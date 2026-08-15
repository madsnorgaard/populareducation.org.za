<?php

/**
 * @file
 * Distil the harvest's media-gallery detail pages into a committed data file.
 *
 * The D7 site had one page per gallery image (/media-gallery/detail/GID/MID)
 * carrying the real per-image title, the parent gallery path and an explicit
 * "Item N of M" position. None of that survived the node/media migrations.
 * This script reads those harvest records and writes
 * scripts/gallery-media.json - the reviewed, committed input for
 * backfill-gallery-media.php (which is what actually touches entities, and
 * is the only piece that runs on production).
 *
 * Local only - needs the staged harvest:
 * `ddev drush scr scripts/extract-gallery-media.php`
 */

use Drupal\Core\Site\Settings;

$source_root = Settings::get('pe_migrate_source', '/var/www/html/private/harvest');
$merged = $source_root . '/items/merged';
if (!is_dir($merged)) {
  print "Harvest directory $merged not found - run locally with the harvest staged.\n";
  return;
}

// The D7 media titles were filename-derived except for a handful of real
// human captions - and comment spam overwrote many titles outright
// ("http://...", random strings, prose clipped at 20 chars). A title counts
// as real only when it resembles its filename or is on this hand-reviewed
// allowlist of genuine captions (reviewed against the full 114-item set).
$allowlist = [
  'demonstrate solidarity with paper figurines',
  'play - can you back stab?',
  'group juggling - establishing a pattern',
  'more juggling - laughter',
  'presentation and panel',
  'presentation and panel 2',
  'a thinking panel',
  'conversations',
  'solidarity with the body',
  'final gesture',
  'tablecloth artwork',
  'exploring union',
];
$normalize = static fn (string $s): string => (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));
$junk = static function (string $title, string $filename) use ($allowlist, $normalize): bool {
  if ($title === ''
    || preg_match('/^(img|dsc|dscn|pb|p|photo|image|untitled)[\s_-]*\d/i', $title)
    || preg_match('/^(view larger|download|read this|click here)/i', $title)
    || preg_match('~https?:|www\.~i', $title)
    || preg_match('/^\d+$/', $title)) {
    return TRUE;
  }
  if (in_array(mb_strtolower($title), $allowlist, TRUE)) {
    return FALSE;
  }
  $t = $normalize($title);
  $f = $normalize((string) preg_replace('/\.[a-z0-9]+$/i', '', $filename));
  return !($t !== '' && $f !== ''
    && (str_contains($f, $t) || str_contains($t, $f) || substr($t, 0, 12) === substr($f, 0, 12)));
};

$records = [];
foreach (glob($merged . '/media-gallery-detail-*.json') as $path) {
  $item = json_decode((string) file_get_contents($path), TRUE);
  if (!is_array($item) || ($item['kind'] ?? '') !== 'gallery_media') {
    continue;
  }
  if (!preg_match('~media-gallery/detail/(\d+)/(\d+)(/edit)?$~', $item['source_id'] ?? '', $m)) {
    continue;
  }
  [, $gid, $mid] = $m;
  $is_edit = !empty($m[3]);
  $key = "$gid/$mid";
  $records[$key] ??= ['gid' => (int) $gid, 'mid' => (int) $mid];
  $rec = &$records[$key];

  $title = html_entity_decode(trim((string) ($item['title'] ?? '')), ENT_QUOTES);
  if ($is_edit) {
    // Edit pages have no body; their title (minus the "Edit image" prefix)
    // is only a secondary title source.
    $rec['edit_title'] = trim((string) preg_replace('/^Edit image\s*/i', '', $title));
    unset($rec);
    continue;
  }

  $rec['title'] = $title;
  $body = (string) ($item['body'] ?? '');
  if (preg_match('~media-gallery-back-link"><a href="([^"]+)"~', $body, $bm)) {
    $rec['parent'] = $bm[1];
  }
  if (preg_match('/Item (\d+) of (\d+)/', $body, $im)) {
    $rec['item_no'] = (int) $im[1];
    $rec['item_total'] = (int) $im[2];
  }
  if (preg_match('~src="[^"]*styles/[^/]+/public/([^"?]+)~', $body, $fm)) {
    $rec['filename'] = rawurldecode($fm[1]);
  }
  elseif (preg_match('~gallery-download" href="/media/\d+/download/([^"?]+)~', $body, $dm)) {
    $rec['filename'] = rawurldecode($dm[1]);
  }
  unset($rec);
}

// Group by parent gallery legacy path; edit-only records (no body, so no
// parent and no filename) are unmatchable and dropped with a note.
$out = [];
$dropped = 0;
foreach ($records as $rec) {
  if (empty($rec['parent']) || empty($rec['filename'])) {
    $dropped++;
    continue;
  }
  $title = $rec['title'] ?? '';
  if ($junk($title, $rec['filename']) && !empty($rec['edit_title']) && !$junk($rec['edit_title'], $rec['filename'])) {
    $title = $rec['edit_title'];
  }
  $out[$rec['parent']][] = [
    'mid' => $rec['mid'],
    'item_no' => $rec['item_no'] ?? NULL,
    'item_total' => $rec['item_total'] ?? NULL,
    'filename' => $rec['filename'],
    'title' => $title,
    'junk' => $junk($title, $rec['filename']),
  ];
}
ksort($out);
foreach ($out as &$items) {
  usort($items, static fn (array $a, array $b): int => ($a['item_no'] ?? PHP_INT_MAX) <=> ($b['item_no'] ?? PHP_INT_MAX) ?: $a['mid'] <=> $b['mid']);
}
unset($items);

$file = DRUPAL_ROOT . '/../scripts/gallery-media.json';
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

$total = array_sum(array_map('count', $out));
printf("Wrote %s: %d galleries, %d items (%d unmatchable records dropped).\n", $file, count($out), $total, $dropped);
