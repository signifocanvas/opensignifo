## Phase 11 — OptionB.php — Deep Scan

- [ ] Create `app/OptionB.php`
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
