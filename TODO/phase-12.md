## Phase 12 — OptionC.php — Diff / Change-Driven Scan

- [ ] Create `app/OptionC.php`
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
