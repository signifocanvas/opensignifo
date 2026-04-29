# opensignifo — Architecture Specification

---

## 1. Overview

`opensignifo` is a PHP CLI agent that continuously monitors a set of PHP/JS/HTML projects and writes improvement suggestions into each project's `.opensignifo/` folder. It uses DeepSeek's API (v4-flash for lightweight tasks, v4-pro for deep analysis) and runs in a single terminal tab until Ctrl+C.

---

## 2. Installation Layout

```
~/.opensignifo/
├── config.json              # User configuration
├── budget.json              # Daily spend tracker (auto-managed)
├── state.json               # File mtimes, last-scan timestamps (auto-managed)
└── app/
    ├── main.php             # Entry point — the main loop
    ├── Config.php           # Loads and validates config.json
    ├── Budget.php           # Budget tracking and enforcement
    ├── State.php            # Persists file mtimes and project scan state
    ├── ProjectScanner.php   # Discovers files, respects .gitignore
    ├── GitIgnore.php        # Parses root-level .gitignore rules
    ├── CycleRouter.php      # Decides Option B vs Option C per project
    ├── OptionB.php          # Deep scan: flash bird's-eye → pro on ranges
    ├── OptionC.php          # Diff scan: changed files → flash ranges → pro
    ├── SuggestionWriter.php # Writes/updates/deletes .opensignifo/ md files
    ├── DeepSeekClient.php   # All API calls, token counting, cache awareness
    └── Logger.php           # Console log output (timestamped, like php -s)
```

The `opensignifo` command lives at `/usr/local/bin/opensignifo`:

```bash
#!/bin/bash
php ~/.opensignifo/app/main.php "$@"
```

---

## 3. config.json example

```json
{
  "projects_root": "~/Projects/",
  "daily_budget_usd": 0.50,
  "projects": [
    { "name": "project-1", "active": true, "maintenance_token": 0 },
    { "name": "project-2", "active": true, "maintenance_token": 0 },
    { "name": "project-3", "active": false, "maintenance_token": 0 }
  ]
}
```

- **Priority order** = top-to-bottom in the `projects` array
- **`active: false`** = agent skips the project entirely
- **`maintenance_token`** = cumulative lifetime counter of how many cycles (B or C) have been run on this project. Written back to config.json after each cycle. Used to balance attention: among equally-prioritized active projects, the one with the lowest token gets picked next.

---

## 4. Project Folder Structure (per project) example

```
~/Projects/project-1/
├── .gitignore                        # Only root-level is respected
├── .opensignifo/
│   ├── suggestions/                  # Option B output — max 3 *.md files
│   │   ├── suggestion-2026-04-29-001.md
│   │   └── suggestion-2026-04-29-002.md
│   └── reviews/                      # Option C output — max 10 *.md files
│       ├── review-2026-04-29-001.md
│       └── ...
├── docs/
├── sql/
├── src/
├── tests/
├── CLAUDE.md
├── GEMINI.md
├── AGENTS.md
└── public_html/
    └── ...  (your project files)
```

**Key rule:** The agent counts **all `*.md` files** in `suggestions/` (not just `suggestion-*.md`). This allows you to place any dummy `.md` file there to manually cap Option B and force Option C.

---

## 5. Cycle Router Logic

This runs once per project per loop iteration:

```
Count *.md files in {project}/.opensignifo/suggestions/

IF count < 3:
    → Run Option B (deep scan)

IF count >= 3:
    → Run Option C (diff/change scan)

After either option completes:
    → Increment maintenance_token for this project in config.json
    → Log completion
```

**Project selection order per loop iteration:**

1. Filter to `active: true` projects only
2. Among those, pick the one with the **lowest `maintenance_token`**
3. On a tie, use **config.json order** (top = higher priority)
4. Only one project is processed per loop iteration — the agent is a single person

---

## 6. Option B — Deep Scan

Triggered when `suggestions/` has fewer than 3 `*.md` files.

### Step 1 — Review existing suggestions (v4-flash, non-thinking)

For each existing `*.md` file in `suggestions/`:
- Send the suggestion content + the current content of the file it references to v4-flash
- Ask: "Has this issue been resolved or improved upon in the current file? Answer only: RESOLVED, IMPROVED, or PENDING."
- If `RESOLVED` or `IMPROVED` → delete the suggestion file, log deletion
- If `PENDING` → keep it, skip re-generating for that file

### Step 2 — Bird's-eye scan (v4-flash, non-thinking)

Build a **stable system prompt** for the project (see Section 9 on caching). Send the full file tree (names + line counts only, no content) to v4-flash. Ask it to return a JSON array of the most interesting files to analyze for bugs, refactoring, doc inconsistencies, outdated deps, or missing tests. Ranked by priority.

```json
[
  { "file": "src/Controllers/InvoiceController.php", "reason": "complex controller, likely bug surface" },
  { "file": "composer.json", "reason": "check for outdated packages" }
]
```

### Step 3 — Range scan (v4-flash, non-thinking)

For each file returned by Step 2 (up to the number of open suggestion slots):
- Send the full file content to v4-flash
- Ask: "Return a JSON array of line ranges worth deep analysis: `{ line_ranges: [[start, end], ...] }`. Max 300 lines total across all ranges."
- Respect the 300-line total cap

### Step 4 — Deep analysis (v4-pro, medium thinking)

For each set of line ranges from Step 3:
- Send only those extracted lines to v4-pro with the stable system prompt
- Ask for a structured finding: bug / refactor / doc inconsistency / outdated dep / missing test
- Write one `suggestion-{date}-{NNN}.md` file per finding

### Step 4 stops when `suggestions/` reaches 3 `*.md` files.

---

## 7. Option C — Diff / Change-Driven Scan

Triggered when `suggestions/` already has 3 `*.md` files.

### Step 1 — Detect changed files

Compare current `filemtime()` of all scannable files against the timestamps stored in `state.json`. Collect all files modified since last scan. Update `state.json` timestamps after collecting.

### Step 2 — Range scan (v4-flash, non-thinking)

For each changed file:
- Same as Option B Step 3: ask flash for interesting line ranges (max 300 lines total)
- If flash returns empty ranges → skip the file, log "no interesting ranges found"

### Step 3 — Deep analysis (v4-pro, medium thinking)

For each set of ranges:
- Send to v4-pro with stable system prompt
- Write one `review-{date}-{NNN}.md` per finding to `reviews/`
- If `reviews/` already has 10 files → delete the oldest one first, log deletion

### If no files have changed:

Log `[IDLE] No changed files in {project}. Sleeping 60s.` and sleep. This is the normal steady-state when the agent has been running a while.

---

## 8. Suggestion / Review File Format

```markdown
---
project: project-1
file: src/Controllers/InvoiceController.php
lines: 87-112
type: bug | refactor | doc | dependency | test
severity: low | medium | high
generated: 2026-04-29T10:42:01
model: deepseek-v4-pro
---

## Finding

[Clear description of the issue]

## Why It Matters

[Brief impact explanation]

## Suggested Fix

[Concrete code or action suggestion]
```

---

## 9. Cache Optimization Strategy

DeepSeek caches the **prefix** of a prompt if it is identical across calls. Cache hits cost 1/10 the input token price.

**Rule:** Every API call for a given project starts with an identical stable system prompt. Only the file content appended at the end changes per call.

The stable system prompt per project is built once at startup and stored in memory:

```
You are a code quality agent for the project "{name}".
Stack: vanilla PHP (custom MVC), vanilla JS, Tailwind CSS v4, HTML.
Scannable file types: .php .js .html .css .json .txt .neon .sh .sql
Test tools: PHPUnit, Jest, Cypress, PHPStan, PHP-CS-Fixer.
Each repo is independent and deployed on Hostinger shared hosting.
Do not suggest new features. Focus only on: bugs, refactors,
documentation inconsistencies, outdated dependencies, and missing/broken tests.
```

This prefix is identical for every call within a project → maximizes cache hits. The file content is appended after this prompt every time.

---

## 10. Budget Tracking

### budget.json (auto-managed)

```json
{
  "2026-04-29": {
    "spent_usd": 0.021,
    "calls": 4
  }
}
```

### Before every API call:

1. Get today's date
2. Read `budget.json`
3. If `spent_usd` for today >= `daily_budget_usd` in config → log `[BUDGET] Daily limit of $0.50 reached. Exiting.` and exit cleanly (no error, just `exit(0)`)
4. After every API call → compute cost from `usage.prompt_tokens` + `usage.completion_tokens` using current pricing, add to today's total, write back to `budget.json`

### Pricing constants (in DeepSeekClient.php)

```php
// Per 1M tokens
const V4_FLASH_INPUT_CACHE_HIT  = 0.028;
const V4_FLASH_INPUT_CACHE_MISS = 0.14;
const V4_FLASH_OUTPUT           = 0.28;

const V4_PRO_INPUT_CACHE_HIT    = 0.145;
const V4_PRO_INPUT_CACHE_MISS   = 1.74;  // 75% discount active until 2026-05-31
const V4_PRO_OUTPUT             = 3.48;
```

The API response includes a `prompt_cache_hit_tokens` field — use this to split the input cost correctly between cache-hit and cache-miss tokens.

---

## 11. Console Log Format

Exactly like `php -s localhost:8000` — timestamped, one action per line, always flowing:

```
[10:42:00] opensignifo started. Watching 2 active projects.
[10:42:00] Budget today: $0.000 / $0.50
[10:42:01] Project selected: acc-signifocanvas-com (token: 0)
[10:42:01] suggestions/ has 2 file(s). Running Option B.
[10:42:02] [B:1] Reviewing existing suggestion: suggestion-2026-04-28-001.md
[10:42:04] [B:1] suggestion-2026-04-28-001.md → PENDING (keeping)
[10:42:04] [B:2] Bird's-eye scan via v4-flash...
[10:42:06] [B:2] 3 files flagged for deep analysis.
[10:42:06] [B:3] Range scan: app/Controllers/InvoiceController.php
[10:42:08] [B:3] Ranges: lines 87-142, 201-230 (85 lines total)
[10:42:08] [B:4] Deep analysis via v4-pro (medium)...
[10:42:21] [B:4] Writing ~/.../suggestions/suggestion-2026-04-29-001.md
[10:42:21] Budget used today: $0.018 / $0.50
[10:42:21] suggestions/ now full (3 files). Option B complete.
[10:42:22] maintenance_token for acc-signifocanvas-com → 1
[10:42:22] ---
[10:42:22] Project selected: inv-signifocanvas-com (token: 0)
[10:42:22] suggestions/ has 3 file(s). Running Option C.
[10:42:23] [C:1] Scanning for changed files...
[10:42:23] [C:1] 2 changed files found.
[10:42:23] [C:2] Range scan: src/Models/Product.php (v4-flash)
[10:42:25] [C:2] Ranges: lines 44-91 (48 lines)
[10:42:25] [C:3] Deep analysis via v4-pro (medium)...
[10:42:38] [C:3] Writing ~/.../reviews/review-2026-04-29-001.md
[10:42:38] Budget used today: $0.031 / $0.50
[10:42:39] maintenance_token for inv-signifocanvas-com → 1
[10:42:39] ---
[10:43:39] [IDLE] No changed files in acc-signifocanvas-com. Sleeping 60s.
```

---

## 12. Scannable File Types

```
*.php  *.js  *.html  *.css  *.json  *.txt  *.neon  *.sh *.sql
```

Always skip:
- Any path matching root-level `.gitignore` rules
- The `.opensignifo/` folder itself
- `node_modules/`, `vendor/` (also typically in .gitignore, but skip unconditionally)

---

## 13. Ctrl+C Handling

```php
pcntl_signal(SIGINT, function() {
    Logger::log('opensignifo stopped by user (Ctrl+C). Goodbye.');
    exit(0);
});
```

Call `pcntl_signal_dispatch()` inside the main loop on each iteration to process signals.

---

## 14. API Call Discipline

- **One call at a time, always.** No async, no parallel requests.
- Every call is synchronous — wait for full response before proceeding.
- Log `Calling {model}...` before the call, log response time after.
- If a call fails (network error, 500, rate limit) → log the error, sleep 30s, retry once. If it fails again → skip this file/task and continue to the next.

---

## 15. Build Order (Recommended)

1. `Logger.php` — get console output working first
2. `Config.php` + `config.json` — load and validate
3. `Budget.php` + `budget.json` — budget check before any API calls
4. `DeepSeekClient.php` — test a single v4-flash call
5. `GitIgnore.php` + `ProjectScanner.php` — file discovery
6. `State.php` + `state.json` — mtime tracking
7. `CycleRouter.php` — B vs C decision
8. `OptionB.php` — full Option B pipeline
9. `OptionC.php` — full Option C pipeline
10. `SuggestionWriter.php` — write/delete suggestion and review files
11. `main.php` — wire everything into the main loop
12. `/usr/local/bin/opensignifo` — install the command
