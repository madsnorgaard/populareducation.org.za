<?php

/**
 * @file
 * Seed allied Revolutionary Papers / Pathways to Free Education entries.
 *
 * New editorial content (2026), not recovered from the archive - both carry
 * external links only; the material stays on its own site per its
 * non-commercial circulation ethos.
 *
 * `ddev drush scr scripts/seed-revolutionary-papers.php` - idempotent.
 */

use Drupal\node\Entity\Node;

$term = function (string $vid, string $name): ?int {
  $found = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => $vid, 'name' => $name]);
  return $found ? (int) reset($found)->id() : NULL;
};
$topics = fn(array $names) => array_values(array_filter(array_map(
  fn($n) => ($t = $term('topics', $n)) ? ['target_id' => $t] : NULL, $names)));

$storage = \Drupal::entityTypeManager()->getStorage('node');

if (!$storage->loadByProperties(['title' => 'Revolutionary Papers', 'type' => 'organisation'])) {
  Node::create([
    'type' => 'organisation',
    'title' => 'Revolutionary Papers',
    'status' => 1,
    'uid' => 1,
    'field_active' => 1,
    'field_website' => ['uri' => 'https://revolutionarypapers.org', 'title' => 'revolutionarypapers.org'],
    'field_topics' => $topics(['Education struggles', 'Organising & campaigns', 'Arts & culture']),
    'body' => [
      'format' => 'basic_html',
      'value' => '<p>A transnational research collaboration exploring twentieth-century periodicals of Left, anti-imperial and anti-colonial movements - the newspapers, journals and pamphlets that worked as counter-institutions and schools of struggle. Co-founded by Koni Benson (University of the Western Cape), Hana Morgenstern and Mahvish Ahmad, it joins over a hundred researchers with community archives and organisers across the Global South.</p><p>Their <a href="https://revolutionarypapers.org/teaching-tool/pathways-to-free-education/">teaching tools</a> turn movement archives into workshop-ready material - close kin to the tools on this site.</p>',
    ],
  ])->save();
  print "created organisation: Revolutionary Papers\n";
}

if (!$storage->loadByProperties(['title' => 'Pathways to Free Education (Volumes I-IV)', 'type' => 'tool'])) {
  Node::create([
    'type' => 'tool',
    'title' => 'Pathways to Free Education (Volumes I-IV)',
    'status' => 1,
    'uid' => 1,
    'field_external_link' => ['uri' => 'https://revolutionarypapers.org/teaching-tool/pathways-to-free-education/', 'title' => 'Read on revolutionarypapers.org'],
    'field_source_org' => 'Pathways / Revolutionary Papers',
    'field_topics' => $topics(['Education struggles', 'Organising & campaigns', 'Arts & culture']),
    'body' => [
      'format' => 'basic_html',
      'value' => '<p>Four free volumes from the Pathways collective, grown out of South Africa\'s 2015-2016 student movement: pamphleting tactics, printmaking as protest, strategy and tactics of organising, radical libraries and winter schools - with audio interviews and linocut prints alongside. A living continuation of the tradition this archive preserves.</p><p>The volumes and guides live on the <a href="https://revolutionarypapers.org/teaching-tool/pathways-to-free-education/">Pathways teaching tool</a>; they circulate non-commercially, so read and download them there.</p>',
    ],
  ])->save();
  print "created tool: Pathways to Free Education\n";
}
print "done\n";
