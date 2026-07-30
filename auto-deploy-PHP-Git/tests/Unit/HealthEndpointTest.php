<?php
use PHPUnit\Framework\TestCase;

final class HealthEndpointTest extends TestCase
{
    public function testHealthEndpointRunsAndReturnsOK(): void
    {
        $output = [];
        $returnVar = 0;

        // Execute the health endpoint with the PHP CLI
        $cmd = escapeshellcmd((PHP_BINARY ?? 'php')) . ' -f ' . escapeshellarg(__DIR__ . '/../../public/health.php');
        exec($cmd, $output, $returnVar);

        $this->assertSame(0, $returnVar, "Health endpoint did not exit with code 0");
        $this->assertStringContainsString('OK', implode("\n", $output));
    }
}
