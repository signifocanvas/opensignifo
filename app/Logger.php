<?php

/**
 * Logger — Phase 1
 *
 * Console output, always timestamped, always to STDOUT.
 * Format: [HH:MM:SS] message
 *
 * Every subsequent phase depends on this class being available.
 */
class Logger
{
    /**
     * Write a timestamped line to STDOUT and flush immediately.
     *
     * @param string $message The message to log (no trailing newline needed).
     */
    public static function log(string $message): void
    {
        $line = '[' . date('H:i:s') . '] ' . $message . PHP_EOL;
        fwrite(STDOUT, $line);
        fflush(STDOUT);
    }
}
