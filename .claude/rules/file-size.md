# Rule: File Size & Splitting

## Hard cap: 500 lines per file

If a PHP/JS/JSX/SCSS file passes ~500 lines, split it before adding more. Long files hide bugs, are hard to review, and signal the file is doing too many things.

### Where to split

Split along **responsibility seams**, not by line count. Some good seams:

| File type | Split when… | Split into |
|-----------|-------------|------------|
| Service class | A method group serves a different verb (e.g. award vs redeem vs recalculate) | Sub-service or separate `*Handler` |
| Model class | Read methods + write methods + complex queries all in one place | Keep `Model` for CRUD, move complex queries to `*Repository` |
| REST controller | Handlers grow > ~30 lines each | Move handler bodies to a Service, keep controller as thin route → service mapping |
| React component | JSX + state + side-effects + sub-components in one file | Extract sub-components to `./components/`, hooks to `./hooks/` |
| SCSS partial | More than one component's styles | One partial per component |

### Anti-patterns

- **Don't split arbitrarily** at line 501 just to satisfy the cap — find a meaningful seam at line ~300 instead.
- **Don't create a `helpers.php` / `utils.js` dumping ground** — that's a file-size shell game. Helpers belong with the thing they help.
- **Don't extract a class with one caller and no other reason to exist** — that's not a split, it's misdirection.

### Before splitting, ask

1. Is this file long because the *feature* is large (split is justified) or because I'm mixing concerns (refactor first, then it splits naturally)?
2. Will the split help the next reader, or just hide complexity behind another file?

If the answer is "hide complexity," refactor instead.
