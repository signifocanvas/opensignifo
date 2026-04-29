## Phase 3 — Budget.php

Budget enforcement must run before every API call.

- [ ] Create `app/Budget.php`
- [ ] Implement `Budget::check(float $dailyLimit): void`
  - Read `~/.opensignifo/budget.json`
  - Get today's date as `YYYY-MM-DD`
  - If today's `spent_usd >= $dailyLimit` → `Logger::log('[BUDGET] Daily limit of $X reached. Exiting.')` then `exit(0)`
- [ ] Implement `Budget::record(float $cost): void`
  - Read current `budget.json`
  - Add `$cost` to today's `spent_usd`, increment today's `calls` counter
  - Write back atomically (`.tmp` + `rename()`)
- [ ] Implement `Budget::todaySpent(): float` — returns today's running total
- [ ] Log current spend at startup: `Budget today: $X.XXX / $Y.YY`

**Acceptance:** After 3 simulated `Budget::record(0.10)` calls on a $0.25 limit, the 4th `Budget::check()` exits.
