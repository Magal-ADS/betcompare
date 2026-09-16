---
paths:
  - 'app/{Http/Controllers,Jobs}/**'
---

# Controllers Jobs

## Run manual odds refreshes through the unique queue job
The refresh HTTP request must enqueue the unique CollectOdds job and return immediately. Keep the atomic pending marker, one job attempt, worker timeout below database retry_after, and never retry HTTP 429 responses to bypass source limits.
