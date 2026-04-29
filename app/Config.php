<?php

/**
 * Config — Phase 2
 *
 * Loads, validates, and saves ~/.opensignifo/config.json.
 * Every other phase depends on this class for project lists and settings.
 */
class Config
{
    private static ?array $config = null;

    /**
     * Load and validate config.json. Caches the result for subsequent calls.
     *
     * @return array The validated config array.
     */
    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $home = self::resolveHome();
        $path = $home . '/.opensignifo/config.json';

        if (!file_exists($path)) {
            Logger::log("Config file not found: {$path}");
            Logger::log('Create ~/.opensignifo/config.json with required keys: projects_root, daily_budget_usd, projects');
            exit(1);
        }

        $json = file_get_contents($path);
        $config = json_decode($json, true);

        if (!is_array($config)) {
            Logger::log("Invalid JSON in config file: {$path}");
            exit(1);
        }

        $required = ['projects_root', 'daily_budget_usd', 'projects'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $config)) {
                Logger::log("Missing required config key: {$key}");
                exit(1);
            }
        }

        $config['projects_root'] = self::expandTilde($config['projects_root'], $home);

        if (!is_array($config['projects'])) {
            Logger::log('Config key "projects" must be an array');
            exit(1);
        }

        foreach ($config['projects'] as $i => $project) {
            foreach (['name', 'active', 'maintenance_token'] as $key) {
                if (!array_key_exists($key, $project)) {
                    Logger::log("Missing required key '{$key}' in projects[{$i}]");
                    exit(1);
                }
            }
            if (!is_bool($project['active'])) {
                Logger::log("projects[{$i}].active must be a boolean");
                exit(1);
            }
            if (!is_int($project['maintenance_token'])) {
                Logger::log("projects[{$i}].maintenance_token must be an integer");
                exit(1);
            }
        }

        self::$config = $config;
        return self::$config;
    }

    /**
     * Write config back to disk atomically.
     *
     * Used to persist maintenance_token increments and other runtime updates.
     *
     * @param array $config The full config array to write.
     */
    public static function save(array $config): void
    {
        $home = self::resolveHome();
        $path = $home . '/.opensignifo/config.json';
        $tmp  = $path . '.tmp';

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($tmp, $json) === false) {
            Logger::log("Failed to write config tmp file: {$tmp}");
            exit(1);
        }
        if (!rename($tmp, $path)) {
            Logger::log("Failed to atomically replace config file: {$path}");
            exit(1);
        }

        // Keep the cache consistent: store the ~ -expanded version, matching load().
        $config['projects_root'] = self::expandTilde($config['projects_root'], $home);
        self::$config = $config;
    }

    /**
     * Return only active projects, re-indexed.
     *
     * @return array Active project entries.
     */
    public static function getActiveProjects(): array
    {
        $config = self::load();
        return array_values(array_filter($config['projects'], fn($p) => $p['active'] === true));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the current user's home directory.
     * Prefers the HOME env-var; falls back to posix_getpwuid() for environments
     * where HOME is not set (e.g. some cron / daemon contexts).
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

    /**
     * Expand a leading "~" or "~/" to the real home directory.
     * Only the leading tilde is replaced — tildes elsewhere in the path are left
     * untouched, which is the POSIX-standard behaviour.
     */
    private static function expandTilde(string $path, string $home): string
    {
        if ($path === '~') {
            return $home;
        }
        if (str_starts_with($path, '~/')) {
            return $home . substr($path, 1);
        }
        return $path;
    }
}
