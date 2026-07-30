<?php

namespace AutoDeployPHP;

class WebhookHandler
{
    private Config $config;
    private Logger $logger;
    private Security $security;

    public function __construct(Config $config, ?Logger $logger = null)
    {
        $this->config = $config;
        $logDir = $this->config->get('deployment.deploy_to', sys_get_temp_dir()) . '/deploy_logs';
        $this->logger = $logger ?? new Logger($logDir);
        $this->security = new Security($config);
    }

    /**
     * Handle incoming webhook and trigger deployment.
     * Returns a JSON string suitable for HTTP response.
     */
    public function handle(): string
    {
        try {
            $payload = file_get_contents('php://input');
            $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

            // Respect common proxy headers for client IP (single-hop proxies like a load balancer)
            $remoteIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            if (strpos($remoteIp, ',') !== false) {
                // X-Forwarded-For may contain a list; take the first (original client)
                $parts = explode(',', $remoteIp);
                $remoteIp = trim($parts[0]);
            }

            // Basic validation
            if (!$this->security->verifySignature($payload, $signature)) {
                http_response_code(403);
                $this->logger->warning('Invalid webhook signature');
                return json_encode(['success' => false, 'message' => 'Invalid signature']);
            }

            if (!$this->security->verifyIp($remoteIp)) {
                http_response_code(403);
                $this->logger->warning('Request IP not allowed: ' . $remoteIp);
                return json_encode(['success' => false, 'message' => 'IP not allowed']);
            }

            $data = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                $this->logger->error('Invalid JSON payload');
                return json_encode(['success' => false, 'message' => 'Invalid JSON']);
            }

            // Determine branch from ref (e.g. refs/heads/main)
            $ref = $data['ref'] ?? ($data['pull_request']['base']['ref'] ?? null);
            if ($ref && strpos($ref, 'refs/heads/') === 0) {
                $branch = substr($ref, strlen('refs/heads/'));
            } else {
                // Fallback to configured branch
                $branch = $this->config->get('deployment.branch', 'main');
            }

            if (!Security::isValidBranch($branch)) {
                http_response_code(400);
                $this->logger->warning('Invalid branch name in payload: ' . $branch);
                return json_encode(['success' => false, 'message' => 'Invalid branch name']);
            }

            $this->logger->info('Webhook accepted for branch: ' . $branch . ' from IP ' . $remoteIp);

            // If running from CLI (manual invocation), run synchronously
            if (php_sapi_name() === 'cli') {
                $logger = $this->logger;
                $deployer = new Deployer($this->config, $logger);
                $result = $deployer->deploy($branch);

                if (!empty($result['success'])) {
                    http_response_code(200);
                    return json_encode($result);
                }

                http_response_code(500);
                return json_encode($result);
            }

            // For web requests, queue/launch deployment as a background process to avoid request timeouts.
            // Try to return to the client quickly, then start the deploy in background.
            $this->logger->info('Queueing background deployment for branch: ' . $branch);

            // Return response early to the caller (best-effort)
            header('Content-Type: application/json');
            $response = json_encode(['success' => true, 'queued' => true, 'message' => 'Deployment queued']);

            // If PHP-FPM, finish request to free the connection before starting the background job
            echo $response;
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                // attempt to flush buffers
                if (ob_get_level()) {
                    @ob_end_flush();
                }
                @flush();
            }

            // Build background command using the CLI PHP binary
            $deployScript = realpath(__DIR__ . '/../../bin/deploy.php');
            $phpBin = PHP_BINARY;
            if ($deployScript === false) {
                $this->logger->error('Cannot locate bin/deploy.php for background run');
                return json_encode(['success' => false, 'message' => 'Internal error']);
            }

            $cmd = sprintf('%s %s --branch %s > /dev/null 2>&1 &',
                escapeshellcmd($phpBin),
                escapeshellarg($deployScript),
                escapeshellarg($branch)
            );

            // Use exec to start non-blocking background process
            exec($cmd, $out, $rc);
            if ($rc !== 0) {
                $this->logger->warning('Background deploy exec returned code: ' . $rc . ' cmd: ' . $cmd);
            } else {
                $this->logger->info('Background deploy started');
            }

            return $response;
        } catch (\Exception $e) {
            http_response_code(500);
            $this->logger->error('Webhook handler exception: ' . $e->getMessage());
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
