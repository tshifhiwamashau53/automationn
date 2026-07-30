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
