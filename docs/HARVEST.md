# Harvest

Content source: [populareducation-harvest](https://github.com/madsnorgaard/populareducation-harvest) -
a standalone Python pipeline that recovers both archived domains
(populareducation.org.za to Aug 2023, populareducation.co.za to Nov 2025)
from the Wayback Machine.

- Output contract: `items/merged/*.json` (one record per legacy page) +
  `media/` (sha256 content-addressed files). `pe_migrate` reads exactly this.
- Re-run any time; it is resumable and never refetches what it has.
- `MISSING-CONTENT.md` in the harvest output lists everything the archive
  never captured; the committed copy in this repo is the working document -
  see [MISSING-CONTENT.md](MISSING-CONTENT.md).

Staging into the site: see [ONBOARDING.md](ONBOARDING.md) step 3.

## Gallery per-image metadata

The D7 `/media-gallery/detail/GID/MID` pages (harvest kind `gallery_media`)
carry the real per-image titles, ordering and captions that the node/media
migrations never used. `scripts/extract-gallery-media.php` distils them into
the committed `scripts/gallery-media.json`; the idempotent
`scripts/backfill-gallery-media.php` applies names/alt/captions/order to the
gallery media (this is the piece that also runs on production, where the
harvest itself is absent). Re-running the legacy media/gallery migrations
with `--update` clobbers the backfilled names - re-run the backfill after.
