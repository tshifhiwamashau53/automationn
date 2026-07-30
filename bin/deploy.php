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
