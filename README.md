# populareducation.org.za

Drupal 11 rebuild of **Popular Education South Africa** ("Looking back to go
forward together") - the archive of South African popular-education tools,
organisations, library texts, galleries, and the PEN 2018 audio archive. The
original Drupal 7 site died; its content was recovered from the Wayback
Machine by [populareducation-harvest](https://github.com/madsnorgaard/populareducation-harvest)
and imported here as drafts for editorial review.

## Quickstart

```bash
ddev start
ddev composer install
ddev drush si --existing-config -y
ddev drush scr scripts/seed-menus.php
```

Site: https://populareducation.ddev.site - admin login via `ddev drush uli`.

To import the harvested content, see [docs/ONBOARDING.md](docs/ONBOARDING.md).

## Commands

| Command | What it does |
|---|---|
| `ddev drush cex -y` | Export config to `config/sync` (the source of truth) |
| `ddev drush cim -y` | Import config |
| `ddev drush migrate:status` | Show import state of the legacy content |
| `ddev drush migrate:import --tag=pe_legacy` | Import everything (as drafts) |
| `npm run build` (in `web/themes/custom/pe_theme`) | Rebuild theme assets into committed `dist/` |

## Layout

```
config/sync/        exported config - law; config/develop = dev-only split
web/modules/custom/pe_migrate   harvest importer (develop split only)
web/themes/custom/pe_theme      struggle-poster theme (Vite, committed dist/)
scripts/            one-off drush scripts that built the content model
docs/               content model, onboarding, missing-content worklist
```

## Rules

- All content imports land **unpublished**. Publishing is a human decision.
- `config/sync` is law: change config in DDEV, `cex`, commit. No UI-only config.
- Never edit `pe_theme/dist/` by hand; CI fails if it drifts from `src/`.
- Legacy URLs 301 to their new homes; `page` content keeps its exact old path.
