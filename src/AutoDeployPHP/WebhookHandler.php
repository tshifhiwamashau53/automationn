<?php
namespace AutoDeployPHP;

class WebhookHandler
{
    private Config $config;
    private Deployer $deployer;

    /**
     * @param array<string,mixed> $configArray
     */
    public function __construct(array $configArray)
    {
        $this->config = new Config($configArray);
        $this->deployer = new Deployer($this->config);
    }

    /**
     * @param array<string,string> $headers
     */
    private function verifySignature(array $headers, string $payload): bool
    {
        $secret = $this->config->get('github.webhook_secret');
        if (empty($secret)) {
            return true; // no secret set — not recommended
        }
        $sig = $headers['HTTP_X_HUB_SIGNATURE_256'] ?? $headers['X-Hub-Signature-256'] ?? '';
        if (!$sig) {
            return false;
        }
        if (strpos($sig, 'sha256=') === 0) {
            $hash = substr($sig, 7);
            $calc = hash_hmac('sha256', $payload, $secret);
            return hash_equals($calc, $hash);
        }
        return false;
    }

    public function handle(): string
    {
        $payload = file_get_contents('php://input') ?: '';
        $headers = $_SERVER;
        if (!$this->verifySignature($headers, $payload)) {
            http_response_code(401);
            return json_encode(['success' => false, 'message' => 'Invalid signature']);
        }

        $data = json_decode($payload, true);
        // Quick check: only react to push (or allow all if not specified)
        $events = $this->config->get('github.events', ['push', 'pull_request']);
        $eventHeader = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
        if (!in_array($eventHeader, $events) && !empty($events)) {
            return json_encode(['success' => false, 'message' => 'Event ignored', 'event' => $eventHeader]);
        }

        try {
            $result = $this->deployer->deploy();
            return json_encode(['success' => true, 'result' => $result]);
        } catch (\Throwable $e) {
            http_response_code(500);
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
