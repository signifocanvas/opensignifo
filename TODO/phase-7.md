## Phase 7 — ProjectScanner.php

- [ ] Create `app/ProjectScanner.php`
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
