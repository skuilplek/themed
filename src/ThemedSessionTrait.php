<?php declare(strict_types=1);

namespace Skuilplek\Themed;

trait ThemedSessionTrait {
    const SESSION_KEY = "sk_themed";

    /**
     * Ensure session is started
     */
    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    private function setSessionKey(mixed $key, mixed $value): void
    {
        $this->ensureSessionStarted();
        if (is_array($key)) {
            // Handle nested array keys
            $current = &$_SESSION[self::SESSION_KEY];
            foreach ($key as $k) {
                $current = &$current[$k];
            }
            $current = $value;
        } else {
            // Handle single key
            $_SESSION[self::SESSION_KEY][$key] = $value;
        }
    }

    private function getSessionKey(mixed $key): mixed
    {
        $this->ensureSessionStarted();
        return $_SESSION[self::SESSION_KEY][$key] ?? null;
    }
}