# Server setup for auto-deploy-PHP-Git

This document contains an exact, copy-pasteable, start→finish set of commands and configuration files to make the repository run automated deployments from GitHub webhooks on an Ubuntu server (22.04 / 24.04). Follow the commands exactly. Replace values marked in UPPERCASE (e.g., YOUR_DOMAIN, YOUR_REPO) with your own.

Important notes before you begin:
- Use a dedicated non-root user named `deploy` (the instructions create this user).
- Do NOT commit any secrets (github.webhook_secret) to the repo. Put them in `/var/www/myapp/config.php` on the server or use environment variables.
- This guide sets up a dedicated PHP-FPM pool that runs as `deploy` so webhook requests run with the correct SSH keys and file permissions.

Quick overview of what we add:
- public/health.php (simple health endpoint)
- Nginx example site config (nginx/deploy.example.conf)
- PHP-FPM pool example (to run PHP as `deploy` user)
- Detailed step-by-step commands to setup the server

Contents
--------
1) Prepare server and install packages
2) Create `deploy` user and SSH key for GitHub
3) Clone repository and install PHP dependencies
4) Configure `config.php` and health endpoint
5) Configure PHP-FPM pool for `deploy` user
6) Configure Nginx and TLS
7) Add GitHub webhook and test
8) Run a manual deploy and verify
9) Troubleshooting & hardening checklist

1) Prepare server and install packages

Run these commands as a sudo user (copy-paste):

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Create deploy user (non-interactive)
sudo adduser --disabled-password --gecos "Deploy User" deploy

# Install required packages
sudo apt install -y git php-cli php-fpm php-curl unzip curl openssl nginx certbot python3-certbot-nginx

# Install composer globally (if not present)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
```

2) Create SSH key for the deploy user and register on GitHub

```bash
# Generate key and display public key
sudo -iu deploy bash -c 'mkdir -p ~/.ssh && chmod 700 ~/.ssh; ssh-keygen -t ed25519 -C "deploy@$(hostname)" -f ~/.ssh/id_ed25519 -N "" && cat ~/.ssh/id_ed25519.pub'

# Add GitHub to known_hosts to avoid interactive prompt
sudo -iu deploy bash -c 'ssh-keyscan github.com >> ~/.ssh/known_hosts && chmod 644 ~/.ssh/known_hosts'
```

- Copy the printed public key and add it to your GitHub repository Settings → Deploy keys → Add deploy key (read-only recommended).

3) Clone repository, install dependencies, set permissions

Pick an install path (we use `/var/www/myapp` in examples). Replace `tshifhiwa021006/auto-deploy-PHP-Git.git` with your repo if you moved the code.

```bash
sudo mkdir -p /var/www
sudo chown deploy:deploy /var/www

sudo -iu deploy bash -c '
cd /var/www
git clone git@github.com:tshifhiwa021006/auto-deploy-PHP-Git.git myapp
cd myapp
composer install --no-dev --optimize-autoloader
cp config.example.php config.php
chmod +x bin/*.php scripts/*.sh
'

# Ensure ownership/permissions
sudo chown -R deploy:deploy /var/www/myapp
sudo chmod -R 755 /var/www/myapp
```

4) Configure `config.php` and add the health endpoint

Edit `/var/www/myapp/config.php` and set at minimum:
- deployment.repository => `git@github.com:YOUR_USERNAME/YOUR_REPO.git`
- deployment.branch => `main`
- deployment.deploy_to => `/var/www/myapp`
- deployment.keep_releases => `5`
- github.webhook_secret => a long random string (32+ chars)
- security.ssh_key => `/home/deploy/.ssh/id_ed25519` (optional)

Quick edit example:

```bash
sudo -u deploy cp /var/www/myapp/config.example.php /var/www/myapp/config.php
sudo -u deploy nano /var/www/myapp/config.php
```

Create the health endpoint (this file is used by the deploy health check):

```bash
sudo -u deploy tee /var/www/myapp/public/health.php > /dev/null <<'PHP'
<?php
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'time' => date('c')]);
PHP
```

5) Configure PHP-FPM pool to run as `deploy` user

Create a pool file for PHP-FPM so webhook requests execute as `deploy`. Adjust the PHP version in the path (8.1 used in this example).

```bash
PHP_FPM_CONF="/etc/php/8.1/fpm/pool.d/deploy.conf"
sudo tee $PHP_FPM_CONF > /dev/null <<'CONF'
[deploy]
user = deploy
group = deploy
listen = /run/php/php-fpm-deploy.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 5
pm.start_servers = 1
pm.min_spare_servers = 1
pm.max_spare_servers = 3
chdir = /var/www/myapp
CONF

sudo systemctl restart php8.1-fpm
```

If you use a different PHP version, change `8.1` to your version.

6) Configure Nginx and TLS

Create Nginx site config (example): `/etc/nginx/sites-available/deploy.example.com` — replace `deploy.example.com` and `root` with your domain and path.

```
server {
    listen 80;
    server_name deploy.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name deploy.example.com;

    root /var/www/myapp/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/deploy.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/deploy.example.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php-fpm-deploy.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

Enable the site and reload Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/deploy.example.com /etc/nginx/sites-enabled/deploy.example.com
sudo nginx -t
sudo systemctl reload nginx
```

Obtain TLS certificate with certbot (replace domain):

```bash
sudo certbot --nginx -d deploy.example.com
```

7) Verify Git access from server

```bash
sudo -iu deploy bash -c 'ssh -T git@github.com || echo "SSH test may prompt for host fingerprint"'
sudo -iu deploy bash -c 'git clone --depth=1 git@github.com:tshifhiwa021006/auto-deploy-PHP-Git.git /tmp/test-clone && rm -rf /tmp/test-clone || echo clone failed'
```

8) Configure GitHub webhook

In your repository on github.com → Settings → Webhooks → Add webhook:
- Payload URL: `https://deploy.example.com/webhook.php`
- Content type: `application/json`
- Secret: the same `github.webhook_secret` you set in `config.php`
- Events: `push` (and optionally `pull_request`)

9) Test webhook manually (simulate GitHub)

```bash
printf '{"ref":"refs/heads/main"}' > /tmp/payload.json
SECRET="YOUR_WEBHOOK_SECRET"
PAYLOAD=$(cat /tmp/payload.json)
SIG="sha256=$(printf '%s' "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')"

curl -v -H "Content-Type: application/json" \
     -H "X-Hub-Signature-256: $SIG" \
     -d "$PAYLOAD" \
     https://deploy.example.com/webhook.php
```

Expect a JSON response. If you see `Invalid signature`, re-check the secret and that the payload used to compute the HMAC is byte-identical to what you POST.

10) Manual deploy test and logs

```bash
sudo -iu deploy bash -c 'php /var/www/myapp/bin/deploy.php --branch main'
# Check log
sudo -u deploy tail -n 200 /var/www/myapp/deploy_logs/deploy-$(date +%F).log
```

11) Troubleshooting & hardening checklist

- If `git clone` fails with "Permission denied (publickey)", add the deploy public key to GitHub Deploy Keys.
- If webhook returns 403 IP not allowed, set `security.restrict_to_github_ips` to false in `config.php` during testing or populate `security.allowed_ips` with GitHub webhook IP ranges.
- Keep `github.webhook_secret` secret; do not commit it.
- Run deployments as `deploy` user; do not run webhook as root or as `www-data`.
- Consider moving webhook handling out of request thread into a queue and background worker if you expect high traffic or long deploy times (optional advanced improvement).

End of setup document.
