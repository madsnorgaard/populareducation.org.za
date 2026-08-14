<?php

/**
 * @file
 * Seed topics + resource types and tag the migrated corpus by keyword.
 *
 * `ddev drush scr scripts/seed-taxonomy.php` - idempotent, additive only:
 * existing term references are kept, terms are matched by name. A crude
 * first pass so the topic filters and term pages work; editors refine.
 */

use Drupal\taxonomy\Entity\Term;

$topic_map = [
  'Racism & anti-racism' => ['racism', 'racist', 'race ', 'apartheid', 'privilege walk'],
  'Gender & feminism' => ['gender', 'women', 'feminis', 'sexual violence', 'patriarch'],
  'Climate & environment' => ['climate', 'environment', 'sustainab', 'green ', 'water', 'nuclear', 'energy'],
  'Workers & unions' => ['worker', 'union', 'labour', 'cosatu', 'wage', 'informal traders'],
  'Health' => ['health', 'hiv', 'aids', 'tuberculosis', ' tb ', 'literacy for women'],
  'Food & land' => ['food', 'land ', 'farm', 'agrar', 'sovereignty'],
  'Housing & services' => ['housing', 'sanitation', 'electricity', 'evict'],
  'Education struggles' => ['education', 'school', 'university', 'student', 'literacy', 'learning'],
  'Democracy & power' => ['democracy', 'power', 'citizen', 'election', 'participat', 'solidarity'],
  'Economics & inequality' => ['econom', 'inequality', 'poverty', 'capitalis', 'money', 'budget'],
  'Violence & peacebuilding' => ['violence', 'peace', 'conflict', 'safety'],
  'Arts & culture' => ['theatre', 'poem', 'poetry', 'artwork', 'mural', 'song', 'story', 'film', 'photo', 'artivism', 'cultural'],
  'Facilitation & workshops' => ['facilitat', 'workshop', 'energise', 'energiser', 'ice-break', 'group work', 'training for transformation'],
  'Organising & campaigns' => ['campaign', 'organis', 'mobilis', 'movement', 'activist', 'lobby', 'advocacy', 'strategy', 'planning'],
];

$type_map = [
  'Handbook' => ['handbook', 'guide', 'barefoot guide'],
  'Workshop guide' => ['workshop', 'exercise', 'activity', 'session'],
  'Game & energiser' => ['game', 'energise', 'energiser', 'bingo', 'ice-break', 'role play', 'role-play'],
  'Manual' => ['manual', 'training'],
  'Toolkit' => ['toolkit', 'tools for'],
  'Article & paper' => ['article', 'paper', 'journal', 'chapter', 'report', 'lecture', 'thesis'],
  'Poster & artwork' => ['poster', 'artwork', 'image', 'pamphlet', 'calendar', 'cartoon'],
  'Poem & story' => ['poem', 'poetry', 'story', 'brecht'],
  'Film & video' => ['film', 'video', 'documentary'],
  'Audio' => ['audio', 'recording', 'listening', 'podcast'],
  'Link collection' => ['links to', 'link:', 'sites &', 'catalogue online'],
];

$term_ids = [];
$get_term = function (string $vid, string $name) use (&$term_ids): int {
  $key = "$vid:$name";
  if (isset($term_ids[$key])) {
    return $term_ids[$key];
  }
  $found = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => $vid, 'name' => $name]);
  $term = $found ? reset($found) : Term::create(['vid' => $vid, 'name' => $name]);
  if ($term->isNew()) {
    $term->save();
    print "term $vid: $name\n";
  }
  return $term_ids[$key] = (int) $term->id();
};

$storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->execute();
$tagged = 0;
foreach (array_chunk($ids, 50) as $chunk) {
  foreach ($storage->loadMultiple($chunk) as $node) {
    $hay = mb_strtolower($node->getTitle() . ' '
      . ($node->hasField('body') ? (string) $node->get('body')->summary : '') . ' '
      . mb_substr(strip_tags($node->hasField('body') ? (string) $node->get('body')->value : ''), 0, 1500));
    $changed = FALSE;

    if ($node->hasField('field_topics')) {
      $have = array_map(
        static fn(array $v): int => (int) $v['target_id'],
        $node->get('field_topics')->getValue()
      );
      foreach ($topic_map as $topic => $needles) {
        foreach ($needles as $needle) {
          if (str_contains($hay, $needle)) {
            $tid = $get_term('topics', $topic);
            if (!in_array($tid, $have, TRUE)) {
              $node->get('field_topics')->appendItem(['target_id' => $tid]);
              $have[] = $tid;
              $changed = TRUE;
            }
            break;
          }
        }
      }
    }

    if ($node->hasField('field_resource_type')
        && $node->get('field_resource_type')->isEmpty()) {
      foreach ($type_map as $type => $needles) {
        foreach ($needles as $needle) {
          if (str_contains($hay, $needle)) {
            $node->set('field_resource_type', ['target_id' => $get_term('resource_type', $type)]);
            $changed = TRUE;
            break 2;
          }
        }
      }
    }

    if ($changed) {
      $node->save();
      $tagged++;
    }
  }
}
print "tagged $tagged nodes\n";
