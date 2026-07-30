<?php

namespace AutoDeployPHP;

class Deployer
{
    private Config $config;
    private Logger $logger;
    private Notification $notifier;
    private string $deployPath;
    private string $releasesPath;
    private string $currentLink;

    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->notifier = new Notification($config, $logger);
        $this->deployPath = $config->get('deployment.deploy_to');
        $this->releasesPath = $this->deployPath . '/releases';
        $this->currentLink = $this->deployPath . '/current';
    }

    /**
     * Execute deployment
     */
    public function deploy(string $branch = null): array
    {
        try {
            $branch = $branch ?? $this->config->get('deployment.branch', 'main');

            if (!Security::isValidBranch($branch)) {
                throw new \Exception("Invalid branch name: $branch");
            }

            $this->logger->info("Starting deployment of branch: $branch");

            // Create releases directory
            $this->ensureReleasesDir();

            // Create new release directory
            $releaseDir = $this->createRelease();
            $this->logger->info("Created release directory: $releaseDir");

            // Run pre-deployment hooks
            $this->runHooks($this->config->get('pre_deploy_hooks', []), $releaseDir);

            // Clone/pull repository
            $this->fetchCode($releaseDir, $branch);
            $this->logger->info("Code fetched successfully");

            // Update current symlink
            $this->updateCurrentLink($releaseDir);
            $this->logger->info("Current symlink updated to: $releaseDir");

            // Run post-deployment hooks
            $this->runHooks($this->config->get('post_deploy_hooks', []), $releaseDir);

            // Cleanup old releases
            $this->cleanupReleases();

            // Run health check
            $healthy = $this->healthCheck();

            $result = [
                'success' => true,
                'release' => basename($releaseDir),
                'path' => $releaseDir,
                'healthy' => $healthy,
                'message' => 'Deployment completed successfully',
            ];

            // Notify about success/failure based on health
            $this->notifier->send($healthy ? 'success' : 'failure', $result['message'], $result);

            $this->logger->info("Deployment completed successfully");

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Deployment failed: " . $e->getMessage());

            $result = [
                'success' => false,
                'message' => $e->getMessage(),
            ];

            // Notify about failure
            try {
                $this->notifier->send('failure', 'Deployment failed: ' . $e->getMessage(), $result);
            } catch (\Exception $ne) {
                $this->logger->warning('Notifier failed: ' . $ne->getMessage());
            }

            return $result;
        }
    }

    /**
     * Rollback to previous release programmatically
     */
    public function rollback(): array
    {
        $rollback = new Rollback($this->config, $this->logger);
        return $rollback->run();
    }

    /**
     * Ensure releases directory exists
     */
    private function ensureReleasesDir(): void
    {
        if (!is_dir($this->releasesPath)) {
            mkdir($this->releasesPath, 0755, true);
        }
    }

    /**
     * Create new release directory
     */
    private function createRelease(): string
    {
        $releaseDir = $this->releasesPath . '/' . date('YmdHis');
        mkdir($releaseDir, 0755, true);
        return $releaseDir;
    }

    /**
     * Fetch code from repository
     */
    private function fetchCode(string $releaseDir, string $branch): void
    {
        $repository = $this->config->get('deployment.repository');
        $timeout = $this->config->get('deployment.timeout', 300);

        $escRepo = escapeshellarg($repository);
        $escBranch = escapeshellarg($branch);
        $escDir = escapeshellarg($releaseDir);

        if (is_dir($releaseDir . '/.git')) {
            // Use fetch + reset to ensure a clean state and use shallow fetch
            $cmd = "cd {$escDir} && git fetch --depth=1 origin {$escBranch} && git reset --hard origin/{$escBranch}";
            $this->executeCommand($cmd, $timeout);
        } else {
            // Shallow clone to reduce network and disk usage
            $cmd = "git clone --depth=1 --single-branch --branch {$escBranch} {$escRepo} {$escDir}";
            $this->executeCommand($cmd, $timeout);
        }
    }

    /**
     * Update current symlink atomically
     */
    private function updateCurrentLink(string $releaseDir): void
    {
        $tempLink = $this->currentLink . '.tmp';

        if (is_link($tempLink)) {
            unlink($tempLink);
        }

        symlink($releaseDir, $tempLink);

        if (is_link($this->currentLink) || file_exists($this->currentLink)) {
            unlink($this->currentLink);
        }
        rename($tempLink, $this->currentLink);
    }

    /**
     * Run deployment hooks
     */
    private function runHooks(array $hooks, string $releaseDir): void
    {
        $escDir = escapeshellarg($releaseDir);
        foreach ($hooks as $hook) {
            $hookPath = $releaseDir . '/' . $hook;

            if (!file_exists($hookPath)) {
                $this->logger->warning("Hook not found: $hook");
                continue;
            }

            chmod($hookPath, 0755);
            $this->logger->info("Running hook: $hook");

            $escHook = escapeshellarg($hookPath);
            $output = $this->executeCommand("cd {$escDir} && bash {$escHook}");
            $this->logger->info("Hook output: " . substr($output, 0, 1000));
        }
    }

    /**
     * Execute system command (safe: non-blocking reads, timeout, close stdin)
     */
    private function executeCommand(string $command, int $timeout = 300): string
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new \Exception("Failed to execute command: $command");
        }

        // Close stdin - we don't send input
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        // Set streams to non-blocking
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            stream_set_blocking($pipes[1], false);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            stream_set_blocking($pipes[2], false);
        }

        $stdout = '';
        $stderr = '';
        $start = microtime(true);

        // Poll streams until process exits or timeout
        while (true) {
            $status = proc_get_status($process);
            $running = $status['running'];

            if (isset($pipes[1]) && is_resource($pipes[1])) {
                $stdoutChunk = stream_get_contents($pipes[1]);
                if ($stdoutChunk !== false && $stdoutChunk !== '') {
                    $stdout .= $stdoutChunk;
                }
            }
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                $stderrChunk = stream_get_contents($pipes[2]);
                if ($stderrChunk !== false && $stderrChunk !== '') {
                    $stderr .= $stderrChunk;
                }
            }

            if (!$running) {
                break;
            }

            if ((microtime(true) - $start) > $timeout) {
                // Attempt graceful termination
                proc_terminate($process);
                // give it a brief moment
                usleep(100000);
                $status = proc_get_status($process);
                if ($status['running']) {
                    // Force close
                    proc_close($process);
                }

                throw new \Exception("Command timed out after {$timeout}s: $command\nPartial output: $stdout\nError output: $stderr");
            }

            // Sleep a little to avoid busy loop
            usleep(10000);
        }

        // Read any remaining output
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            $stdout .= stream_get_contents($pipes[1]);
            fclose($pipes[1]);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            $stderr .= stream_get_contents($pipes[2]);
            fclose($pipes[2]);
        }

        $code = proc_close($process);

        if ($code !== 0) {
            throw new \Exception("Command failed (code $code): $command\nError: $stderr\nOutput: $stdout");
        }

        return $stdout;
    }

    /**
     * Cleanup old releases
     */
    private function cleanupReleases(): void
    {
        $keep = $this->config->get('deployment.keep_releases', 5);

        if (!is_dir($this->releasesPath)) {
            return;
        }

        $releases = array_diff(
            scandir($this->releasesPath, SCANDIR_SORT_DESCENDING),
            ['.', '..']
        );

        if (count($releases) <= $keep) {
            return;
        }

        $toDelete = array_slice($releases, $keep);

        foreach ($toDelete as $release) {
            $path = $this->releasesPath . '/' . $release;
            $this->removeDirectory($path);
            $this->logger->info("Deleted old release: $release");
        }
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    /**
     * Run health check
     */
    private function healthCheck(): bool
    {
        if (!$this->config->get('health_check.enabled', true)) {
            return true;
        }

        $url = $this->config->get('health_check.url');
        if (!$url) {
            return true;
        }

        $timeout = $this->config->get('health_check.timeout', 10);
        $retries = $this->config->get('health_check.retries', 3);
        $delay = $this->config->get('health_check.retry_delay', 2);
        $expected = $this->config->get('health_check.expected_status', 200);

        for ($i = 0; $i < $retries; $i++) {
            if ($i > 0) {
                sleep($delay);
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => $this->config->get('security.verify_ssl', true),
            ]);

            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code == $expected) {
                $this->logger->info("Health check passed: $url ($code)");
                return true;
            }
        }

        $this->logger->warning("Health check failed after $retries attempts");
        return false;
    }
}
