## Phase 13 — main.php — Main Loop

Wire everything together into the continuous loop.

- [ ] Create `app/main.php`
- [ ] Register `SIGINT` handler via `pcntl_signal()` (ARCHITECTURE §13):
  ```php
  pcntl_signal(SIGINT, function() {
      Logger::log('opensignifo stopped by user (Ctrl+C). Goodbye.');
      exit(0);
  });
  ```
- [ ] On startup:
  - [ ] Load `Config`
  - [ ] Log `opensignifo started. Watching N active projects.`
  - [ ] Log `Budget today: $X.XXX / $Y.YY`
- [ ] Main loop (infinite `while(true)`):
  - [ ] Call `pcntl_signal_dispatch()` at top of every iteration
  - [ ] Call `Budget::check($dailyLimit)` — exits if over budget
  - [ ] Call `CycleRouter::selectProject()` to pick the next project
  - [ ] Log `Project selected: {name} (token: N)`
  - [ ] Resolve `$projectRoot = $projectsRoot . '/' . $projectName`
  - [ ] Call `ProjectScanner::scan($projectRoot)`
  - [ ] Call `PromptBuilder::build($projectName, $projectRoot)` → `$systemPrompt`
  - [ ] Call `CycleRouter::decideOption($projectRoot)` → `'B'` or `'C'`
  - [ ] Log option decision
  - [ ] Dispatch to `OptionB::run()` or `OptionC::run()`
  - [ ] Increment `maintenance_token` for selected project in config, call `Config::save()`
  - [ ] Log `maintenance_token for {project} → N`
  - [ ] Log `---`
  - [ ] Continue loop (no artificial sleep between projects — Option C idles internally)

**Acceptance:** Agent runs, selects projects in round-robin by token, processes B then C correctly, Ctrl+C exits cleanly.
