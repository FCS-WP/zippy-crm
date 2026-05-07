# Rule: Performance — Where It Actually Matters

WordPress sites get slow from a small set of recurring causes. Optimize *those*. Don't micro-optimize trivial code — that's a waste of time and adds abstraction you'll regret.

## Where performance bites in a WP/WC site

| Layer | Top causes | What to do |
|-------|-----------|------------|
| **DB** | N+1 queries, missing indexes, full-table scans, queries inside loops | Batch, JOIN, index the `WHERE`/`ORDER BY` columns |
| **Object cache** | Hot reads not cached; cache not invalidated → stale data | Cache on read, invalidate on write — both, always |
| **Cron** | Long-running cron firing on every uncached page load | Real system cron + idempotent batches |
| **Frontend bundle** | Shipping unused vendor code; one giant bundle | Code-split per entry; lazy-load heavy widgets |
| **REST round-trips** | Component fires 3 separate GETs on mount | One endpoint that returns the composite payload |
| **Admin pages** | Synchronous slow query blocking page render | Defer to REST + skeleton; or paginate hard |

## Rules — DB

1. **Never query inside a loop.** If you're tempted to `foreach ($users as $u) { $wpdb->get_row(...) }`, write one query with `WHERE user_id IN (…)` and an index. This is the #1 source of WP slowness.
2. **Index the columns you filter or sort by.** Every `WHERE x = ?` on a table > ~10K rows needs an index on `x`. Composite indexes for combined filters (see CLAUDE.md `idx_user_created` example).
3. **Use `SELECT only_what_you_need`.** No `SELECT *` on wide tables in hot paths.
4. **Use `EXPLAIN` for any query in a customer-facing path.** If it's not using an index, fix the index or the query.
5. **`crm_points_summary` exists exactly to avoid `SUM(points) FROM crm_points_ledger`** on every page render. Always read the summary; recalculate only when the ledger changes.
6. **Pagination uses `LIMIT/OFFSET` only up to ~10K offset.** Past that, switch to keyset pagination (`WHERE id < last_seen_id`).

## Rules — Caching (object cache)

1. **Cache hot reads.** Anything called on every page load by every logged-in user (membership lookup, points summary) goes through `Support/Cache`.
2. **Invalidate on write.** Every service method that writes must invalidate the keys it affects. Stale cache is worse than no cache.
3. **Use a versioned key prefix.** `zc:v1:points:summary:{user_id}` — bumping `v1` to `v2` is a global flush.
4. **TTL ≠ invalidation.** TTLs are a safety net for missed invalidations, not the primary mechanism. Default TTL: 1 hour.
5. **Don't cache user-input-derived keys without a whitelist** — easy DoS vector.

## Rules — Cron / batch jobs

1. **Batches are idempotent.** Running the same batch twice must not double-send. The `crm_notification_log UNIQUE` already enforces this — *use it*, don't bypass it.
2. **Batch size cap.** Default 50 (per `ZIPPY_CRM_EMAIL_BATCH_SIZE`). Don't process all-at-once.
3. **No `sleep()` or long loops in the request lifecycle.** Schedule a cron event instead.
4. **Cron runs that hit `wp_schedule_single_event` need a way to verify they fired** — log batch IDs to `crm_notification_log` so we can audit.

## Rules — Frontend

1. **Two bundles only:** `admin.js` (loads on Zippy CRM admin pages) and `account.js` (loads on My Account CRM tabs). Never on every page.
2. **Conditional enqueue.** `Assets::enqueue_account` checks `is_account_page()` AND the current endpoint is one of ours.
3. **Lazy-load heavy widgets** (charts, rich text editors). `import()` on first user interaction, not on mount.
4. **Compose REST calls.** A page that needs membership + points + voucher count = one `/account/dashboard` endpoint, not three.
5. **React Query default `staleTime: 30_000`.** Avoids refetching on every component mount.

## Rules — Hooks

1. **Hook callbacks are hot.** `woocommerce_order_status_completed` fires on every completion — no slow work inline. Defer heavy work to a single-event cron.
2. **Run conditional logic before doing work.** `if (! $order->get_customer_id()) return;` at the top — bail out of guest orders before any DB read.
3. **Don't add hooks at file-load time inside loops.** One `add_action` per registration class.

## What NOT to optimize

- Microsecond-level array iteration on a 20-element array.
- Replacing `foreach` with `array_map` for "performance" — they're the same.
- String concatenation vs interpolation.
- Adding a cache layer to a query that runs once per admin page load.

If you're not sure whether something is hot, **measure first** (`Query Monitor` plugin, `microtime(true)`, browser Network tab). Don't guess.

## Required performance check before shipping a feature

For every feature, before saying "done":

- [ ] No DB query inside a loop in any code path
- [ ] Every `WHERE`/`ORDER BY` column on a custom table has an index
- [ ] Hot reads route through `Support/Cache`, and writes invalidate
- [ ] Conditional enqueue — frontend bundles don't load globally
- [ ] No new synchronous external HTTP calls in a request lifecycle
- [ ] If something looks slow, **run Query Monitor** before optimizing — don't guess
