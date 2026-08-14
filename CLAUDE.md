# populareducation.org.za - working conventions

Drupal 11 rebuild of Popular Education South Africa from the Wayback
Machine. Two repos: this one (site) and populareducation-harvest (content
recovery). Read README.md and docs/ before changing anything.

## Hard rules

- **Config as code.** Change config in DDEV, `ddev drush cex -y`, commit.
  `config/sync` must always clean-install (CI gates it). Dev-only modules
  (field_ui, views_ui, migrate*, pe_migrate) live in the `develop` split.
- **Never auto-publish.** Migrated content lands unpublished; publishing is
  an editorial act by a human.
- **Never edit `pe_theme/dist/`.** Build with `npm run build` in the theme
  directory and commit the result; CI diffs it.
- **Two settings.php files.** Repo root `settings.php` = production (env
  driven, baked into the Docker image). `web/sites/default/settings.php` =
  DDEV/CI only. Local secrets/overrides go in `settings.local.php`
  (gitignored).
- **No secrets in git.** No real IPs, ports, or credentials. Deploy env
  lives on the server, never here.
- **Legacy URLs are a promise.** `/content/<slug>` and `/node/N` must keep
  resolving (301) forever. `page` bundle keeps its exact legacy alias.

## Design

pe_theme is the struggle-poster system: newsprint paper, linocut ink, one
pass of silkscreen red (#b5231f), Anton display caps, Archivo body, Plex
Mono slugs. No gradients, shadows, or rounded corners. The misregistered
double rule (`.register`) is the signature - use it on section headings
only. The linocut artwork (logo, ACTION figures) is identity, not
decoration; keep `mix-blend-mode: multiply` so it prints on the paper.

## Deploy

`.github/workflows/deploy.yml` dispatches to madsnorgaard/contabo-infrastructure
on push to main. It stays dormant until `~/docker/populareducation.org.za/.env`
exists on the production server (hosting/domain decided with the project
owners). Changes to the shared infra deploy.yml go via PR in that repo.

## Migrations

`pe_migrate` reads `$settings['pe_migrate_source']` (default
`/var/www/html/private/harvest`). Row keys: legacy path (nodes), sha256
(files/media), source path (redirects) - re-imports update, never
duplicate. Order: files -> media -> nodes -> redirects
(`--tag=pe_legacy` runs everything in dependency order).

WARNING: `migrate:import --update` (and rollback + import) resets every
migrated node to unpublished - it re-applies the draft-only default.
Migrations are for the initial load; once editors start publishing, do
not re-run them over published content. A rollback also deletes the
nodes, which purges any menu links pointing at them - re-run
`drush scr scripts/seed-menus.php` afterwards (idempotent).

# CLAUDE.md

## Instructions for Agents on how to collaborate with Jumbo

See JUMBO.md and follow all instructions. If the file does not exist, then ignore this instruction.
