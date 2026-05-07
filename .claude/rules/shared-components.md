# Rule: Shared, Reusable Components

Build a small, sharp set of shared building blocks. Reuse them. Don't reinvent on every feature.

## React (frontend & admin)

### Where shared code lives

```
assets/src/js/shared/
├── api.js              # REST client (already exists)
├── cn.js               # className merge helper (already exists)
├── hooks/
│   ├── useApi.js       # useQuery/useMutation wrappers around api.js
│   ├── useToast.js     # Toast trigger
│   └── usePaginated.js # Standard cursor/page state for listings
├── ui/                 # shadcn-style primitives (Button, Card, Input, …)
│   ├── button.jsx
│   ├── card.jsx
│   ├── dialog.jsx
│   ├── input.jsx
│   ├── select.jsx
│   ├── table.jsx
│   ├── tabs.jsx
│   ├── badge.jsx
│   └── toast.jsx
├── components/         # Higher-level reused widgets (composed from ui/)
│   ├── DataTable.jsx
│   ├── EmptyState.jsx
│   ├── ErrorBoundary.jsx
│   ├── PaginationBar.jsx
│   ├── StatusPill.jsx
│   └── ConfirmDialog.jsx
└── utils/
    ├── format.js       # money, date, number helpers
    └── validators.js   # email, points-multiple-of-20, etc.
```

### Rules

1. **Before you create a component, search `shared/`.** If 60%+ of what you need exists, extend the shared one — don't fork.
2. **`ui/` is the design-system layer** — visual primitives only, no business logic, no API calls.
3. **`components/` is the composition layer** — combine `ui/` + hooks. Still no domain knowledge ("members," "vouchers"). A `DataTable` doesn't know what a member is.
4. **Domain-specific components stay in their feature folder** (`account/membership/MembershipCard.jsx`, `admin/vouchers/VoucherForm.jsx`). They consume `shared/`, not the other way around.
5. **Promote to shared on the third use, not the first.** Two callers = duplicate. Three = pattern. Premature shared components are wrong shared components.
6. **No prop-drilling through three layers** — if you find yourself doing it, extract a context provider into `shared/contexts/`.

## PHP

### Where shared code lives

```
src/
├── Core/                       # Plugin-level: Assets, Plugin, Installer, Endpoints
├── Support/                    # Reusable helpers
│   ├── Repository.php          # Base repo: find/findBy/insert/update/delete
│   ├── RestResponse.php        # Standard success/error envelope helpers
│   ├── Validator.php           # Reusable input validators
│   ├── DateTimeHelper.php      # WP timezone-aware datetime utilities
│   └── Cache.php               # Wrapper around wp_cache_* with our key prefix
└── Database/
    └── QueryLoader.php         # See sql-files.md
```

### Rules

1. **Repositories extend a base `Repository`** — every repo gets `find($id)`, `findBy(array $criteria)`, `insert(array $data)`, `update($id, array $data)`, `delete($id)` for free.
2. **REST controllers use `RestResponse::ok($data)` / `RestResponse::error($code, $message, $status)`** — never call `wp_send_json_*` directly. Keeps the error envelope identical across all endpoints.
3. **Validation lives in `Support/Validator.php` or a feature-specific `*Validator` class** — never inline 30 lines of `if (! is_int($x))` in a controller.
4. **Cache reads through `Support/Cache::get/set/delete`** — never call `wp_cache_*` directly. Centralizes the key prefix and TTLs.
5. **Same promotion rule as React:** third use = promote. First time = leave it inline.

## Anti-patterns

- ❌ Copy-pasting a "Button" component into each feature folder with slight tweaks
- ❌ A `helpers.php` / `utils.js` dumping ground — group by purpose (`format.js`, not `utils.js`)
- ❌ Shared components with feature-specific props (`<Card showVoucherBadge />`) — that means the abstraction is wrong
- ❌ Adding a new `shared/ui/*` component because it *might* be reused later — wait for the second caller
