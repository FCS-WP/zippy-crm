# Rule: SQL Lives in `.sql` Files

## Why

- Inline SQL in PHP is unreadable past ~5 lines, breaks syntax highlighting, and hides query complexity from reviewers.
- A query in a named `.sql` file is greppable, diffable, and reusable across services.
- DB analysts and DBAs can read `.sql` files without touching PHP.

## Layout

```
src/Database/
├── Schema/                        # CREATE TABLE / ALTER TABLE for installer
│   ├── crm_memberships.sql
│   ├── crm_points_ledger.sql
│   ├── crm_points_summary.sql
│   ├── crm_vouchers.sql
│   ├── crm_voucher_claims.sql
│   ├── crm_notif_subs.sql
│   └── crm_notification_log.sql
├── Queries/                       # Runtime queries used by services/repositories
│   ├── points/
│   │   ├── get_summary.sql
│   │   ├── get_ledger_paginated.sql
│   │   └── recalculate_balance.sql
│   ├── vouchers/
│   │   ├── list_active_unclaimed.sql
│   │   └── increment_uses.sql
│   └── notifications/
│       └── select_unsent_subscribers.sql
└── QueryLoader.php                # Loads + caches the .sql file, applies $wpdb->prefix
```

## Pattern

Each `.sql` file uses **named placeholders** (we substitute them before passing to `$wpdb->prepare()`):

```sql
-- Queries/points/get_ledger_paginated.sql
SELECT id, type, points, description, order_id, created_at
FROM   {prefix}crm_points_ledger
WHERE  user_id = %d
ORDER  BY created_at DESC
LIMIT  %d OFFSET %d;
```

Loader returns the prepared SQL string with `{prefix}` replaced by `$wpdb->prefix`:

```php
final class QueryLoader {
    public static function load( string $relative ): string {
        static $cache = [];
        if ( isset( $cache[ $relative ] ) ) {
            return $cache[ $relative ];
        }
        $path = ZIPPY_CRM_DIR . 'src/Database/Queries/' . $relative;
        $sql  = (string) file_get_contents( $path );
        global $wpdb;
        $cache[ $relative ] = str_replace( '{prefix}', $wpdb->prefix, $sql );
        return $cache[ $relative ];
    }
}
```

Usage:

```php
global $wpdb;
$sql  = QueryLoader::load( 'points/get_ledger_paginated.sql' );
$rows = $wpdb->get_results( $wpdb->prepare( $sql, $user_id, $per_page, $offset ) );
```

## Rules

1. **Always use `$wpdb->prepare()`** with the loaded SQL. Loader returns raw SQL with placeholders — it does NOT escape values.
2. **Never interpolate `$wpdb->prefix` inside the SQL file** — use `{prefix}` so the loader can swap it. This keeps the file portable and lintable.
3. **Never interpolate user input into the SQL file** — values are always passed via `$wpdb->prepare(... %s ... %d ...)`.
4. **Schema files** (`Schema/*.sql`) are for `dbDelta()` — they do NOT need `prepare()`, but they MUST keep the `{prefix}` placeholder.
5. **One query per file**, named after what it does (`get_*`, `insert_*`, `update_*`, `count_*`). No `query1.sql`.
6. **Comments on top** of every `.sql` file: what it returns, what indexes it uses, expected row count order of magnitude.
7. **Trivial one-liners are an exception** — `SELECT * FROM {prefix}crm_memberships WHERE user_id = %d` can stay inline if it's truly a single line and used once. The moment it has a JOIN, a subquery, or 3+ columns, move it.

## Anti-patterns

- ❌ Heredoc SQL inside a PHP method
- ❌ Building SQL by string concatenation across multiple `if` branches → instead, write distinct `.sql` files per branch
- ❌ Loader that does `file_get_contents` on every call without caching
- ❌ Putting raw `$wpdb->prefix` in the `.sql` file (breaks portability for multisite/test fixtures)
