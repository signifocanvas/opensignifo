# opensignifo — Implementation Roadmap

This document is the authoritative task list for building opensignifo from scratch.
Each phase maps directly to a section in `ARCHITECTURE.md`. Complete phases in order —
later phases depend on earlier ones.

---

## Phase 1 — Project Scaffolding

Set up the installation layout and the CLI entry point before writing any logic.

- [ ] Create `~/.opensignifo/` directory structure:
  - [ ] `~/.opensignifo/app/` — PHP source files live here
  - [ ] `~/.opensignifo/projects/` — per-project prompt overrides
- [ ] Create placeholder `~/.opensignifo/OPENSIGNIFO.md` with a starter global agent prompt
- [ ] Create `~/.opensignifo/config.json` with example structure (see ARCHITECTURE §3):
  - `projects_root`, `daily_budget_usd`, `projects[]` with `name`, `active`, `maintenance_token`
- [ ] Create empty `~/.opensignifo/budget.json` (`{}`)
- [ ] Create empty `~/.opensignifo/state.json` (`{}`)
- [ ] Create `/usr/local/bin/opensignifo` shell wrapper:
  ```bash
  #!/bin/bash
  php ~/.opensignifo/app/main.php "$@"
  ```
- [ ] `chmod +x /usr/local/bin/opensignifo`
- [ ] Verify `opensignifo --help` (or any arg) reaches `main.php` without error

**Acceptance:** Running `opensignifo` drops into `main.php` (even if it's just `<?php echo "ok\n";`).

---

## Phase 2 — Logger.php

Build console output first — every subsequent phase depends on being able to see what's happening.

- [ ] Create `~/.opensignifo/app/Logger.php`
- [ ] Implement `Logger::log(string $message): void`
  - Prefix every line with `[HH:MM:SS]` using `date('H:i:s')`
  - Write to `STDOUT` (not `STDERR`)
  - Flush immediately (no output buffering)
- [ ] Verify format matches ARCHITECTURE §11 exactly:
  ```
  [10:42:00] opensignifo started. Watching 2 active projects.
  ```

**Acceptance:** `Logger::log('hello')` prints `[HH:MM:SS] hello` and returns.

---

## Phase 3 — Config.php + config.json

- [ ] Create `~/.opensignifo/app/Config.php`
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

---

## Phase 4 — Budget.php + budget.json

Budget enforcement must run before every API call.

- [ ] Create `~/.opensignifo/app/Budget.php`
- [ ] Implement `Budget::check(float $dailyLimit): void`
  - Read `~/.opensignifo/budget.json`
  - Get today's date as `YYYY-MM-DD`
  - If today's `spent_usd >= $dailyLimit` → `Logger::log('[BUDGET] Daily limit of $X reached. Exiting.')` then `exit(0)`
- [ ] Implement `Budget::record(float $cost): void`
  - Read current `budget.json`
  - Add `$cost` to today's `spent_usd`, increment today's `calls` counter
  - Write back atomically (`.tmp` + `rename()`)
- [ ] Implement `Budget::todaySpent(): float` — returns today's running total
- [ ] Log current spend at startup: `Budget today: $X.XXX / $Y.YY`

**Acceptance:** After 3 simulated `Budget::record(0.10)` calls on a $0.25 limit, the 4th `Budget::check()` exits.

---

## Phase 5 — DeepSeekClient.php

The API wrapper. All calls in the whole system go through this one class.

- [ ] Create `~/.opensignifo/app/DeepSeekClient.php`
- [ ] Store pricing constants exactly as in ARCHITECTURE §10:
  ```php
  const V4_FLASH_INPUT_CACHE_HIT  = 0.028;
  const V4_FLASH_INPUT_CACHE_MISS = 0.14;
  const V4_FLASH_OUTPUT           = 0.28;
  const V4_PRO_INPUT_CACHE_HIT    = 0.145;
  const V4_PRO_INPUT_CACHE_MISS   = 1.74;
  const V4_PRO_OUTPUT             = 3.48;
  ```
- [ ] Implement `DeepSeekClient::call(string $model, string $system, string $user, bool $thinking = false): array`
  - `$model` is `'v4-flash'` or `'v4-pro'`
  - `$thinking = true` enables medium thinking (v4-pro only)
  - Returns `['content' => string, 'usage' => [...], 'cost' => float]`
- [ ] Read API key from environment variable `DEEPSEEK_API_KEY`; exit with error if missing
- [ ] Log `Calling {model}...` before the call, log elapsed seconds after
- [ ] Use `prompt_cache_hit_tokens` from the response to split input cost correctly:
  ```
  cost = (cache_hit_tokens / 1M * HIT_PRICE)
       + ((total_input - cache_hit_tokens) / 1M * MISS_PRICE)
       + (output_tokens / 1M * OUTPUT_PRICE)
  ```
- [ ] Call `Budget::check()` before every API call
- [ ] Call `Budget::record($cost)` after every successful API call
- [ ] Implement retry logic (ARCHITECTURE §14):
  - On network error / 500 / rate-limit → log the error, sleep 30s, retry once
  - If second attempt also fails → log and return `null` (caller skips this task)
- [ ] Test with a minimal real call to confirm auth and pricing work

**Acceptance:** A single `v4-flash` call returns a non-empty string and correctly updates `budget.json`.

---

## Phase 6 — PromptBuilder.php

Assembles the stable system prompt once per project per session.

- [ ] Create `~/.opensignifo/app/PromptBuilder.php`
- [ ] Implement `PromptBuilder::build(string $projectName, string $projectRoot): string`
- [ ] Layer 1: always read `~/.opensignifo/OPENSIGNIFO.md` and prepend it
- [ ] Layer 2: if `~/.opensignifo/projects/{projectName}/context.md` exists, append it
- [ ] Layer 3: scan project root for the first file that exists in priority order:
  1. `CLAUDE.md`
  2. `AGENTS.md`
  3. `GEMINI.md`
  4. `README.md` then `ARCHITECTURE.md` (either counts)
  5. Hardcoded generic fallback (exact text from ARCHITECTURE §9)
- [ ] Join all layers with a single blank line between them
- [ ] Return the assembled string — this is the `system` param for every API call

**Acceptance:** With only `OPENSIGNIFO.md` present, returns a non-empty string ending with the generic fallback text.

---

## Phase 7 — GitIgnore.php

- [ ] Create `~/.opensignifo/app/GitIgnore.php`
- [ ] Implement `GitIgnore::load(string $projectRoot): self` — reads `{projectRoot}/.gitignore` if it exists
- [ ] Implement `GitIgnore::matches(string $relativePath): bool`
  - Supports glob patterns (`*.log`, `build/`, `vendor/`)
  - Supports negation rules (`!important.log`)
  - Only root-level `.gitignore` is respected (no recursive parsing)
- [ ] Handle missing `.gitignore` gracefully (treat as no rules)

**Acceptance:** Given a `.gitignore` with `vendor/`, `GitIgnore::matches('vendor/autoload.php')` returns `true`.

---

## Phase 8 — ProjectScanner.php

- [ ] Create `~/.opensignifo/app/ProjectScanner.php`
- [ ] Implement `ProjectScanner::scan(string $projectRoot): array`
  - Returns array of `['path' => string (absolute), 'relative' => string, 'lines' => int]`
- [ ] Scannable extensions: `.php .js .html .css .json .txt .neon .sh .sql` (ARCHITECTURE §12)
- [ ] Always skip:
  - Paths matched by `GitIgnore`
  - `.opensignifo/` directory
  - `node_modules/` and `vendor/` directories (unconditionally)
- [ ] Count lines via `count(file($path))` or equivalent — no content is loaded beyond line count
- [ ] Return results sorted by relative path (deterministic order)

**Acceptance:** On a sample directory, returns only scannable files outside ignored paths with correct line counts.

---

## Phase 9 — State.php + state.json

Tracks file modification times so Option C can detect changes.

- [ ] Create `~/.opensignifo/app/State.php`
- [ ] Data structure in `state.json`:
  ```json
  {
    "project-1": {
      "last_scan": "2026-04-29T10:42:00",
      "files": {
        "src/Controllers/InvoiceController.php": 1714384920
      }
    }
  }
  ```
- [ ] Implement `State::load(): self` — reads `~/.opensignifo/state.json`
- [ ] Implement `State::getChangedFiles(string $projectName, array $scannedFiles): array`
  - Compare current `filemtime()` of each file against stored timestamp
  - Returns array of files whose mtime differs or that are new (not previously seen)
- [ ] Implement `State::updateTimestamps(string $projectName, array $scannedFiles): void`
  - Store current `filemtime()` for all files
  - Write back to `state.json` atomically
- [ ] First run (no prior state for a project) → treat all files as changed

**Acceptance:** After one `updateTimestamps` call, modifying one file and calling `getChangedFiles` returns only that one file.

---

## Phase 10 — CycleRouter.php

- [ ] Create `~/.opensignifo/app/CycleRouter.php`
- [ ] Implement `CycleRouter::selectProject(array $activeProjects): array`
  - Filter to `active: true` entries
  - Pick the project with the lowest `maintenance_token`
  - On tie, pick the one that appears first in the array (config.json order)
  - Return the selected project array entry
- [ ] Implement `CycleRouter::decideOption(string $projectRoot): string`
  - Count `*.md` files in `{projectRoot}/.opensignifo/suggestions/`
  - Return `'B'` if count < 3, `'C'` if count >= 3
- [ ] Ensure `suggestions/` and `reviews/` directories are created if they don't exist

**Acceptance:** Given two projects with tokens `[2, 0]`, `selectProject` returns the second one.

---

## Phase 11 — SuggestionWriter.php

Handles writing, naming, and rotating suggestion/review files.

- [ ] Create `~/.opensignifo/app/SuggestionWriter.php`
- [ ] Implement `SuggestionWriter::writeSuggestion(string $projectRoot, string $content): string`
  - Destination: `{projectRoot}/.opensignifo/suggestions/`
  - Filename: `suggestion-{YYYY-MM-DD}-{NNN}.md` where NNN is zero-padded next sequence number
  - Returns the full path written
- [ ] Implement `SuggestionWriter::writeReview(string $projectRoot, string $content): string`
  - Destination: `{projectRoot}/.opensignifo/reviews/`
  - Filename: `review-{YYYY-MM-DD}-{NNN}.md`
  - If `reviews/` already contains 10 `*.md` files → delete the oldest one first (by filename sort), log deletion
  - Returns the full path written
- [ ] Implement `SuggestionWriter::deleteSuggestion(string $filePath): void`
  - Delete the file, log `[B:1] {filename} → RESOLVED/IMPROVED (deleted)`
- [ ] File content format must match ARCHITECTURE §8 exactly (YAML front matter + three sections)
- [ ] All writes are atomic: write to `.tmp` then `rename()`

**Acceptance:** Writing 11 reviews results in exactly 10 files in `reviews/`; oldest removed.

---

## Phase 12 — OptionB.php — Deep Scan

- [ ] Create `~/.opensignifo/app/OptionB.php`
- [ ] Implement `OptionB::run(string $projectName, string $projectRoot, string $systemPrompt): void`

### Step B:1 — Review existing suggestions
- [ ] List all `*.md` files in `suggestions/`
- [ ] For each file, send its content + the referenced source file content to v4-flash
- [ ] Prompt: `"Has this issue been resolved or improved upon in the current file? Answer only: RESOLVED, IMPROVED, or PENDING."`
- [ ] `RESOLVED` or `IMPROVED` → call `SuggestionWriter::deleteSuggestion()`, log deletion
- [ ] `PENDING` → log `{filename} → PENDING (keeping)`

### Step B:2 — Bird's-eye scan
- [ ] Build file tree: array of `{file: relative_path, lines: N}` for all scanned files
- [ ] Send file tree (no content) to v4-flash
- [ ] Ask for JSON array: `[{ "file": "...", "reason": "..." }]` ranked by analysis priority
- [ ] Parse JSON response; log count of flagged files

### Step B:3 — Range scan
- [ ] For each flagged file (up to open suggestion slots remaining):
  - Send full file content to v4-flash
  - Ask: `"Return a JSON array of line ranges worth deep analysis: { line_ranges: [[start, end], ...] }. Max 300 lines total."`
  - Enforce 300-line cap: truncate ranges if needed
  - Log ranges found

### Step B:4 — Deep analysis
- [ ] For each set of ranges:
  - Extract only those lines from the file
  - Send to v4-pro with `$thinking = true`
  - Parse structured finding from response
  - Call `SuggestionWriter::writeSuggestion()` with formatted content
  - Log file written
  - Stop when `suggestions/` reaches 3 `*.md` files

**Acceptance:** On a real project with 1 existing suggestion, running Option B adds 1–2 more suggestions and logs all B:1–B:4 steps.

---

## Phase 13 — OptionC.php — Diff / Change-Driven Scan

- [ ] Create `~/.opensignifo/app/OptionC.php`
- [ ] Implement `OptionC::run(string $projectName, string $projectRoot, string $systemPrompt): void`

### Step C:1 — Detect changed files
- [ ] Call `State::getChangedFiles($projectName, $scannedFiles)`
- [ ] Call `State::updateTimestamps()` immediately after collecting changed files
- [ ] If no changed files → log `[IDLE] No changed files in {project}. Sleeping 60s.` and `sleep(60)`

### Step C:2 — Range scan
- [ ] For each changed file:
  - Send full file content to v4-flash
  - Ask for line ranges (same prompt as B:3, max 300 lines)
  - If flash returns empty ranges → log `no interesting ranges found` and skip

### Step C:3 — Deep analysis
- [ ] For each set of ranges:
  - Extract lines, send to v4-pro with `$thinking = true`
  - Call `SuggestionWriter::writeReview()` (auto-rotates at 10 files)
  - Log file written

**Acceptance:** Modifying one file, then running Option C produces exactly one review file and updates `state.json`.

---

## Phase 14 — main.php — Main Loop

Wire everything together into the continuous loop.

- [ ] Create `~/.opensignifo/app/main.php`
- [ ] Register `SIGINT` handler via `pcntl_signal()` (ARCHITECTURE §13):
  ```php
  pcntl_signal(SIGINT, function() {
      Logger::log('opensignifo stopped by user (Ctrl+C). Goodbye.');
      exit(0);
  });
  ```
- [ ] On startup:
  - [ ] Load `Config`
  - [ ] Log `opensignifo started. Watching N active projects.`
  - [ ] Log `Budget today: $X.XXX / $Y.YY`
- [ ] Main loop (infinite `while(true)`):
  - [ ] Call `pcntl_signal_dispatch()` at top of every iteration
  - [ ] Call `Budget::check($dailyLimit)` — exits if over budget
  - [ ] Call `CycleRouter::selectProject()` to pick the next project
  - [ ] Log `Project selected: {name} (token: N)`
  - [ ] Resolve `$projectRoot = $projectsRoot . '/' . $projectName`
  - [ ] Call `ProjectScanner::scan($projectRoot)`
  - [ ] Call `PromptBuilder::build($projectName, $projectRoot)` → `$systemPrompt`
  - [ ] Call `CycleRouter::decideOption($projectRoot)` → `'B'` or `'C'`
  - [ ] Log option decision
  - [ ] Dispatch to `OptionB::run()` or `OptionC::run()`
  - [ ] Increment `maintenance_token` for selected project in config, call `Config::save()`
  - [ ] Log `maintenance_token for {project} → N`
  - [ ] Log `---`
  - [ ] Continue loop (no artificial sleep between projects — Option C idles internally)

**Acceptance:** Agent runs, selects projects in round-robin by token, processes B then C correctly, Ctrl+C exits cleanly.

---

## Phase 15 — Install Script

- [ ] Create `install.sh` in the repo root
- [ ] Script must:
  - [ ] Copy `app/` source files to `~/.opensignifo/app/`
  - [ ] Create `~/.opensignifo/projects/` if absent
  - [ ] Write starter `~/.opensignifo/OPENSIGNIFO.md` if none exists (never overwrite)
  - [ ] Write starter `~/.opensignifo/config.json` if none exists (never overwrite)
  - [ ] Write empty `budget.json` and `state.json` if they don't exist
  - [ ] Install `/usr/local/bin/opensignifo` (requires sudo or PATH-local bin dir)
  - [ ] Print setup instructions for `DEEPSEEK_API_KEY`
- [ ] Make `install.sh` idempotent — safe to run multiple times

**Acceptance:** Running `install.sh` on a clean machine produces a working `opensignifo` command.

---

## Phase 16 — End-to-End Smoke Test

Final validation before the project is considered shippable.

- [ ] Set up a minimal test project directory with a few PHP/JS files
- [ ] Configure `config.json` with that project as `active: true`
- [ ] Run `opensignifo` for 2–3 full cycles
- [ ] Verify:
  - [ ] Option B runs when `suggestions/` has < 3 files
  - [ ] Option C runs when `suggestions/` has >= 3 files
  - [ ] `maintenance_token` increments in `config.json` after each cycle
  - [ ] Budget is tracked correctly in `budget.json`
  - [ ] `state.json` correctly reflects file mtimes
  - [ ] Log output matches ARCHITECTURE §11 format
  - [ ] Ctrl+C exits cleanly with goodbye message
  - [ ] Suggestion files have correct YAML front matter (ARCHITECTURE §8)

---

## Cross-Cutting Requirements (all phases)

- **Atomic writes everywhere:** All JSON and Markdown writes use `.tmp` + `rename()` — no partial files on crash.
- **No parallel API calls:** Calls are strictly sequential (ARCHITECTURE §14).
- **Error handling:** Network/API failures log the error, sleep 30s, retry once, then skip and continue.
- **No external dependencies:** Pure PHP stdlib — no Composer packages.
- **PHP version:** Target PHP 8.1+ (`filemtime`, `pcntl_signal`, named args, etc. all available).
- **Log format:** Every log line must match `[HH:MM:SS] message` — no exceptions.
