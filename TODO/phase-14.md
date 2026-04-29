## Phase 14 — install.sh

The install script is the only place that touches the user's machine. All local setup lives here.

- [ ] Create `install.sh` in the repo root
- [ ] Script must perform the following steps, in order:

  **Directory setup**
  - [ ] Create `~/.opensignifo/app/` if it doesn't exist
  - [ ] Create `~/.opensignifo/projects/` if it doesn't exist

  **Copy source files**
  - [ ] Copy all files from `app/` in the repo → `~/.opensignifo/app/` (overwrite on upgrade)

  **Write starter data files (never overwrite existing)**
  - [ ] Write `~/.opensignifo/OPENSIGNIFO.md` with a default global agent prompt — skip if already present
  - [ ] Write `~/.opensignifo/config.json` with the example structure from ARCHITECTURE §3 — skip if already present
  - [ ] Write `~/.opensignifo/budget.json` as `{}` — skip if already present
  - [ ] Write `~/.opensignifo/state.json` as `{}` — skip if already present

  **Install CLI wrapper**
  - [ ] Write `/usr/local/bin/opensignifo`:
    ```bash
    #!/bin/bash
    php ~/.opensignifo/app/main.php "$@"
    ```
  - [ ] `chmod +x /usr/local/bin/opensignifo`

  **Post-install message**
  - [ ] Print: `opensignifo installed. Set DEEPSEEK_API_KEY in your shell profile, then run: opensignifo`

- [ ] Make the entire script idempotent — safe to run again on upgrade without losing user data

**Acceptance:** Running `install.sh` on a clean machine + running it a second time both succeed. User data files are not overwritten on the second run.
