<?php
namespace AutoDeployPHP;

class Deployer
{
    private Config $config;
    private string $deployPath;
    private string $repository;
    private string $branch;

    public function __construct(Config $config)
    {
        $this->config = $config;

        // Retrieve values and guard their types to avoid casting mixed to string
        $deployTo = $this->config->get('deployment.deploy_to');
        if (!is_string($deployTo) || $deployTo === '') {
            $deployTo = __DIR__ . '/../../deploy';
        }
        $this->deployPath = rtrim($deployTo, '/');

        $repo = $this->config->get('deployment.repository');
        $this->repository = is_string($repo) ? $repo : '';

        $br = $this->config->get('deployment.branch');
        $this->branch = is_string($br) && $br !== '' ? $br : 'main';
    }

    private function timestamp(): string
    {
        return date('Y-m-d-His');
    }

    private function runCommand(string $cmd, bool $failOnError = true): string
    {
        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);
        $text = implode("\n", $output);
        if ($failOnError && $code !== 0) {
            throw new \RuntimeException("Command failed: $cmd\nOutput: $text");
        }
        return $text;
    }

    /**
     * @return array{release:string,healthy:bool,path:string}
     */
    public function deploy(): array
    {
        if (!$this->repository) {
            throw new \RuntimeException('No repository configured.');
        }
        $releases = $this->deployPath . '/releases';
        $current = $this->deployPath . '/current';
        @mkdir($releases, 0755, true);
        @mkdir(dirname($current), 0755, true);

        $releaseDir = $releases . '/' . $this->timestamp();
        $this->runCommand(sprintf(
            'git clone --depth=1 --branch %s %s %s',
            escapeshellarg($this->branch),
            escapeshellarg($this->repository),
            escapeshellarg($releaseDir)
        ));
        // Run pre-deploy hooks
        $preHooks = (array)$this->config->get('pre_deploy_hooks', []);
        foreach ($preHooks as $hook) {
            if (!is_string($hook) || $hook === '') {
                continue;
            }
            $hookPath = $releaseDir . '/' . ltrim($hook, '/');
            if (file_exists($hookPath) && is_executable($hookPath)) {
                $this->runCommand(escapeshellcmd($hookPath));
            }
        }
        // Symlink swap (atomic on most Unixes)
        $tmpLink = $this->deployPath . '/current_tmp';
        @unlink($tmpLink);
        symlink($releaseDir, $tmpLink);
        // Rename atomically
        if (file_exists($current)) {
            rename($current, $this->deployPath . '/previous');
        }
        rename($tmpLink, $current);

        // Run post-deploy hooks from current
        $postHooks = (array)$this->config->get('post_deploy_hooks', []);
        foreach ($postHooks as $hook) {
            if (!is_string($hook) || $hook === '') {
                continue;
            }
            $hookPath = $current . '/' . ltrim($hook, '/');
            if (file_exists($hookPath) && is_executable($hookPath)) {
                $this->runCommand(escapeshellcmd($hookPath));
            }
        }

        // Health check
        $hc = (array)$this->config->get('health_check', []);
        $healthy = true;
        if (!empty($hc['enabled']) && !empty($hc['url']) && is_string($hc['url'])) {
            $expected = $hc['expected_status'] ?? 200;
            $timeout = (int)($hc['timeout'] ?? 10);
            $urlArg = escapeshellarg($hc['url']);
            $cmd = sprintf(
                'curl -s -o /dev/null -w "%%{http_code}" --max-time %d %s',
                $timeout,
                $urlArg
            );
            $codeStr = trim($this->runCommand($cmd, false));
            $healthy = ((int)$codeStr === (int)$expected);
        }

        // Keep a history file
        $logDir = $this->deployPath . '/deploy_logs';
        @mkdir($logDir, 0755, true);
        file_put_contents($logDir . '/last_deploy.json', json_encode([
            'release' => basename($releaseDir),
            'time' => time(),
            'healthy' => $healthy
        ], JSON_PRETTY_PRINT));

        return ['release' => basename($releaseDir), 'healthy' => $healthy, 'path' => $current];
    }

    /**
     * @return array{rolled_back_to:string,path:string}
     */
    public function rollback(): array
    {
        $releases = $this->deployPath . '/releases';
        $current = $this->deployPath . '/current';
        if (!is_dir($releases)) {
            throw new \RuntimeException('No releases to roll back to.');
        }
        $scanned = scandir($releases);
        if ($scanned === false) {
            throw new \RuntimeException('Failed to read releases directory.');
        }
        $dirs = array_values(array_filter($scanned, fn($d) => $d !== '.' && $d !== '..'));
        rsort($dirs);
        if (count($dirs) < 2) {
            throw new \RuntimeException('Not enough releases to roll back.');
        }
        $previous = $releases . '/' . $dirs[1]; // the one before the current newest
        // swap
        if (is_link($current) || file_exists($current)) {
            @unlink($current);
        }
        symlink($previous, $current);
        return ['rolled_back_to' => basename($previous), 'path' => $current];
    }
}
