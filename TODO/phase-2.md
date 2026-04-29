## Phase 2 — Config.php

- [ ] Create `app/Config.php`
- [ ] Implement `Config::load(): array` — reads and JSON-decodes `~/.opensignifo/config.json`
- [ ] Validate required top-level keys: `projects_root`, `daily_budget_usd`, `projects`
- [ ] Expand `~` in `projects_root` to the real home directory path
- [ ] Validate each project entry has `name`, `active` (bool), `maintenance_token` (int)
- [ ] Exit with a clear error message (via Logger) if config is missing or malformed
- [ ] Implement `Config::save(array $config): void` — writes updated config back to JSON
  - Used to persist `maintenance_token` increments after each cycle
  - Write atomically: write to a `.tmp` file, then `rename()` over the real file
- [ ] Implement `Config::getActiveProjects(): array` — filters to `active: true` entries only

**Acceptance:** `Config::load()` returns a valid array; invalid JSON or missing keys exits cleanly.
