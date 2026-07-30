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

$releasesDir = $deployTo . '/releases';
if (!is_dir($releasesDir)) {
    echo "No releases directory found: $releasesDir\n";
    exit(0);
}

$releases = array_values(array_diff(scandir($releasesDir, SCANDIR_SORT_DESCENDING), ['.', '..']));
if (empty($releases)) {
    echo "No releases found in $releasesDir\n";
    exit(0);
}

$limit = 20;
$argLimit = $argv[1] ?? null;
if ($argLimit && is_numeric($argLimit)) {
    $limit = (int)$argLimit;
}

$show = array_slice($releases, 0, $limit);
foreach ($show as $r) {
    echo $r . "\n";
}
