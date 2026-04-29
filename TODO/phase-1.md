## Phase 1 — Logger.php

Build console output first — every subsequent phase depends on being able to see what's happening.

- [ ] Create `app/Logger.php`
- [ ] Implement `Logger::log(string $message): void`
  - Prefix every line with `[HH:MM:SS]` using `date('H:i:s')`
  - Write to `STDOUT` (not `STDERR`)
  - Flush immediately (no output buffering)
- [ ] Verify format matches ARCHITECTURE §11 exactly:
  ```
  [10:42:00] opensignifo started. Watching 2 active projects.
  ```

**Acceptance:** `Logger::log('hello')` prints `[HH:MM:SS] hello` and returns.
