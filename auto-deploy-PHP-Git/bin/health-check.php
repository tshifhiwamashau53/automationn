#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use AutoDeployPHP\Config;

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
$health = $config->get('health_check', []);
if (empty($health['url'])) {
    fwrite(STDERR, "No health_check.url configured\n");
    exit(1);
}

$url = $health['url'];
$timeout = $health['timeout'] ?? 10;
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => $config->get('security.verify_ssl', true),
]);

curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Health check HTTP status: $code\n";
exit(($code === ($health['expected_status'] ?? 200)) ? 0 : 2);
