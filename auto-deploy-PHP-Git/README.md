# Auto Deploy PHP Git

Automatically update your website when you push code to GitHub. No manual uploads needed.

## What It Does

When you push code to GitHub, this tool automatically:
- Downloads your new code
- Updates your website
- Checks if everything works
- Sends you a message saying it's done

## What You Can Do With It

- Push code to GitHub, website updates automatically
- Get notified on Slack when your code goes live
- Go back to an old version if something breaks
- Run commands before and after updating
- Check if your website is working after each update
- Deploy different code to different servers
- View a history of all your deployments

## What You Need

- PHP 7.4 or higher
- Git installed on your computer and server
- Composer (for installing libraries)
- SSH access to your server (to upload files)
- A GitHub account

## Quick Start (5 Minutes)

```bash
# 1. Download
git clone https://github.com/tshifhiwa021006/auto-deploy-PHP-Git.git
cd auto-deploy-PHP-Git

# 2. Install
composer install

# 3. Setup
cp config.example.php config.php
nano config.php  # Edit with YOUR settings

# 4. Create webhook file
mkdir -p public
cat > public/webhook.php << 'EOF'
<?php
require_once __DIR__ . '/../vendor/autoload.php';
use AutoDeployPHP\WebhookHandler;
use AutoDeployPHP\Config;
$config = Config::load(__DIR__ . '/../config.php');
$handler = new WebhookHandler($config);
echo $handler->handle();
EOF

# 5. Tell GitHub about it (see Step 5 below)
```

Done! Push code and watch it deploy automatically.

## How to Set It Up (Detailed)

### Step 1: Download the Tool

```bash
git clone https://github.com/tshifhiwa021006/auto-deploy-PHP-Git.git
cd auto-deploy-PHP-Git
```

### Step 2: Install Libraries

```bash
composer install
```

This downloads all the code libraries this tool needs to work.

### Step 3: Set Up Your Settings

Copy the example settings file:

```bash
cp config.example.php config.php
```

Edit `config.php` with your information. Here's what each setting means:

```php
<?php
return [
    'deployment' => [
        // Your GitHub repo URL (find this on GitHub)
        'repository' => 'git@github.com:username/your-repo.git',
        
        // Which branch to deploy (main, master, develop, etc)
        'branch' => 'main',
        
        // Where to upload on your server
        'deploy_to' => '/var/www/html/app',
        
        // How many old versions to keep (in case you need to go back)
        'keep_releases' => 5,
    ],
    
    'github' => [
        // This is a secret code - make it hard to guess (at least 32 characters)
        'webhook_secret' => 'your-very-secret-key-here-make-it-long',
        
        // Get this from GitHub: Settings > Developer settings > Personal access tokens
        'token' => 'github_pat_xxxxxxxxxxxxx',
    ],
    
    'notifications' => [
        // Get this from Slack: https://api.slack.com/apps (optional)
        'slack_webhook' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
        
        // Where to send email alerts
        'email' => 'admin@example.com',
    ],
    
    'pre_deploy_hooks' => [
        // Commands to run BEFORE uploading (optional)
        'scripts/before-deploy.sh',
    ],
    
    'post_deploy_hooks' => [
        // Commands to run AFTER uploading (optional)
        'scripts/after-deploy.sh',
    ],
];
```

### Step 4: Create the Webhook File

Create a new file at `public/webhook.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use AutoDeployPHP\WebhookHandler;
use AutoDeployPHP\Config;

$config = Config::load(__DIR__ . '/../config.php');
$handler = new WebhookHandler($config);

echo $handler->handle();
```

This file is what GitHub talks to. When you push code, GitHub sends a message to this file.

### Step 5: Tell GitHub to Notify Your Server

1. Go to your GitHub repository on github.com
2. Click **Settings** (top right)
3. Click **Webhooks** (left side menu)
4. Click **Add webhook** (green button)
5. Fill in the form:
   - **Payload URL:** `https://your-website.com/webhook.php` (replace with YOUR domain)
   - **Content type:** `application/json`
   - **Secret:** Copy your secret from `config.php`
   - **Which events:** Select `push` and `pull_request`
6. Click **Add webhook** button

Now GitHub will automatically tell your server every time you push code!

## How to Use It

### Automatic Deployment (Recommended)

This is the easiest way. Just push your code normally:

```bash
git push origin main
```

Your website updates automatically in about 30 seconds. Check Slack or email for confirmation.

### Manual Deployment

If you want to deploy without pushing:

```bash
php bin/deploy.php --branch main --environment production
```

### Check Deployment Status

See what version is running right now:

```bash
php bin/status.php
```

### Go Back to Previous Version

If something breaks after deployment:

```bash
php bin/rollback.php
```

Done. Your old version is back online.

### See Previous Deployments

View the last 20 deployments:

```bash
php bin/history.php --limit 20
```

### Check If Website is Working

Run automatic checks:

```bash
php bin/health-check.php
```

## Folder Structure

Here's what each folder/file does:

```
auto-deploy-PHP-Git/
├── bin/                    # Commands you can run
│   ├── deploy.php          # Deploy manually
│   ├── rollback.php        # Go back to old version
│   ├── status.php          # Check current version
│   ├── history.php         # See past deployments
│   └── health-check.php    # Test if it works
│
├── src/                    # The actual tool code
│   ├── WebhookHandler.php  # Listens for GitHub notifications
│   ├── Deployer.php        # Does the uploading
│   ├── Rollback.php        # Goes back to old version
│   ├── Notification.php    # Sends Slack/email messages
│   ├── Config.php          # Reads your settings
│   └── Security.php        # Checks if it's really GitHub
│
├── public/                 # Web files (your server sees these)
│   └── webhook.php         # GitHub sends messages here
│
├── scripts/                # Extra commands that run
│   ├── before-deploy.sh    # Runs before uploading
│   └── after-deploy.sh     # Runs after uploading
│
├── tests/                  # Tests to check if it works
│   ├── Unit/               # Small tests
│   └── Integration/        # Tests that work together
│
├── config.example.php      # Example settings (copy this)
├── config.php              # YOUR settings (don't share!)
├── composer.json           # List of libraries needed
├── phpunit.xml             # Testing settings
└── README.md               # This file
```

## What Happens When You Push Code

Here's step-by-step what happens:

```
1. You type: git push
   ↓
2. GitHub receives your code
   ↓
3. GitHub sends a message to your server (webhook)
   ↓
4. Your server receives: "Hey, new code is here!"
   ↓
5. Server checks: "Is this really from GitHub?" (using secret)
   ↓
6. Server runs: scripts/before-deploy.sh (prep work)
   ↓
7. Server downloads the new code from GitHub
   ↓
8. Server creates a new folder with today's date (releases/2026-07-27-143022/)
   ↓
9. Server runs your code in that folder
   ↓
10. Server makes a shortcut called "current" pointing to new folder
    (This is why it's fast - just changing a shortcut, not copying files)
   ↓
11. Server runs: scripts/after-deploy.sh (cleanup)
   ↓
12. Server checks: "Is the website working?" (health check)
   ↓
13. Server sends Slack message: "Deployment successful!"
   ↓
14. DONE! Website is updated
```

Typical time: 30-60 seconds

## Settings Explained

### Deployment Settings

| Setting | What It Does | Example |
|---------|-------------|---------|
| `repository` | Your GitHub repo URL | `git@github.com:username/myapp.git` |
| `branch` | Which branch to deploy | `main` or `production` |
| `deploy_to` | Where to upload files | `/var/www/html/myapp` |
| `keep_releases` | How many old versions to keep | `5` (keeps last 5 versions) |

### GitHub Settings

| Setting | What It Does | Where to Get It |
|---------|-------------|-----------------|
| `webhook_secret` | Secret code to verify it's GitHub | Make it up (32+ characters) |
| `token` | GitHub access token | GitHub Settings > Developer settings > Personal access tokens |

### Notification Settings

| Setting | What It Does | How to Get It |
|---------|-------------|---------------|
| `slack_webhook` | Send Slack messages | https://api.slack.com/apps |
| `email` | Send email alerts | Any email address |

## Before and After Scripts

### Before Uploading (`scripts/before-deploy.sh`)

This runs BEFORE your website gets the new code. Use it to prepare:

```bash
#!/bin/bash
set -e

echo "Preparing to deploy..."

# Update the database (if using Laravel)
php artisan migrate --force

# Clear old cached data
php artisan cache:clear

# Build new CSS/JavaScript
npm run build

# Compress images
php artisan optimize:images
```

### After Uploading (`scripts/after-deploy.sh`)

This runs AFTER your website has the new code. Use it to clean up:

```bash
#!/bin/bash
set -e

echo "Deployment complete, cleaning up..."

# Warm up the cache so site is fast
php artisan cache:warmup

# Restart background workers
supervisorctl restart all

# Tell monitoring service it's done
curl -X POST https://your-website.com/api/deployment-done

# Send success notification
echo "Deployment finished successfully!"
```

## Environment File (For Secrets)

Instead of putting secrets in `config.php`, create `.env` file:

```bash
# .env file (NEVER commit this to GitHub!)
GITHUB_TOKEN=github_pat_xxxxxxxxxxxxx
GITHUB_WEBHOOK_SECRET=your-super-secret-key
SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK
EMAIL_ADDRESS=admin@example.com
DEPLOY_USER=deploy_user
DEPLOY_HOST=production.example.com
DEPLOY_PATH=/var/www/html/app
```

Then in `config.php`:

```php
<?php
return [
    'deployment' => [
        'repository' => $_ENV['GITHUB_REPO'] ?? 'git@github.com:user/repo.git',
        'token' => $_ENV['GITHUB_TOKEN'],
        // ... rest of config
    ],
];
```

## Quick server setup and website upload (step-by-step)

This section shows the shortest, safest path from a fresh clone to automatic deploys on a Linux server (Ubuntu/Debian example). It covers both automatic webhook-driven deploys and manual CLI deploys.

---

### 1) Prepare locally (developer machine)
1. Clone the repo and install dependencies:
```bash
git clone https://github.com/<your-org-or-username>/<your-repo>.git
cd <your-repo>
composer install
```

2. Copy the example config and edit it:
```bash
cp config.example.php config.php
nano config.php
```
Key values to set in `config.php`:
- `deployment.repository` — your repo URL. Prefer SSH for server: `git@github.com:username/your-repo.git`
- `deployment.branch` — branch to deploy (e.g. `main`)
- `deployment.deploy_to` — absolute path on the server where releases/current will live (e.g. `/var/www/myapp`)
- `deployment.keep_releases` — how many old releases to keep (e.g. `5`)
- `github.webhook_secret` — a long random secret used to verify GitHub webhooks (32+ chars)
- `github.token` — (optional) PAT if you need API access
- `health_check.url` — a URL path on your site that returns 200 when healthy (optional)

You can also use environment variables as explained earlier in this README.

3. Make scripts executable:
```bash
chmod +x bin/*.php
chmod +x scripts/*.sh
```

---

### 2) Prepare the server (example steps)
On your server (Ubuntu/Debian), run:

1. Create a dedicated deploy user (recommended) and install system packages:
```bash
sudo adduser --disabled-password --gecos "Deploy User" deploy
sudo apt update
sudo apt install -y git php-cli php-curl unzip curl
# Install composer (if not present)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
```

2. Create the deployment path and give ownership to the deploy user:
```bash
sudo mkdir -p /var/www/myapp
sudo chown -R deploy:deploy /var/www/myapp
```

3. Add an SSH key for the `deploy` user so the server can clone from GitHub:
- On server, as the deploy user:
```bash
sudo -iu deploy
ssh-keygen -t ed25519 -C "deploy@myserver" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub
# Copy the pub key to clipboard
```
- In GitHub repository settings → Deploy keys → Add deploy key (paste the public key). Give write access if you need pushes from server (usually read-only is fine).

4. Test Git access from server:
```bash
# as deploy user
ssh -T git@github.com
git clone --depth=1 git@github.com:username/your-repo.git /tmp/test-clone
```
If clone works, remove the test clone:
```bash
rm -rf /tmp/test-clone
```

---

### 3) Deploy the app code & install dependencies on the server
1. On the server as the deploy user, clone the repo into a safe location (or copy your repo files there):
```bash
cd /var/www
git clone git@github.com:username/your-repo.git myapp
cd myapp
composer install --no-dev --optimize-autoloader
```

2. Create `config.php` on the server (copy from `config.example.php`) and set:
- `deployment.deploy_to` to `/var/www/myapp` (or `/var/www/myapp/deploy` depending on your preference)
- `github.webhook_secret` to same secret you will use in GitHub webhook
- Any environment specific values (database, notifications, health_check.url)

3. Ensure the directories used by the Deployer are writable:
```bash
# if deploy runs as 'deploy'
sudo chown -R deploy:deploy /var/www/myapp
sudo chmod -R 755 /var/www/myapp
```

---

### 4) Configure the webhook endpoint (auto-deploy)
You have two options: serve `public/webhook.php` from your web server or put it behind an HTTPS endpoint via a small reverse proxy.

1. Place `public/` under your webroot:
- If your site root is `/var/www/myapp/public`, move or symlink the `public` folder there.
- Example with Nginx: point a small server block at `/var/www/myapp/public` and ensure PHP is enabled (php-fpm).

2. Example minimal Nginx server block:
```
server {
    listen 443 ssl;
    server_name deploy.example.com;

    root /var/www/myapp/public;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock; # adjust for your PHP version
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```
Reload Nginx after enabling.

3. Configure GitHub webhook:
- Go to your repository → Settings → Webhooks → Add webhook
  - Payload URL: `https://deploy.example.com/webhook.php` (or your site + path)
  - Content type: `application/json`
  - Secret: the same `github.webhook_secret` in `config.php`
  - Which events: choose `push` and `pull_request` (or just `push`)
  - Save.

4. Test webhook locally (simulate GitHub):
Create a JSON payload file `payload.json` (can be a real push payload or minimal one with `"ref":"refs/heads/main"`). Compute HMAC and POST:
```bash
SECRET="your_webhook_secret_here"
PAYLOAD=$(cat payload.json)
SIG="sha256=$(printf '%s' "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')"

curl -v -H "Content-Type: application/json" \
     -H "X-Hub-Signature-256: $SIG" \
     -d "$PAYLOAD" \
     https://deploy.example.com/webhook.php
```
If it returns JSON with `success:true` (or starts the deploy), webhook is working.

---

### 5) Manual deployment (CLI)
If you prefer to trigger deploys manually or from CI, use the included bin script:

1. Run a deploy (from the repository root or from anywhere where the code is present and config.php points to the proper deploy path):
```bash
# as deploy user (or the user who owns the deployment directories)
php bin/deploy.php --branch main
```
This will:
- create a timestamped directory in `deployment.deploy_to`/releases
- shallow clone/fetch the configured repo branch into that directory
- run `scripts/before-deploy.sh` (if present)
- update `current` symlink atomically
- run `scripts/after-deploy.sh`
- run the health check configured in `config.php`

2. Check status/logs:
```bash
php bin/status.php
# or view the log files in deploy_logs under your deploy path
ls -l /var/www/myapp/deploy_logs
tail -n 200 /var/www/myapp/deploy_logs/deploy-$(date +%F).log
```

3. Roll back:
```bash
php bin/rollback.php
```
This switches `current` to the previous release (atomic symlink swap).

---

### 6) Health checks and notifications
- Configure `health_check.url` in `config.php` to point to a small route on your app that returns 200 when healthy, e.g. `/health`.
- The Deployer runs that URL after a deploy and will log and return `healthy:false` if it fails.
- If you configured Slack / email in `config.php`, notifications are sent (if Notification code is present and enabled).

---

### 7) Security & production hardening
- Always use HTTPS for webhook endpoints. Use a valid TLS certificate (Let's Encrypt).
- Keep `github.webhook_secret` secret — do not commit it into Git.
- Prefer adding the server's SSH public key as a GitHub Deploy Key (read-only) instead of putting a PAT into `config.php`.
- Restrict inbound traffic to only GitHub webhook IP ranges (or at least rate-limit / firewall).
- Run deployments as a dedicated non-root user and ensure file permissions are correct.
- Validate hooks (scripts) and keep them minimal and idempotent. Hooks run as the deploy user.

---

### 8) Troubleshooting
- Webhook delivery fails on GitHub: Check Recent Deliveries in the webhook settings; view response body and HTTP status.
- Signature mismatch: ensure the webhook secret in GitHub exactly matches `github.webhook_secret` in `config.php`. HMAC header is `X-Hub-Signature-256: sha256=<hex>`.
- Git clone fails: confirm the server's SSH key is added to GitHub and `git` works from the deploy user.
- Permissions errors: check ownership/permissions of `deployment.deploy_to` and `releases` directories.
- Long-running hooks/timeouts: the Deployer uses timeouts for commands — see `Deployer::executeCommand`. If a command times out, check the hook scripts and increase timeout in config if needed.

---

### 9) Minimal example of config.php (server)
```php
<?php
return [
    'deployment' => [
        'repository' => 'git@github.com:username/your-repo.git',
        'branch' => 'main',
        'deploy_to' => '/var/www/myapp',
        'keep_releases' => 5,
    ],
    'github' => [
        'webhook_secret' => 'change-this-to-a-long-random-string',
        'token' => null,
    ],
    'notifications' => [
        'slack_webhook' => null,
        'email' => 'admin@example.com',
    ],
    'pre_deploy_hooks' => [
        'scripts/before-deploy.sh',
    ],
    'post_deploy_hooks' => [
        'scripts/after-deploy.sh',
    ],
    'health_check' => [
        'enabled' => true,
        'url' => 'https://your-site.com/health',
        'expected_status' => 200,
        'timeout' => 10,
        'retries' => 3,
        'retry_delay' => 2,
    ],
    'security' => [
        'verify_ssl' => true,
        'restrict_to_github_ips' => true,
    ],
];
```


