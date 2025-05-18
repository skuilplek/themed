<?php declare(strict_types=1);

namespace Skuilplek\Themed;

use Exception;

trait ThemedLogTrait {


    /**
     * Optional custom logger callback. Signature: function(string $message): void
     * @var callable|null
     */
    protected $loggerCallback = null;

    /**
     * Set a custom logger callback for ThemedComponent.
     * @param callable(string): void $callback
     */
    public function setLoggerCallback(callable $callback): void
    {
        $this->loggerCallback = $callback;
        error_log("Custom logger callback registered"); // Use error_log here to avoid recursion
    }

    /**
     * Log a message with auto-detected debug levels
     * 
     * @param string $message Message to log. The level is auto-detected from the message prefix:
     *                       'ERROR:' = level 0 (error)
     *                       'WARN:' = level 1 (warning)
     *                       'NOTICE:' = level 2 (notice)
     *                       No prefix = level 3 (info)
     */
    protected function log(string $message): void
    {
        // If a custom logger is set, use it regardless of debug level
        if ($this->loggerCallback !== null) {
            call_user_func($this->loggerCallback, $message);
            return;
        }

        self::internalLog($message);
    }

    /**
     * Internal logging implementation with auto-detected debug levels
     * 
     * @param string $message Message to log. The level is auto-detected from the message prefix:
     *                       'ERROR:' = level 0 (error)
     *                       'WARN:' = level 1 (warning)
     *                       'NOTICE:' = level 2 (notice)
     *                       No prefix = level 3 (info)
     */
    protected function internalLog(string $message): void
    {
        $debug = (bool) $this->getThemedConfig('debug');
        $debugLevel = (int) $this->getThemedConfig('debug_level');

        // Auto-detect level from message prefix
        $level = 3; // Default to INFO level
        if (preg_match('/^(ERROR|WARN|NOTICE):/', $message, $matches)) {
            $level = match ($matches[1]) {
                'ERROR' => 0,
                'WARN' => 1,
                'NOTICE' => 2
            };
            // Remove the prefix from the message
            $message = trim(substr($message, strlen($matches[1]) + 1));
        }

        // Skip logging if debugging is disabled or message level is higher than debug level
        if (!$debug || $debugLevel < $level) {
            return;
        }

        // Add level prefix to message
        $prefix = match ($level) {
            0 => '[ERROR] ',
            1 => '[WARN] ',
            2 => '[NOTICE] ',
            3 => '[INFO] ',
            default => '[LOG] '
        };

        $logFilePath = $this->getThemedConfig('debug_log');
        $logMessage = date('Y-m-d H:i:s') . ' ' . $prefix . $message . "\n";

        // Ensure log directory exists
        $logDir = dirname($logFilePath);
        if (!is_dir($logDir)) {
            try {
                mkdir($logDir, 0755, true);
            } catch (\Exception $e) {
                error_log("Unable to create log directory: " . $e->getMessage());
                return;
            }
        }

        // Create log file if it doesn't exist
        if (!file_exists($logFilePath)) {
            try {
                touch($logFilePath);
                chmod($logFilePath, 0644);
            } catch (\Exception $e) {
                error_log("Unable to create log file: " . $e->getMessage());
                return;
            }
        }

        // Check file size and rotate if needed (1MB = 1048576 bytes)
        if (file_exists($logFilePath) && filesize($logFilePath) > 1048576) {
            // Find the next available backup number
            $backupNumber = 1;
            while (file_exists($logFilePath . '.' . $backupNumber)) {
                $backupNumber++;
                // Limit to 5 backup files to prevent unlimited growth
                if ($backupNumber > 5) {
                    unlink($logFilePath . '.1'); // Remove oldest backup
                    // Shift all files down by one number
                    for ($i = 1; $i < 5; $i++) {
                        if (file_exists($logFilePath . '.' . ($i + 1))) {
                            rename($logFilePath . '.' . ($i + 1), $logFilePath . '.' . $i);
                        }
                    }
                    $backupNumber = 5;
                    break;
                }
            }
            rename($logFilePath, $logFilePath . '.' . $backupNumber);
            try {
                touch($logFilePath);
            } catch (Exception $e) {
                error_log("Unable to create log file: " . $e->getMessage());
                return;
            }
        }

        $message = date("Y-m-d H:i:s") . " - " . $message;
        if (file_exists($logFilePath)) {
            file_put_contents($logFilePath, $message . "\n", FILE_APPEND);
        }
    }

}