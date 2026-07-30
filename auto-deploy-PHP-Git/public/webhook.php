<?php
require_once __DIR__ . '/../vendor/autoload.php';

use AutoDeployPHP\Config;
use AutoDeployPHP\Logger;
use AutoDeployPHP\WebhookHandler;

// Load configuration (prefer config.php, fall back to example)
$configPath = __DIR__ . '/../config.php';
$examplePath = __DIR__ . '/../config.example.php';
if (file_exists($configPath)) {
    $configArray = include $configPath;
} elseif (file_exists($examplePath)) {
    $configArray = include $examplePath;
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No config.php or config.example.php found']);
    exit(1);
}

$config = new Config($configArray);
$logDir = $config->get('deployment.deploy_to', sys_get_temp_dir()) . '/deploy_logs';
$logger = new Logger($logDir);

$handler = new WebhookHandler($config, $logger);

header('Content-Type: application/json');
echo $handler->handle();
