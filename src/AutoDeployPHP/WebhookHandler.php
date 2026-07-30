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
        $secretRaw = $this->config->get('github.webhook_secret');
        if (empty($secretRaw)) {
            return true; // no secret set — not recommended
        }

        if (!is_string($secretRaw) && !is_scalar($secretRaw) && !(is_object($secretRaw) && method_exists($secretRaw, '__toString'))) {
            return false;
        }
        $secret = (string)$secretRaw;

        $sig = '';
        if (isset($headers['HTTP_X_HUB_SIGNATURE_256']) && is_string($headers['HTTP_X_HUB_SIGNATURE_256'])) {
            $sig = $headers['HTTP_X_HUB_SIGNATURE_256'];
        } elseif (isset($headers['X-Hub-Signature-256']) && is_string($headers['X-Hub-Signature-256'])) {
            $sig = $headers['X-Hub-Signature-256'];
        }
        if ($sig === '') {
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
        $payload = @file_get_contents('php://input');
        if (!is_string($payload)) {
            $payload = '';
        }

        $headers = $_SERVER;
        if (!$this->verifySignature($headers, $payload)) {
            http_response_code(401);
            $resp = json_encode(['success' => false, 'message' => 'Invalid signature']);
            if ($resp === false) {
                return '{"success":false,"message":"Invalid signature"}';
            }
            return $resp;
        }

        $data = json_decode($payload, true);
        // Quick check: only react to push (or allow all if not specified)
        $eventsRaw = $this->config->get('github.events', ['push', 'pull_request']);
        if (!is_array($eventsRaw)) {
            $events = is_scalar($eventsRaw) ? [$eventsRaw] : ['push', 'pull_request'];
        } else {
            $events = $eventsRaw;
        }

        $eventHeader = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
        if (!in_array($eventHeader, $events, true) && !empty($events)) {
            $resp = json_encode(['success' => false, 'message' => 'Event ignored', 'event' => $eventHeader]);
            if ($resp === false) {
                return '{"success":false,"message":"Event ignored"}';
            }
            return $resp;
        }

        try {
            $result = $this->deployer->deploy();
            $resp = json_encode(['success' => true, 'result' => $result]);
            if ($resp === false) {
                return '{"success":true,"result":{}}';
            }
            return $resp;
        } catch (\Throwable $e) {
            http_response_code(500);
            $resp = json_encode(['success' => false, 'message' => $e->getMessage()]);
            if ($resp === false) {
                return '{"success":false,"message":"Internal error"}';
            }
            return $resp;
        }
    }
}
