<?php

/**
 * Env — shared environment helpers
 *
 * A thin static utility class for reading OS-level context that multiple
 * classes need (home directory, etc.) without coupling those classes to each
 * other.  No business logic lives here.
 */
class Env
{
    /**
     * Resolve the current user's home directory.
     *
     * Resolution order:
     *   1. $HOME environment variable (fastest, covers the vast majority of cases)
     *   2. posix_getpwuid() — for cron/daemon contexts where HOME is unset
     *
     * Exits with a logged error if neither source yields a usable path.
     *
     * @return string Absolute home-directory path with no trailing slash.
     */
    public static function resolveHome(): string
    {
        $home = getenv('HOME');
        if ($home !== false && $home !== '') {
            return rtrim($home, '/');
        }

        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $info = posix_getpwuid(posix_getuid());
            if (isset($info['dir']) && $info['dir'] !== '') {
                return rtrim($info['dir'], '/');
            }
        }

        Logger::log('Cannot determine home directory (HOME is unset and posix_getpwuid unavailable)');
        exit(1);
    }
}
