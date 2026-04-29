## Phase 8 — State.php

Tracks file modification times so Option C can detect changes.

- [ ] Create `app/State.php`
- [ ] Data structure in `~/.opensignifo/state.json`:
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
