## Phase 15 — End-to-End Smoke Test

Final validation before the project is considered shippable.

- [ ] Run `install.sh` on a clean machine (or clean `~/.opensignifo/`)
- [ ] Set `DEEPSEEK_API_KEY` in the environment
- [ ] Edit `~/.opensignifo/config.json` to point at a minimal test project with a few PHP/JS files
- [ ] Run `opensignifo` for 2–3 full cycles
- [ ] Verify:
  - [ ] Option B runs when `suggestions/` has < 3 files
  - [ ] Option C runs when `suggestions/` has >= 3 files
  - [ ] `maintenance_token` increments in `config.json` after each cycle
  - [ ] Budget is tracked correctly in `budget.json`
  - [ ] `state.json` correctly reflects file mtimes
  - [ ] Log output matches ARCHITECTURE §11 format exactly
  - [ ] Ctrl+C exits cleanly with goodbye message
  - [ ] Suggestion files have correct YAML front matter (ARCHITECTURE §8)
