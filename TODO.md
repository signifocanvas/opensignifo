# opensignifo — Implementation Roadmap

This document is the authoritative task list for building opensignifo from scratch.
Each phase maps directly to a section in `ARCHITECTURE.md`. Complete phases in order —
later phases depend on earlier ones.

The repo ships source code. Everything that touches `~/.opensignifo/` or
`/usr/local/bin/` on the user's machine is the responsibility of `install.sh` (Phase 14),
not of the developer directly.

Repo layout produced by this roadmap:

```
opensignifo/
├── ARCHITECTURE.md
├── LICENSE
├── TODO.md
├── install.sh
└── app/
    ├── main.php
    ├── Logger.php
    ├── Config.php
    ├── Budget.php
    ├── DeepSeekClient.php
    ├── PromptBuilder.php
    ├── GitIgnore.php
    ├── ProjectScanner.php
    ├── State.php
    ├── CycleRouter.php
    ├── SuggestionWriter.php
    ├── OptionB.php
    └── OptionC.php
```

---

## Phases

- [Phase 1— Logger.php](TODO/phase-1.md)
- [Phase 2— Config.php](TODO/phase-2.md)
- [Phase 3— Budget.php](TODO/phase-3.md)
- [Phase 4— DeepSeekClient.php](TODO/phase-4.md)
- [Phase 5— PromptBuilder.php](TODO/phase-5.md)
- [Phase 6— GitIgnore.php](TODO/phase-6.md)
- [Phase 7— ProjectScanner.php](TODO/phase-7.md)
- [Phase 8— State.php](TODO/phase-8.md)
- [Phase 9— CycleRouter.php](TODO/phase-9.md)
- [Phase 10— SuggestionWriter.php](TODO/phase-10.md)
- [Phase 11— OptionB.php — Deep Scan](TODO/phase-11.md)
- [Phase 12— OptionC.php — Diff / Change-Driven Scan](TODO/phase-12.md)
- [Phase 13— main.php — Main Loop](TODO/phase-13.md)
- [Phase 14— install.sh](TODO/phase-14.md)
- [Phase 15— End-to-End Smoke Test](TODO/phase-15.md)

---

## Cross-Cutting Requirements (all phases)

- **Atomic writes everywhere:** All JSON and Markdown writes use `.tmp` + `rename()` — no partial files on crash.
- **No parallel API calls:** Calls are strictly sequential (ARCHITECTURE §14).
- **Error handling:** Network/API failures log the error, sleep 30s, retry once, then skip and continue.
- **No external dependencies:** Pure PHP stdlib — no Composer packages.
- **PHP version:** Target PHP 8.1+ (`filemtime`, `pcntl_signal`, named args, etc. all available).
- **Log format:** Every log line must match `[HH:MM:SS] message` — no exceptions.
