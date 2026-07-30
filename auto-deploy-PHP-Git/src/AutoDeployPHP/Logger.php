<?php

namespace AutoDeployPHP;

class Logger
{
    private string $logDir;
    private string $logFile;
    private string $level;

    public function __construct(string $logDir, string $level = 'info')
    {
        $this->logDir = $logDir;
        $this->level = $level;
        $this->logFile = $logDir . '/deploy-' . date('Y-m-d') . '.log';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Try to ensure latest.log points to today's log file
        $latestLog = $this->logDir . '/latest.log';
        if (is_link($latestLog)) {
            // Do nothing if symlink already exists
        } else {
            // Remove any regular file and attempt to create a symlink (use basename to keep symlink relative)
            @unlink($latestLog);
            @symlink(basename($this->logFile), $latestLog);
        }
    }

    /**
     * Log debug message
     */
    public function debug(string $message): void
    {
        $this->log('DEBUG', $message);
    }

    /**
     * Log info message
     */
    public function info(string $message): void
    {
        $this->log('INFO', $message);
    }

    /**
     * Log warning message
     */
    public function warning(string $message): void
    {
        $this->log('WARNING', $message);
    }

    /**
     * Log error message
     */
    public function error(string $message): void
    {
        $this->log('ERROR', $message);
    }

    /**
     * Log message to file (single append with LOCK_EX)
     */
    private function log(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;

        // Ensure log directory exists
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }

        // Write to daily file with exclusive lock
        @file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // If latest.log is not a symlink, try to create or update it (best-effort)
        $latestLog = $this->logDir . '/latest.log';
        if (!is_link($latestLog)) {
            @unlink($latestLog);
            @symlink(basename($this->logFile), $latestLog);
        }
    }

    /**
     * Get log file path
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }
}
