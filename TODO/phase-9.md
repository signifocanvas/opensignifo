## Phase 9 — CycleRouter.php

- [ ] Create `app/CycleRouter.php`
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
