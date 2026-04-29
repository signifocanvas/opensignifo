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

        $path = getenv('HOME') . '/.opensignifo/config.json';

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

        $config['projects_root'] = str_replace('~', getenv('HOME'), $config['projects_root']);

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
        $path = getenv('HOME') . '/.opensignifo/config.json';
        $tmp = $path . '.tmp';

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($tmp, $json);
        rename($tmp, $path);

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
}
