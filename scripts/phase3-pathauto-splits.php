<?php

/**
 * @file
 * Phase 3: pathauto patterns + config_split develop split.
 *
 * Run once with `ddev drush scr scripts/phase3-pathauto-splits.php`, then
 * `ddev drush cex -y`. Idempotent.
 *
 * Clean section paths per bundle; legacy /content/<slug> paths arrive as 301
 * redirects via pe_migrate. Migrated pages whose legacy alias IS the natural
 * one (e.g. /definitions-popular-education) keep it: pe_migrate sets the
 * alias directly and pathauto is configured not to clobber existing aliases
 * on bulk generate.
 */

use Drupal\pathauto\Entity\PathautoPattern;

$patterns = [
  'tool' => ['label' => 'Tools', 'pattern' => '/tools/[node:title]'],
  'organisation' => ['label' => 'Organisations', 'pattern' => '/organisations/[node:title]'],
  'library_item' => ['label' => 'Library', 'pattern' => '/library/[node:title]'],
  'gallery' => ['label' => 'Galleries', 'pattern' => '/galleries/[node:title]'],
  'audio_item' => ['label' => 'Audio', 'pattern' => '/audio/[node:title]'],
  'blog_post' => ['label' => 'Blog', 'pattern' => '/blog/[node:title]'],
  'page' => ['label' => 'Pages', 'pattern' => '/[node:title]'],
];
$weight = 0;
foreach ($patterns as $bundle => $info) {
  $id = 'node_' . $bundle;
  if (PathautoPattern::load($id)) {
    $weight++;
    continue;
  }
  $pattern = PathautoPattern::create([
    'id' => $id,
    'label' => $info['label'],
    'type' => 'canonical_entities:node',
    'pattern' => $info['pattern'],
    'weight' => $weight++,
  ]);
  $pattern->addSelectionCondition([
    'id' => 'entity_bundle:node',
    'bundles' => [$bundle => $bundle],
    'negate' => FALSE,
    'context_mapping' => ['node' => 'node'],
  ]);
  $pattern->save();
  print "Created pathauto pattern $id\n";
}

// Never regenerate over an existing (migrated legacy) alias on update.
\Drupal::configFactory()->getEditable('pathauto.settings')
  ->set('update_action', 0)
  ->save();

// --- config_split: develop ---------------------------------------------------
if (!\Drupal::entityTypeManager()->getStorage('config_split')->load('develop')) {
  \Drupal::entityTypeManager()->getStorage('config_split')->create([
    'id' => 'develop',
    'label' => 'Develop',
    'description' => 'Dev-only modules: UIs and migrations. Never active in production.',
    'folder' => '../config/develop',
    'module' => [
      'field_ui' => 0,
      'views_ui' => 0,
      'migrate' => 0,
      'migrate_drupal' => 0,
      'migrate_plus' => 0,
      'migrate_tools' => 0,
      'pe_migrate' => 0,
    ],
    'status' => TRUE,
  ])->save();
  print "Created config split 'develop'\n";
}

// Install the dev modules locally so they land in the split on export.
\Drupal::service('module_installer')->install([
  'field_ui', 'views_ui', 'migrate', 'migrate_plus', 'migrate_tools',
]);

print "Phase 3 done. Now: ddev drush cex -y\n";
