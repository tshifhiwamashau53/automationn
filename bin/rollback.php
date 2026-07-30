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
