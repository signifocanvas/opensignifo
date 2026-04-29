## Phase 5 — PromptBuilder.php

Assembles the stable system prompt once per project per session.

- [ ] Create `app/PromptBuilder.php`
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
