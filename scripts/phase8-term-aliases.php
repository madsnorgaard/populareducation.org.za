<?php

/**
 * @file
 * Phase 8: clean taxonomy URLs. /taxonomy/term/N -> /topics/<name> etc.
 *
 * `ddev drush scr scripts/phase8-term-aliases.php`, then `ddev drush cex -y`.
 * Idempotent. Old /taxonomy/term/N paths keep working (alias is additive).
 */

use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\pathauto\PathautoGeneratorInterface;

$patterns = [
  'topics' => ['label' => 'Topics terms', 'pattern' => '/topics/[term:name]'],
  'regions' => ['label' => 'Regions terms', 'pattern' => '/regions/[term:name]'],
  'resource_type' => ['label' => 'Resource type terms', 'pattern' => '/resource-type/[term:name]'],
];
$weight = 0;
foreach ($patterns as $vid => $info) {
  $id = 'term_' . $vid;
  if (PathautoPattern::load($id)) {
    $weight++;
    continue;
  }
  $pattern = PathautoPattern::create([
    'id' => $id,
    'label' => $info['label'],
    'type' => 'canonical_entities:taxonomy_term',
    'pattern' => $info['pattern'],
    'weight' => $weight++,
  ]);
  $pattern->addSelectionCondition([
    'id' => 'entity_bundle:taxonomy_term',
    'bundles' => [$vid => $vid],
    'negate' => FALSE,
    'context_mapping' => ['taxonomy_term' => 'taxonomy_term'],
  ]);
  $pattern->save();
  print "pattern $id -> {$info['pattern']}\n";
}

$generator = \Drupal::service('pathauto.generator');
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$n = 0;
foreach ($storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->execute()) as $term) {
  if ($generator->updateEntityAlias($term, 'bulkupdate', ['force' => FALSE])) {
    $n++;
  }
}
print "aliases generated: $n\n";
