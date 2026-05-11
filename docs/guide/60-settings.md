# Settings reference

Every option in **Zippy CRM → Settings**, what it does, and recommended values.

## Points settings

### Points per dollar
- **Default**: `1`
- **Range**: any positive integer
- **What it does**: Base earn rate before tier multiplier. `1` means a $50 order earns 50 base points (multiplied by tier).
- **Recommended**: `1`. Round numbers are easier for customers to reason about.

### Redemption rate
- **Default**: `20`
- **Range**: any positive integer ≥ 1
- **What it does**: How many points equal $1 of discount. `20` means 20 points → $1 off.
- **Recommended**: `20` or `100`. Both round numbers; `20` is the same ratio as Starbucks (loosely), `100` is the same as Sephora.
- **Caveat**: changing this AFTER customers have earned points changes the dollar value of their existing balance. If you bump from 20 → 100, a customer with 200 points goes from "$10 of redeemable value" to "$2". Communicate before changing.

### Minimum redemption
- **Default**: `20`
- **Range**: any positive integer, must be a multiple of `redemption_rate`
- **What it does**: Customers must redeem at least this many points at once. Prevents a customer from redeeming 1 point at a time.
- **Recommended**: same as `redemption_rate`. A higher minimum (e.g. `100`) makes redemptions feel "worth it" for customers — small redemptions feel like noise.

## Tier settings (per-tier — managed under Tiers panel, not Settings)

Each tier has its own settings — see the **Tiers** panel. Recap of the per-tier options:

| Option | What it does |
|---|---|
| Multiplier | Multiplier applied to base earn rate. `1.5` = 50% more points. |
| Threshold orders | Minimum order count to qualify for this tier. Blank = no order requirement. |
| Threshold spend | Minimum lifetime spend to qualify. Blank = no spend requirement. |
| Admin-only | If checked, this tier is only assignable manually — auto-evaluation skips it. |
| Sort order | Position in the ladder. Lower numbers appear lower. |

## Notification settings

### Default opt-in (vouchers)
- **Default**: `true` (checked)
- **What it does**: Whether the "Email me about new vouchers" checkbox on the registration form starts checked.
- **Recommended**: `true` for most stores; check local privacy law (GDPR, CCPA, CASL) if you're unsure.

### Default opt-in (points)
- **Default**: `true` (currently no points emails are actually sent — preference exists for future use)
- **Recommended**: `true`.

### Email batch size
- **Default**: `50` (set via PHP constant `ZIPPY_CRM_EMAIL_BATCH_SIZE`)
- **What it does**: Max emails sent per cron tick.
- **Recommended**: `50` for shared hosting, up to `200` for dedicated SMTP providers like SendGrid. Higher = faster dispatch, more risk of throttling.

### Email batch interval
- **Default**: `300` seconds (5 min) (set via PHP constant `ZIPPY_CRM_EMAIL_BATCH_INTERVAL`)
- **What it does**: How long between batches.
- **Recommended**: `300` for default. Drop to `60` if you have high-throughput SMTP and need faster dispatch.

## Constants (wp-config.php overrides)

The PHP constants below let you override hardcoded defaults without touching the plugin source. Define in `wp-config.php` **before** the `ABSPATH` line:

| Constant | Default | Purpose |
|---|---|---|
| `ZIPPY_CRM_VERSION` | matches plugin version | Schema version — bump triggers `dbDelta()` re-run |
| `ZIPPY_CRM_POINTS_RATE` | `20` | Override redemption rate |
| `ZIPPY_CRM_MIN_REDEMPTION` | `20` | Override minimum redemption |
| `ZIPPY_CRM_EMAIL_BATCH_SIZE` | `50` | Notification batch size |
| `ZIPPY_CRM_EMAIL_BATCH_INTERVAL` | `300` | Seconds between batches |

Most stores never need to set these — the in-app Settings page covers the day-to-day knobs.

## Filters (theme/plugin overrides)

Drop these in your theme's `functions.php` or a small mu-plugin to override behavior:

```php
// Make every customer earn 2× points (regardless of tier)
add_filter( 'crm_points_redemption_rate', function () {
    return 10; // 10 points = $1 instead of 20
} );

// Make the points-redemption fee taxable (default: post-tax)
add_filter( 'crm_points_fee_taxable', '__return_true' );

// Override the conflicting-plugin slug for the EPOS coexistence guard
add_filter( 'zippy_crm_conflicting_plugin', function ( $slug ) {
    return 'my-other-loyalty-plugin/main.php';
} );
```
