## Phase 10 — SuggestionWriter.php

Handles writing, naming, and rotating suggestion/review files.

- [ ] Create `app/SuggestionWriter.php`
- [ ] Implement `SuggestionWriter::writeSuggestion(string $projectRoot, string $content): string`
  - Destination: `{projectRoot}/.opensignifo/suggestions/`
  - Filename: `suggestion-{YYYY-MM-DD}-{NNN}.md` where NNN is zero-padded next sequence number
  - Returns the full path written
- [ ] Implement `SuggestionWriter::writeReview(string $projectRoot, string $content): string`
  - Destination: `{projectRoot}/.opensignifo/reviews/`
  - Filename: `review-{YYYY-MM-DD}-{NNN}.md`
  - If `reviews/` already contains 10 `*.md` files → delete the oldest one first (by filename sort), log deletion
  - Returns the full path written
- [ ] Implement `SuggestionWriter::deleteSuggestion(string $filePath): void`
  - Delete the file, log `[B:1] {filename} → RESOLVED/IMPROVED (deleted)`
- [ ] File content format must match ARCHITECTURE §8 exactly (YAML front matter + three sections)
- [ ] All writes are atomic: write to `.tmp` then `rename()`

**Acceptance:** Writing 11 reviews results in exactly 10 files in `reviews/`; oldest removed.
