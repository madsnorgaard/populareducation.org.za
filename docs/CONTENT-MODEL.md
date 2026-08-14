# Content model

Kept in sync with `config/sync`. Built by `scripts/phase1-*.php` through
`phase5-*.php`; those scripts are historical records, the exported config is
the source of truth.

## Bundles

| Bundle | Purpose | Fields beyond title/body |
|---|---|---|
| `tool` | Workshop guides, games, handbooks, exercises | topics, resource_type, documents, images, source_org, external_link |
| `organisation` | The movement directory | topics, regions, active (boolean), website, images, documents |
| `library_item` | Articles, papers, chapters, pamphlets | topics, resource_type, documents, authors, external_link |
| `gallery` | Event/workshop photo galleries | topics, images |
| `audio_item` | Recordings (PEN 2018 "Listening into Popular Education") | topics, audio (multi), speakers, event |
| `page` | About, Definitions, section landings | documents, images |
| `blog_post` | Dated posts and reports | topics, images, documents |

Every bundle also carries `field_legacy_id` (hidden): the original D7 path,
set by pe_migrate. It makes re-imports idempotent - do not edit.

## Taxonomies

- `topics` - subject tags; auto-created during migration from D7 terms.
- `regions` - provinces, countries, "International"; editorial.
- `resource_type` - handbook, game, workshop guide, article...; editorial.

## Media types

`document` (pdf/doc/...), `image`, `audio` (mp3/wav), `remote_video` (oEmbed).
Migrated files live under `public://legacy/`.

## URLs

- Pathauto per bundle: `/tools/<title>`, `/organisations/<title>`,
  `/library/<title>`, `/galleries/<title>`, `/audio/<title>`, `/blog/<title>`.
- `page` records keep their exact legacy alias (`/content/about-us`,
  `/definitions-popular-education`).
- Every other legacy path (`/content/<slug>`, `/node/N`) 301s to the new
  home via the `pe_legacy_redirects` migration.

## Editorial state

Everything imports **unpublished** (status 0, uid 1). Publishing is a human
act in `/admin/content`. There is no content_moderation workflow; the
publish flag is the gate.
