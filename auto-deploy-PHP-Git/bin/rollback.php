#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use AutoDeployPHP\Config;
use AutoDeployPHP\Logger;
use AutoDeployPHP\Rollback as RollbackRunner;

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

$runner = new RollbackRunner($config, $logger);
$result = $runner->run();

if (!empty($result['success'])) {
    echo json_encode($result) . PHP_EOL;
    exit(0);
}

fwrite(STDERR, json_encode($result) . PHP_EOL);
exit(2);
