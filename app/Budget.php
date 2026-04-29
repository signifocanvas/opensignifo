<?php

/**
 * Budget — Phase 3
 *
 * Tracks daily API spend in ~/.opensignifo/budget.json.
 * Must be consulted before every API call:
 *   Budget::check($dailyLimit);   // exits if limit reached
 *   Budget::record($cost);        // adds cost after a successful call
 *
 * budget.json structure:
 * {
 *   "YYYY-MM-DD": { "spent_usd": 0.000, "calls": 0 },
 *   ...
 * }
 */
class Budget
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Exit if today's spend has already reached or exceeded $dailyLimit.
     *
     * Called before every API request. Logs the reason and exits cleanly so
     * the caller does not need to handle a return value.
     *
     * @param float $dailyLimit Maximum USD allowed per day (from config).
     */
    public static function check(float $dailyLimit): void
    {
        $spent = self::todaySpent();

        if ($spent >= $dailyLimit) {
            Logger::log(sprintf(
                '[BUDGET] Daily limit of $%.2f reached. Exiting.',
                $dailyLimit
            ));
            exit(0);
        }
    }

    /**
     * Add $cost to today's running total and increment the call counter.
     *
     * Written atomically via a .tmp file + rename() so a crash mid-write
     * never leaves a corrupt budget.json.
     *
     * @param float $cost The cost of the API call just completed (USD).
     */
    public static function record(float $cost): void
    {
        $path   = self::budgetPath();
        $data   = self::readData($path);
        $today  = self::today();

        if (!isset($data[$today])) {
            $data[$today] = ['spent_usd' => 0.0, 'calls' => 0];
        }

        $data[$today]['spent_usd'] += $cost;
        $data[$today]['calls']     += 1;

        self::writeData($path, $data);
    }

    /**
     * Return today's total USD spend (0.0 if no calls recorded yet today).
     *
     * @return float Running total in USD.
     */
    public static function todaySpent(): float
    {
        $path  = self::budgetPath();
        $data  = self::readData($path);
        $today = self::today();

        return isset($data[$today]) ? (float) $data[$today]['spent_usd'] : 0.0;
    }

    /**
     * Log a startup summary line: "Budget today: $X.XXX / $Y.YY"
     *
     * @param float $dailyLimit The configured daily limit.
     */
    public static function logStartup(float $dailyLimit): void
    {
        $spent = self::todaySpent();
        Logger::log(sprintf(
            'Budget today: $%.3f / $%.2f',
            $spent,
            $dailyLimit
        ));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Return today's date string in YYYY-MM-DD format (local time).
     */
    private static function today(): string
    {
        return date('Y-m-d');
    }

    /**
     * Resolve the full path to ~/.opensignifo/budget.json,
     * creating the directory if it does not yet exist.
     */
    private static function budgetPath(): string
    {
        $home = self::resolveHome();
        $dir  = $home . '/.opensignifo';

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                Logger::log("Failed to create opensignifo directory: {$dir}");
                exit(1);
            }
        }

        return $dir . '/budget.json';
    }

    /**
     * Read and decode budget.json.  Returns an empty array when the file does
     * not yet exist (first run) or is empty.  Exits on malformed JSON.
     *
     * @param string $path Absolute path to budget.json.
     * @return array Decoded budget data keyed by date string.
     */
    private static function readData(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $raw = file_get_contents($path);

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Logger::log("Corrupt budget.json — could not parse JSON: {$path}");
            exit(1);
        }

        return $data;
    }

    /**
     * Write budget data back to disk atomically (.tmp + rename).
     *
     * @param string $path Absolute path to budget.json.
     * @param array  $data Budget data to serialise.
     */
    private static function writeData(string $path, array $data): void
    {
        $tmp  = $path . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($tmp, $json) === false) {
            Logger::log("Failed to write budget tmp file: {$tmp}");
            exit(1);
        }

        if (!rename($tmp, $path)) {
            Logger::log("Failed to atomically replace budget file: {$path}");
            exit(1);
        }
    }

    /**
     * Resolve the current user's home directory.
     * Mirrors the same logic used in Config to avoid coupling the two classes.
     */
    private static function resolveHome(): string
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
