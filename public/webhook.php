<?php
// Minimal webhook endpoint. Expects config.php in project root.
require_once __DIR__ . '/../vendor/autoload.php';

use AutoDeployPHP\WebhookHandler;

$config = require __DIR__ . '/../config.php';
$handler = new WebhookHandler($config);
header('Content-Type: application/json');
echo $handler->handle();
