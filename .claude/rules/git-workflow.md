> **AI assistants:** this rule is the source of truth for git workflow on this repo.
> Follow it for every commit and PR. The user has saved a personal memory pointing at this file —
> if anything here is unclear, ask before committing rather than guessing.

# Rule: Git Workflow — Branch + Commit + PR

Every change ships through a feature branch and a pull request. `master` is protected and only updated via merged PRs reviewed on GitHub.

## TL;DR

```bash
# 1. Branch off master
git checkout master && git pull origin master
git checkout -b <type>/<short-slug>-<YYYY-MM-DD>

# 2. Commit (descriptive subject + body)
git add <files>
git commit               # write the message in your editor

# 3. Push + open PR
git push -u origin HEAD
gh pr create --base master --fill-first
# then edit the PR body to add the Test Plan
```

## Hard rules

### 1. One branch per task — never commit to `master` directly

Branch naming: `<type>/<short-slug>-<YYYY-MM-DD>`

| `<type>` | When to use |
|---|---|
| `feat`     | New feature or capability |
| `fix`      | Bug fix |
| `chore`    | Tooling, config, dependencies |
| `refactor` | Restructure with no behavior change |
| `docs`     | Documentation only |
| `test`     | Test additions / fixes only |
| `style`    | Formatting / whitespace only |

Examples: `feat/membership-redesign-2026-05-11`, `fix/voucher-claim-expiry-2026-05-11`, `chore/release-tooling-2026-05-11`.

The date suffix prevents collisions when two people pick similar slugs.

### 2. Commit messages: subject + body, always

**Subject** — under ~72 chars, imperative mood:
- ✅ `add voucher claim history sub-tab`
- ✅ `fix WC coupon sync when voucher publishes twice`
- ❌ `Added stuff` / `WIP` / `update code` / `fixes`

**Body** — always include, even for small commits. Explain:
- **Why** the change was needed (not just what — the diff already shows what)
- **What** changed at a high level (bullets are fine)
- **Follow-ups / known limitations** if any — the reviewer needs to know what's *not* covered

Use a HEREDOC so multi-line formatting survives:

```bash
git commit -m "$(cat <<'EOF'
add voucher claim history sub-tab

Customers couldn't see used / expired claims after redemption — codes
just vanished. New "History" tab paginates over the full claim audit
trail so they can verify a code was used and against which order.

- Schema: new revocation_reason column on crm_voucher_claims
- New endpoint GET /vouchers/claims/history (paginated, default 50)
- HistoryList.jsx renders display_status + reason_label verbatim

Follow-up: the cascade hook in WcCouponDelete.php still writes NULL
for the reason — separate PR will wire it.
EOF
)"
```

### 3. Open a PR for every branch

```bash
gh pr create --base master --head $(git branch --show-current) \
  --title "<same as commit subject>" \
  --body-file .pr-description.md
```

PR body MUST include:
- **`## Summary`** — same explanation as the commit body, slightly expanded if helpful
- **`## Test plan`** — markdown checklist of manual QA steps the reviewer should run before approving. For this plugin, that usually means pointing at scripts in `tests/manual/` (e.g. `Run tests/manual/zc-test-tender.php`)

If the PR bundles multiple commits, list each commit + its rationale in the Summary.

### 4. Author attribution

- Commits and PRs are authored by the **human** opening them. Do not add `Co-Authored-By` trailers for AI assistants.
- Do not add "🤖 Generated with …" footers to PR descriptions.
- The PR is the team artifact — keep it free of tooling metadata.

### 5. Do NOT bundle unrelated work in one PR

If `git status` shows files outside the task you just finished, **stop and decide**:
- Are they your in-progress work for a different task? Stash or branch them separately.
- Are they accidental edits? Revert them before staging.
- Are they someone else's work that landed via merge? Don't include them in your PR.

The reviewer should be able to read the PR description and predict the diff. A PR titled "fix voucher claim expiry" should not also touch the points engine, the tier admin UI, and `vite.config.js`.

### 6. Never force-push shared branches

- `master` — never force-push. Period.
- Your own feature branch *before anyone else has pulled it* — okay to force-push to clean up history (`git rebase -i`, `git commit --amend`).
- Your own feature branch *after a teammate has pulled or commented on it* — coordinate first, or just add a fixup commit.

### 7. Never skip hooks or signing

No `--no-verify`, no `--no-gpg-sign`. If a pre-commit hook fails, **fix the underlying issue** and create a NEW commit. Don't `--amend` a commit the hook rejected — the original commit didn't happen, so amending modifies the *previous* commit, which can destroy work.

### 8. Don't commit secrets or large binaries

- No `.env` / `wp-config.php` / `*.key` / `credentials.json` etc.
- No `node_modules/`, `vendor/`, build output (`assets/dist/` is gitignored — rebuild on each install)
- No release zips (`dist/zippy-crm-*.zip` — these belong on GitHub Releases)

If you're tempted to add to `.gitignore`, double-check the file isn't already covered.

## Anti-patterns

- ❌ `git commit -am "fix"` — terse subject, no body, sweeps unrelated files via `-a`
- ❌ Branch named `update`, `wip`, `feature-1`, `shin-branch` — meaningless to teammates and stale-detection
- ❌ One PR with 30 commits called "various improvements"
- ❌ Force-pushing `master` "to clean up the history"
- ❌ Merging your own PR without review (when the change isn't trivial)
- ❌ Commit messages that describe the implementation in code-review detail — that belongs in the PR body, not the commit log
- ❌ Skipping the PR step "because it's a tiny change" — tiny changes still need the trail

## When the rule bends (rare)

- **Hotfix on production** — emergency security patch can go straight to `master` *if* a follow-up PR documents what changed within 24 hours.
- **Branch is a personal experiment never going to `master`** — message format is your choice; just don't push it as a PR.
- **Solo dev, no review possible** — still open the PR (so the description exists as documentation), then self-merge with a note in the description that no peer review was available.

## Quick reference: full flow

```bash
# Start of task
git checkout master
git pull origin master
git checkout -b feat/my-thing-2026-05-11

# Work, commit
git add <files>
git commit       # write subject + body in editor

# More commits as needed (one logical change each)
git commit -am "..."   # only if you really want to add all tracked changes

# Push + PR
git push -u origin HEAD
gh pr create --base master \
  --title "feat: my thing — one-line summary" \
  --body "$(cat <<'EOF'
## Summary

Why this change was needed, what it does, any follow-ups.

## Test plan

- [ ] Run \`tests/manual/zc-test-foo.php\` — verifies X
- [ ] Manually click through the My Account UI to confirm Y
- [ ] Check no console errors / no PHP warnings
EOF
)"

# After PR merges
git checkout master
git pull origin master
git branch -d feat/my-thing-2026-05-11   # delete local branch
```
