<?php

/**
 * @file
 * Backfill per-image names, alt text, captions and ordering on galleries.
 *
 * Input is the committed scripts/gallery-media.json (produced locally by
 * extract-gallery-media.php from the harvest). Safe to re-run: media are only
 * touched while they still carry a known-bad name (the parent gallery title,
 * an untitled placeholder, or the bare filename), so editor curation wins.
 * Publishing status is never changed.
 *
 * `ddev drush scr scripts/backfill-gallery-media.php` (same via drush on prod)
 */

use Drupal\node\Entity\Node;

$data_file = DRUPAL_ROOT . '/../scripts/gallery-media.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || !$data) {
  print "No usable data in $data_file - aborting.\n";
  return;
}

$normalize = static function (string $name): string {
  $name = mb_strtolower(rawurldecode($name));
  // Duplicate-filename imports were prefixed with 8 hex chars of the sha256.
  return (string) preg_replace('/^[0-9a-f]{8}_/', '', $name);
};

// A media name we may overwrite: the import-era fallbacks, never editor work.
$known_bad = static function (string $name, string $gallery_title, string $filename): bool {
  $stem = (string) preg_replace('/\.[a-z0-9]+$/i', '', $filename);
  return $name === $gallery_title
    || (bool) preg_match('/^untitled/i', $name)
    || strcasecmp($name, $filename) === 0
    || strcasecmp($name, $stem) === 0;
};

$storage = \Drupal::entityTypeManager()->getStorage('node');
$changed_media = 0;

foreach ($storage->loadByProperties(['type' => 'gallery']) as $node) {
  /** @var \Drupal\node\Entity\Node $node */
  $legacy = $node->get('field_legacy_id')->value;
  $title = $node->label();
  $items = $data[$legacy] ?? [];
  $media_list = $node->get('field_images')->referencedEntities();
  $total = count($media_list);
  if (!$total) {
    printf("%-38s no images, skipped\n", mb_substr($title, 0, 38));
    continue;
  }

  // Index this node's media by normalized original-file basename.
  $by_file = [];
  foreach ($media_list as $delta => $media) {
    $file = $media->get('field_media_image')->entity;
    if ($file) {
      $by_file[$normalize(basename($file->getFileUri()))][] = [$delta, $media];
    }
  }

  $matched = [];
  $order = [];
  foreach ($items as $item) {
    $candidates = $by_file[$normalize($item['filename'])] ?? [];
    if (count($candidates) !== 1) {
      continue;
    }
    [$delta, $media] = $candidates[0];
    $matched[$delta] = TRUE;
    $order[$delta] = $item['item_no'];

    $new_name = empty($item['junk']) && $item['title'] !== ''
      ? $item['title']
      : sprintf('%s - image %d of %d', $title, $item['item_no'], $item['item_total']);
    $caption = empty($item['junk']) ? $item['title'] : '';

    if ($known_bad($media->label(), $title, $item['filename'])
      && ($media->label() !== $new_name || ($media->get('field_caption')->value ?? '') !== $caption)) {
      $media->setName($new_name);
      $source = $media->get('field_media_image')->first();
      $source->set('alt', mb_substr($new_name, 0, 512));
      $media->set('field_caption', $caption === '' ? NULL : $caption);
      $media->save();
      $changed_media++;
    }
  }

  // Anything unmatched still wearing the import fallback gets a positional
  // name so no two images on the site share identical alt text.
  $fallback = 0;
  foreach ($media_list as $delta => $media) {
    if (isset($matched[$delta])) {
      continue;
    }
    $file = $media->get('field_media_image')->entity;
    $filename = $file ? basename($file->getFileUri()) : '';
    $new_name = sprintf('%s - image %d', $title, $delta + 1);
    if ($known_bad($media->label(), $title, $filename) && $media->label() !== $new_name) {
      $media->setName($new_name);
      $media->get('field_media_image')->first()->set('alt', $new_name);
      $media->save();
      $changed_media++;
      $fallback++;
    }
  }

  // Restore the original D7 order, but only when the evidence is airtight:
  // every image matched, the totals agree, and the positions are unique.
  $reordered = 'kept';
  $first_total = $items[0]['item_total'] ?? NULL;
  if (count($matched) === $total && $first_total === $total
    && count(array_unique($order)) === $total) {
    asort($order);
    $target = array_keys($order);
    if ($target !== range(0, $total - 1)) {
      $values = $node->get('field_images')->getValue();
      $node->set('field_images', array_values(array_map(static fn (int $d) => $values[$d], $target)));
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('Gallery media backfill: restored original image order.');
      $node->save();
      $reordered = 'REORDERED';
    }
  }

  printf("%-38s %2d imgs, %2d matched, %2d positional, order %s\n",
    mb_substr($title, 0, 38), $total, count($matched), $fallback, $reordered);
}

printf("\n%d media entities updated.\n", $changed_media);
if (!$changed_media) {
  print "Nothing changed - either already backfilled (fine) or the data file is stale.\n";
}
