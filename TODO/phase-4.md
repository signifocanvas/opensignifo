## Phase 4 — DeepSeekClient.php

The API wrapper. All calls in the whole system go through this one class.

- [ ] Create `app/DeepSeekClient.php`
- [ ] Store pricing constants exactly as in ARCHITECTURE §10:
  ```php
  const V4_FLASH_INPUT_CACHE_HIT  = 0.028;
  const V4_FLASH_INPUT_CACHE_MISS = 0.14;
  const V4_FLASH_OUTPUT           = 0.28;
  const V4_PRO_INPUT_CACHE_HIT    = 0.145;
  const V4_PRO_INPUT_CACHE_MISS   = 1.74;
  const V4_PRO_OUTPUT             = 3.48;
  ```
- [ ] Implement `DeepSeekClient::call(string $model, string $system, string $user, bool $thinking = false): array`
  - `$model` is `'v4-flash'` or `'v4-pro'`
  - `$thinking = true` enables medium thinking (v4-pro only)
  - Returns `['content' => string, 'usage' => [...], 'cost' => float]`
- [ ] Read API key from environment variable `DEEPSEEK_API_KEY`; exit with error if missing
- [ ] Log `Calling {model}...` before the call, log elapsed seconds after
- [ ] Use `prompt_cache_hit_tokens` from the response to split input cost correctly:
  ```
  cost = (cache_hit_tokens / 1M * HIT_PRICE)
       + ((total_input - cache_hit_tokens) / 1M * MISS_PRICE)
       + (output_tokens / 1M * OUTPUT_PRICE)
  ```
- [ ] Call `Budget::check()` before every API call
- [ ] Call `Budget::record($cost)` after every successful API call
- [ ] Implement retry logic (ARCHITECTURE §14):
  - On network error / 500 / rate-limit → log the error, sleep 30s, retry once
  - If second attempt also fails → log and return `null` (caller skips this task)
- [ ] Test with a minimal real call to confirm auth and pricing work

**Acceptance:** A single `v4-flash` call returns a non-empty string and correctly updates `budget.json`.
