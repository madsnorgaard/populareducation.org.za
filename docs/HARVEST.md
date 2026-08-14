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
