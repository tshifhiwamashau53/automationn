<?php

return [
    /**
     * Deployment Configuration
     */
    'deployment' => [
        // Git repository URL
        'repository' => 'git@github.com:username/your-repo.git',
        
        // Default branch to deploy
        'branch' => 'main',
        
        // Production server deployment path
        'deploy_to' => '/var/www/html/app',
        
        // Number of releases to keep (older ones are deleted)
        'keep_releases' => 5,
        
        // Timeout in seconds for deployment operations
        'timeout' => 300,
        
        // Enable verbose logging
        'verbose' => true,
    ],

    /**
     * GitHub Configuration
     */
    'github' => [
        // GitHub webhook secret (must match in GitHub settings)
        'webhook_secret' => env('GITHUB_SECRET', 'your-webhook-secret-here'),
        
        // Personal access token for API calls
        'token' => env('GITHUB_TOKEN', ''),
        
        // GitHub API base URL (for self-hosted GitHub)
        'api_url' => 'https://api.github.com',
        
        // Verify webhook payload signature
        'verify_signature' => true,
    ],

    /**
     * Security Settings
     */
    'security' => [
        // Restrict webhooks to GitHub IPs (recommended)
        'restrict_to_github_ips' => true,
        
        // Allowed IP addresses (if restrict_to_github_ips is false)
        'allowed_ips' => [],
        
        // Verify SSL certificates when calling webhooks
        'verify_ssl' => true,
        
        // SSH private key path for Git authentication
        'ssh_key' => env('SSH_KEY_PATH', '/home/deploy/.ssh/id_rsa'),
        
        // SSH known_hosts file path
        'known_hosts' => env('SSH_KNOWN_HOSTS', '/home/deploy/.ssh/known_hosts'),
    ],

    /**
     * Notification Settings
     */
    'notifications' => [
        // Enable notifications
        'enabled' => true,
        
        // Slack webhook URL for notifications
        'slack_webhook' => env('SLACK_WEBHOOK', ''),
        
        // Email address for notifications
        'email' => env('NOTIFICATION_EMAIL', 'admin@example.com'),
        
        // Email subject prefix
        'email_subject_prefix' => '[Deploy] ',
        
        // Send notifications on success
        'notify_on_success' => true,
        
        // Send notifications on failure
        'notify_on_failure' => true,
    ],

    /**
     * Pre-Deployment Hooks
     * Scripts to run BEFORE deployment
     */
    'pre_deploy_hooks' => [
        'scripts/before-deploy.sh',
    ],

    /**
     * Post-Deployment Hooks
     * Scripts to run AFTER successful deployment
     */
    'post_deploy_hooks' => [
        'scripts/after-deploy.sh',
    ],

    /**
     * Health Check Settings
     */
    'health_check' => [
        // Enable health checks after deployment
        'enabled' => true,
        
        // URL to check for deployment success
        'url' => 'https://your-domain.com/health',
        
        // Expected HTTP status code
        'expected_status' => 200,
        
        // Timeout in seconds
        'timeout' => 10,
        
        // Number of retries if check fails
        'retries' => 3,
        
        // Delay between retries in seconds
        'retry_delay' => 2,
    ],

    /**
     * Database Settings (optional)
     */
    'database' => [
        'enabled' => false,
        'driver' => 'mysql',
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_NAME', 'deployments'),
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PASSWORD', ''),
    ],

    /**
     * Logging Settings
     */
    'logging' => [
        // Log file directory
        'directory' => __DIR__ . '/deploy/logs',
        
        // Maximum log file size in MB before rotation
        'max_file_size' => 10,
        
        // Log level: 'debug', 'info', 'warning', 'error'
        'level' => 'info',
        
        // Keep logs for this many days
        'retention_days' => 30,
    ],

    /**
     * Environment-Specific Overrides
     */
    'environments' => [
        'production' => [
            'deploy_to' => '/var/www/html/app',
            'branch' => 'main',
            'keep_releases' => 5,
        ],
        'staging' => [
            'deploy_to' => '/var/www/html/staging',
            'branch' => 'develop',
            'keep_releases' => 3,
        ],
        'development' => [
            'deploy_to' => '/var/www/html/dev',
            'branch' => 'develop',
            'keep_releases' => 2,
        ],
    ],
];

/**
 * Helper function to get environment variables
 */
function env($key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
