<?php

/**
 * @file
 * Repair double-encoded UTF-8 (mojibake) from the Wayback harvest.
 *
 * The raw archive snapshots carry no charset header, so the harvester
 * decoded UTF-8 pages as Latin-1: ’ became â€™, é became Ã©, NBSP became Â .
 * Repair = re-encode the string to Windows-1252 bytes and read them back as
 * UTF-8, repeated until stable. Runs over node titles, bodies, summaries,
 * media names and taxonomy term names.
 *
 * `ddev drush scr scripts/fix-mojibake.php` - idempotent.
 */

$looks_broken = fn(string $s): bool => (bool) preg_match('/â€|Ã[\x80-\xBF]|Â[\s£©®°]|Ã©|Ã¨|Ã¢/u', $s);

// A whole-string CP1252 round-trip corrupts bodies that mix real accents
// with mojibake, so repair by sequence table instead (strtr matches longest
// first). Covers every sequence observed in the harvested corpus.
$map = [
  "â€™" => "’", "â€˜" => "‘",
  "â€œ" => "“", "â€\u{9D}" => "”",
  "â€“" => "–", "â€”" => "—",
  "â€¦" => "…", "â€¢" => "•",
  "â€" => "”",
  "Ã©" => "é", "Ã¨" => "è", "Ãª" => "ê", "Ã«" => "ë",
  "Ã¡" => "á", "Ã " => "à", "Ã¢" => "â", "Ã£" => "ã", "Ã¤" => "ä",
  "Ã­" => "í", "Ã¬" => "ì", "Ã®" => "î", "Ã¯" => "ï",
  "Ã³" => "ó", "Ã²" => "ò", "Ã´" => "ô", "Ãµ" => "õ", "Ã¶" => "ö",
  "Ãº" => "ú", "Ã¹" => "ù", "Ã»" => "û", "Ã¼" => "ü",
  "Ã±" => "ñ", "Ã§" => "ç", "ÃŸ" => "ß",
  "Ã‰" => "É", "Ã€" => "À", "Ã‡" => "Ç", "Ã–" => "Ö", "Ãœ" => "Ü",
  "Â£" => "£", "Â©" => "©", "Â®" => "®", "Â°" => "°",
  "Â\u{A0}" => " ", "Â " => " ",
];
$repair = fn(string $s): string => strtr($s, $map);

$fixed = 0;

// --- Nodes -------------------------------------------------------------------
$storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->execute();
foreach (array_chunk($ids, 50) as $chunk) {
  foreach ($storage->loadMultiple($chunk) as $node) {
    $changed = FALSE;
    $title = $node->getTitle();
    if ($looks_broken($title)) {
      $node->setTitle($repair($title));
      $changed = TRUE;
    }
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $item = $node->get('body')->first();
      foreach (['value', 'summary'] as $prop) {
        $v = (string) $item->{$prop};
        if ($v !== '' && $looks_broken($v)) {
          $item->set($prop, $repair($v));
          $changed = TRUE;
        }
      }
    }
    if ($changed) {
      $node->save();
      $fixed++;
    }
  }
}
print "nodes repaired: $fixed\n";

// --- Media names + taxonomy terms -------------------------------------------
foreach (['media' => 'name', 'taxonomy_term' => 'name'] as $type => $label_field) {
  $n = 0;
  $s = \Drupal::entityTypeManager()->getStorage($type);
  foreach ($s->loadMultiple($s->getQuery()->accessCheck(FALSE)->execute()) as $e) {
    $v = (string) $e->get($label_field)->value;
    if ($looks_broken($v)) {
      $e->set($label_field, $repair($v));
      $e->save();
      $n++;
    }
  }
  print "$type repaired: $n\n";
}

print "done\n";
