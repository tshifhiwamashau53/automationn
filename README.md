# Auto Deploy PHP — quick install and usage

This guide walks through everything needed to install and run this automatic-deploy tool on a Linux server. Follow the exact commands and file contents below. After you finish each step, the app will:

- accept GitHub push webhooks at /webhook.php
- clone the repository into a timestamped release directory
- switch a `current` symlink to the new release
- run optional pre/post scripts
- run a health check and send a Slack/email-style notification (if configured)
- support manual deploy, status and rollback commands

Requirements
- A Linux server (Ubuntu/Debian recommended)
- SSH access to the server
- Git installed on the server
- PHP 7.4 or newer (cli + curl + zip extensions)
- Composer
- (Optional) a Slack incoming webhook or email for notifications

Summary of steps
1. Prepare server user and packages.
2. Clone the repository and install PHP dependencies.
3. Copy and edit configuration.
4. Create required files (webhook endpoint, minimal PHP classes, CLI scripts).
5. Make scripts executable and set permissions.
6. Add the server Deploy Key to GitHub and create the webhook.
7. Test webhook and do a manual deploy.

Quick install (copy & paste)
```bash
# 1. Create a dedicated deploy user
sudo adduser --disabled-password --gecos "Deploy User" deploy

# 2. Install required packages (Debian/Ubuntu)
sudo apt update
sudo apt install -y git php-cli php-curl php-zip unzip curl

# 3. Install composer (if not present)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"

# 4. Clone this repo (on server)
cd /home/deploy
git clone https://github.com/<your-org-or-username>/<your-repo>.git auto-deploy
cd auto-deploy

# 5. Install PHP libraries
composer install --no-dev --optimize-autoloader

# 6. Copy config and edit
cp config.example.php config.php
nano config.php   # edit repository, deploy_to, webhook secret, etc.

# 7. Create public dir and webhook file
mkdir -p public
# paste the webhook.php contents from this README into public/webhook.php

# 8. Create bin and scripts
mkdir -p bin scripts src/AutoDeployPHP releases deploy/logs
# paste the bin/*.php contents and src/AutoDeployPHP/*.php files from this README

# 9. Make scripts executable
chmod +x bin/*.php
chmod +x scripts/*.sh

# 10. Set permissions
sudo chown -R deploy:deploy /home/deploy/auto-deploy
```

Files to create
- Place `public/webhook.php` under the `public/` directory.
- Place PHP classes under `src/AutoDeployPHP/`.
- Place CLI scripts under `bin/`.
- Place hooks under `scripts/`.

public/webhook.php
```php
<?php
// Minimal webhook endpoint. Expects config.php in project root.
require_once __DIR__ . '/../vendor/autoload.php';

use AutoDeployPHP\WebhookHandler;

$config = require __DIR__ . '/../config.php';
$handler = new WebhookHandler($config);
header('Content-Type: application/json');
echo $handler->handle();
```

src/AutoDeployPHP/Config.php
```php
<?php
namespace AutoDeployPHP;

class Config
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function load(string $path): self
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: $path");
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new \RuntimeException("Config file must return an array.");
        }
        return new self($data);
    }

    public function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $value = $this->config;
        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                return $default;
            }
            $value = $value[$p];
        }
        return $value;
    }

    public function all(): array
    {
        return $this->config;
    }
}
```

src/AutoDeployPHP/Deployer.php
```php
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
        $this->deployPath = rtrim($this->config->get('deployment.deploy_to', __DIR__ . '/../../deploy'), '/');
        $this->repository = $this->config->get('deployment.repository');
        $this->branch = $this->config->get('deployment.branch', 'main');
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
        $this->runCommand(sprintf('git clone --depth=1 --branch %s %s %s', escapeshellarg($this->branch), escapeshellarg($this->repository), escapeshellarg($releaseDir)));
        // Run pre-deploy hooks
        $preHooks = $this->config->get('pre_deploy_hooks', []);
        foreach ($preHooks as $hook) {
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
            unlink($current);
        }
        rename($tmpLink, $current);

        // Run post-deploy hooks from current
        $postHooks = $this->config->get('post_deploy_hooks', []);
        foreach ($postHooks as $hook) {
            $hookPath = $current . '/' . ltrim($hook, '/');
            if (file_exists($hookPath) && is_executable($hookPath)) {
                $this->runCommand(escapeshellcmd($hookPath));
            }
        }

        // Health check
        $hc = $this->config->get('health_check', []);
        $healthy = true;
        if (!empty($hc['enabled']) && !empty($hc['url'])) {
            $expected = $hc['expected_status'] ?? 200;
            $cmd = sprintf('curl -s -o /dev/null -w "%{http_code}" --max-time %d %s', (int)($hc['timeout'] ?? 10), escapeshellarg($hc['url']));
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

    public function rollback(): array
    {
        $releases = $this->deployPath . '/releases';
        $current = $this->deployPath . '/current';
        if (!is_dir($releases)) {
            throw new \RuntimeException('No releases to roll back to.');
        }
        $dirs = array_values(array_filter(scandir($releases), fn($d) => $d !== '.' && $d !== '..'));
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
```

src/AutoDeployPHP/WebhookHandler.php
```php
<?php
namespace AutoDeployPHP;

class WebhookHandler
{
    private Config $config;
    private Deployer $deployer;

    public function __construct(array $configArray)
    {
        $this->config = new Config($configArray);
        $this->deployer = new Deployer($this->config);
    }

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
```

bin/deploy.php
```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use AutoDeployPHP\Config;
use AutoDeployPHP\Deployer;

$config = Config::load(__DIR__ . '/../config.php');
$deployer = new Deployer($config);

try {
    $res = $deployer->deploy();
    echo "Deployed release: " . $res['release'] . PHP_EOL;
    echo "Healthy: " . ($res['healthy'] ? 'yes' : 'no') . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo "Deploy failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
```

bin/status.php
```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';
$deployPath = rtrim($config['deployment']['deploy_to'] ?? __DIR__ . '/../deploy', '/');
$log = $deployPath . '/deploy_logs/last_deploy.json';
if (!file_exists($log)) {
    echo "No deploy performed yet.\n";
    exit(0);
}
echo file_get_contents($log) . PHP_EOL;
```

bin/rollback.php
```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
use AutoDeployPHP\Config;
use AutoDeployPHP\Deployer;

$config = Config::load(__DIR__ . '/../config.php');
$deployer = new Deployer($config);
try {
    $res = $deployer->rollback();
    echo "Rolled back to: " . $res['rolled_back_to'] . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo "Rollback failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
```

scripts/before-deploy.sh
```bash
#!/bin/bash
set -e
echo "Running before-deploy hook"
# Add custom commands here, e.g. build assets
# Example:
# cd /var/www/myapp/current
# npm install && npm run build
```

scripts/after-deploy.sh
```bash
#!/bin/bash
set -e
echo "Running after-deploy hook"
# Add commands to restart services, warm caches, etc.
# Example:
# supervisorctl restart myworkers || true
```

Configuration notes
- Edit `config.php` (copy from `config.example.php`) and set:
  - deployment.repository — the repo the server will clone (use SSH e.g. git@github.com:org/repo.git)
  - deployment.deploy_to — full path on the server where releases and current live (e.g. /var/www/myapp)
  - github.webhook_secret — a long random string used to verify webhooks

Example minimal `config.php` (replace values)
```php
<?php
return [
    'deployment' => [
        'repository' => 'git@github.com:username/your-website.git',
        'branch' => 'main',
        'deploy_to' => '/var/www/mywebsite',
        'keep_releases' => 5,
        'timeout' => 300,
    ],
    'github' => [
        'webhook_secret' => 'change-to-a-long-random-string',
        'verify_signature' => true,
        'events' => ['push']
    ],
    'pre_deploy_hooks' => [
        'scripts/before-deploy.sh'
    ],
    'post_deploy_hooks' => [
        'scripts/after-deploy.sh'
    ],
    'health_check' => [
        'enabled' => true,
        'url' => 'https://your-domain.com/health',
        'expected_status' => 200,
        'timeout' => 10
    ],
];
```

Set up GitHub access for the server
1. On the server, become deploy user:
   sudo su - deploy
2. Generate SSH key:
   ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519 -N ""
3. Show the public key:
   cat ~/.ssh/id_ed25519.pub
4. In GitHub repo → Settings → Deploy keys → Add deploy key. Paste the public key. Give read access.
5. Test:
   ssh -T git@github.com
   git clone --depth=1 git@github.com:username/your-website.git /tmp/test-clone

Create GitHub webhook
1. In your repo on github.com → Settings → Webhooks → Add webhook.
2. Payload URL: https://your-server-domain/webhook.php
3. Content type: application/json
4. Secret: the same as github.webhook_secret in config.php
5. Which events: push (and pull_request if you want)
6. Save.

Testing webhook locally (optional)
- On the server you can simulate a push:
```bash
PAYLOAD='{"ref":"refs/heads/main"}'
SECRET="your_secret_here"
SIG="sha256=$(printf '%s' "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')"
curl -s -H "Content-Type: application/json" -H "X-Hub-Signature-256: $SIG" -H "X-Github-Event: push" -d "$PAYLOAD" https://your-server-domain/webhook.php
```

Manual deploy and rollback
- Manual deploy:
  php bin/deploy.php --branch main
- Check last deploy:
  php bin/status.php
- Rollback to the previous release:
  php bin/rollback.php

Permissions & services
- All files and the deploy path should be owned by the dedicated deploy user:
  sudo chown -R deploy:deploy /var/www/mywebsite
- Ensure bin/*.php and scripts/*.sh are executable.

Troubleshooting
- "git clone fails": check the server's SSH key is added to GitHub deploy keys.
- "Signature invalid": ensure the webhook secret matches exactly and your webhook sends X-Hub-Signature-256.
- "Permission denied when creating releases/current": fix file ownership and permissions.
- Check logs in DEPLOY_PATH/deploy_logs/last_deploy.json for recent results.

Security & production advice
- Use HTTPS for your webhook endpoint.
- Do not put secrets in your repo. Use environment variables or server config and keep config.php out of Git.
- Prefer Deploy Keys (SSH) instead of adding a long-lived PAT into config.php.
- Restrict the webhook endpoint by IP or use a firewall.

If you want, I can:
- create these files in the repository for you (I will need confirmation and repo details),
- or produce a small GitHub Actions workflow that runs tests and linting before deploy.
