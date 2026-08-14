# Onboarding

From zero to a running site with the full legacy corpus.

## 1. Run the site

```bash
git clone git@github.com:madsnorgaard/populareducation.org.za.git
cd populareducation.org.za
ddev start
ddev composer install
ddev drush si --existing-config -y
ddev drush scr scripts/seed-menus.php
ddev drush uli
```

## 2. Get the harvest

```bash
git clone git@github.com:madsnorgaard/populareducation-harvest.git ~/populareducation-harvest
cd ~/populareducation-harvest
python3 -m venv .venv && .venv/bin/pip install -r requirements.txt
.venv/bin/python harvest.py all            # ~1h, resumable, be patient
.venv/bin/python scripts/merge_and_report.py
```

## 3. Stage it for the importer

```bash
cd /path/to/populareducation.org.za
mkdir -p private/harvest
rsync -a ~/populareducation-harvest/output/populareducation/items private/harvest/
rsync -a ~/populareducation-harvest/output/populareducation/media private/harvest/
```

`web/sites/default/settings.local.php` (gitignored) points
`pe_migrate_source` at `/var/www/html/private/harvest`. Create it if missing:

```php
<?php
$settings['pe_migrate_source'] = '/var/www/html/private/harvest';
```

## 4. Import

```bash
ddev drush migrate:status                  # totals should match the harvest
ddev drush migrate:import --tag=pe_legacy
```

Everything lands unpublished under uid 1. Review in `/admin/content`
(filter: unpublished), publish what is ready.

## 5. Verify

- `/tools`, `/library`, `/organisations`, `/galleries`, `/audio` list content
  once published.
- A legacy URL such as `/content/what-popular-education` 301s to the new path.
- `ddev drush config:status` is empty after any config work you commit.

Re-running an import updates rather than duplicates (rows are keyed by
legacy path / file sha256). Roll back with
`ddev drush migrate:rollback --tag=pe_legacy`.
