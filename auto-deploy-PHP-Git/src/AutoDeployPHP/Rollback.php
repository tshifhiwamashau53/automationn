<?php

namespace AutoDeployPHP;

class Rollback
{
    private Config $config;
    private Logger $logger;

    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Perform a rollback to the previous release.
     * Returns an array with success, previous, and message.
     */
    public function run(): array
    {
        $deployTo = $this->config->get('deployment.deploy_to', sys_get_temp_dir());
        $current = $deployTo . '/current';
        $releasesDir = $deployTo . '/releases';

        if (!is_dir($releasesDir)) {
            $msg = "No releases directory found: $releasesDir";
            $this->logger->error($msg);
            return ['success' => false, 'message' => $msg];
        }

        $releases = array_values(array_diff(scandir($releasesDir, SCANDIR_SORT_DESCENDING), ['.', '..']));
        if (count($releases) < 2) {
            $msg = "Not enough releases to rollback";
            $this->logger->warning($msg);
            return ['success' => false, 'message' => $msg];
        }

        $previous = $releases[1] ?? null;
        if ($previous === null) {
            $msg = "No previous release found";
            $this->logger->error($msg);
            return ['success' => false, 'message' => $msg];
        }

        $previousPath = $releasesDir . '/' . $previous;
        if (!is_dir($previousPath)) {
            $msg = "Previous release directory not found: $previousPath";
            $this->logger->error($msg);
            return ['success' => false, 'message' => $msg];
        }

        // Atomic symlink swap
        $tmp = $current . '.tmp';
        if (is_link($tmp)) {
            @unlink($tmp);
        }
        if (!@symlink($previousPath, $tmp)) {
            $msg = "Failed to create temporary symlink";
            $this->logger->error($msg);
            return ['success' => false, 'message' => $msg];
        }
        if (is_link($current) || file_exists($current)) {
            @unlink($current);
        }
        if (!@rename($tmp, $current)) {
            $msg = "Failed to rename tmp symlink to current";
            $this->logger->error($msg);
            return ['success' => false, 'message' => $msg];
        }

        $this->logger->info("Rolled back to release: $previous");

        return ['success' => true, 'previous' => $previous, 'message' => "Rolled back to $previous"];
    }
}
