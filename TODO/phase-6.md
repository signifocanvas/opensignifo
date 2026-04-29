## Phase 6 — GitIgnore.php

- [ ] Create `app/GitIgnore.php`
- [ ] Implement `GitIgnore::load(string $projectRoot): self` — reads `{projectRoot}/.gitignore` if it exists
- [ ] Implement `GitIgnore::matches(string $relativePath): bool`
  - Supports glob patterns (`*.log`, `build/`, `vendor/`)
  - Supports negation rules (`!important.log`)
  - Only root-level `.gitignore` is respected (no recursive parsing)
- [ ] Handle missing `.gitignore` gracefully (treat as no rules)

**Acceptance:** Given a `.gitignore` with `vendor/`, `GitIgnore::matches('vendor/autoload.php')` returns `true`.
