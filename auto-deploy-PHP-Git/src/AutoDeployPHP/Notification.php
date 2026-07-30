<?php

namespace AutoDeployPHP;

class Notification
{
    private Config $config;
    private Logger $logger;

    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Send a notification. $type can be 'success' or 'failure' or 'info'.
     * $payload may contain extra data to include.
     */
    public function send(string $type, string $message, array $payload = []): void
    {
        $enabled = $this->config->get('notifications.enabled', true);
        if (!$enabled) {
            $this->logger->debug('Notifications disabled in config');
            return;
        }

        // Send Slack if configured
        $slack = $this->config->get('notifications.slack_webhook');
        if (!empty($slack)) {
            $this->sendSlack($slack, $type, $message, $payload);
        }

        // Send email if configured
        $email = $this->config->get('notifications.email');
        if (!empty($email)) {
            $this->sendEmail($email, $type, $message, $payload);
        }
    }

    private function sendSlack(string $webhook, string $type, string $message, array $payload = []): void
    {
        $text = ($type === 'success' ? ":white_check_mark: " : ":x: ") . $message;
        $body = json_encode([
            'text' => $text,
            'attachments' => [
                [
                    'color' => $type === 'success' ? 'good' : 'danger',
                    'fields' => array_map(function ($k, $v) {
                        return ['title' => (string)$k, 'value' => (string)$v, 'short' => false];
                    }, array_keys($payload), $payload)
                ]
            ]
        ]);

        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $err) {
            $this->logger->warning('Slack notification failed: ' . $err . ' resp:' . substr((string)$resp, 0, 200));
        } else {
            $this->logger->info('Slack notification sent');
        }
    }

    private function sendEmail(string $to, string $type, string $message, array $payload = []): void
    {
        $prefix = $this->config->get('notifications.email_subject_prefix', '[Deploy] ');
        $subject = $prefix . ($type === 'success' ? 'Deployment succeeded' : 'Deployment failed');
        $body = $message . "\n\n" . json_encode($payload, JSON_PRETTY_PRINT);

        // Try mail() as a best-effort fallback
        $headers = 'From: noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";
        $ok = @mail($to, $subject, $body, $headers);
        if ($ok) {
            $this->logger->info('Email notification queued to: ' . $to);
        } else {
            $this->logger->warning('Email notification failed (mail returned false)');
        }
    }
}
