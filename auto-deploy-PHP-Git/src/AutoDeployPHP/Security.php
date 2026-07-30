<?php

namespace AutoDeployPHP;

class Security
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Verify GitHub webhook signature
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        if (!$this->config->get('github.verify_signature', true)) {
            return true;
        }

        $secret = $this->config->get('github.webhook_secret', '');
        if (empty($secret)) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify request comes from GitHub IP
     */
    public function verifyIp(string $ip): bool
    {
        if (!$this->config->get('security.restrict_to_github_ips', true)) {
            return true;
        }

        $allowed = $this->config->get('security.allowed_ips', []);
        if (empty($allowed)) {
            $allowed = $this->getGitHubIps();
        }

        foreach ($allowed as $allowed_ip) {
            if ($this->ipInRange($ip, $allowed_ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range (supports IPv4 and IPv6)
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range);
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // Ensure same address family (IPv4 vs IPv6)
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int)$bits;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        // Compare full bytes
        if ($bytes > 0) {
            if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }
        }

        // Compare remaining bits
        if ($remainder > 0) {
            $mask = (0xFF00 >> $remainder) & 0xFF; // high bits mask
            $ipByte = ord($ipBin[$bytes]);
            $subByte = ord($subnetBin[$bytes]);
            if ((($ipByte & $mask) !== ($subByte & $mask))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get GitHub's IP ranges
     */
    private function getGitHubIps(): array
    {
        // It's safer to maintain these via config or fetch from https://api.github.com/meta in production.
        return [
            '140.82.112.0/20',
            '143.55.64.0/20',
            '185.199.108.0/22',
            // Note: keep this list updated or set security.restrict_to_github_ips = false while testing
        ];
    }

    /**
     * Validate branch name
     */
    public static function isValidBranch(string $branch): bool
    {
        return preg_match('/^[a-zA-Z0-9\/\_\-.]+$/', $branch) === 1;
    }
}
