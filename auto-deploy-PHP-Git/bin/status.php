#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use AutoDeployPHP\Config;
use AutoDeployPHP\Logger;

// Load config
$configPath = __DIR__ . '/../config.php';
$examplePath = __DIR__ . '/../config.example.php';
if (file_exists($configPath)) {
    $configArray = include $configPath;
} elseif (file_exists($examplePath)) {
    $configArray = include $examplePath;
} else {
    fwrite(STDERR, "Missing config.php and config.example.php\n");
    exit(1);
}

$config = new Config($configArray);
$deployTo = $config->get('deployment.deploy_to', sys_get_temp_dir());
$logDir = $deployTo . '/deploy_logs';
$logger = new Logger($logDir);

$current = $deployTo . '/current';
if (is_link($current)) {
    $target = readlink($current);
    echo "Current release: " . basename($target) . "\n";
    echo "Path: " . $target . "\n";
} else {
    echo "No current symlink found at: $current\n";
}

$latestLog = $logDir . '/latest.log';
if (file_exists($latestLog)) {
    echo "Latest log: $latestLog\n";
}
