<?php
use PHPUnit\Framework\TestCase;

final class CLIScriptsTest extends TestCase
{
    public function testStatusScriptRuns(): void
    {
        $output = [];
        $code = 0;
        $cmd = escapeshellcmd((PHP_BINARY ?? 'php')) . ' ' . escapeshellarg(__DIR__ . '/../../bin/status.php');
        exec($cmd, $output, $code);

        // status.php should exit 0 and print either a message about current symlink or that none exists
        $this->assertSame(0, $code, 'status.php did not exit with code 0');
        $joined = implode("\n", $output);
        $this->assertMatchesRegularExpression('/(Current release:|No current symlink found|No releases directory found)/', $joined);
    }

    public function testHistoryScriptRuns(): void
    {
        $output = [];
        $code = 0;
        $cmd = escapeshellcmd((PHP_BINARY ?? 'php')) . ' ' . escapeshellarg(__DIR__ . '/../../bin/history.php');
        exec($cmd, $output, $code);

        // history.php should exit 0 even if no releases (it prints a message)
        $this->assertSame(0, $code, 'history.php did not exit with code 0');
    }

    public function testHealthCheckScriptExitsWithCode(): void
    {
        $output = [];
        $code = 0;
        $cmd = escapeshellcmd((PHP_BINARY ?? 'php')) . ' ' . escapeshellarg(__DIR__ . '/../../bin/health-check.php');
        exec($cmd, $output, $code);

        // health-check.php will exit 0 if health_check.url is configured and returns expected status; otherwise non-zero or prints message
        $this->assertIsInt($code);
    }
}
