<?php

/**
 * @file
 * Phase 1: modules, admin theme, vocabularies, media types.
 *
 * Run once with `ddev drush scr scripts/phase1-structure.php`, then
 * `ddev drush cex -y`. The exported config is the artifact. Idempotent.
 */

use Drupal\media\Entity\MediaType;
use Drupal\taxonomy\Entity\Vocabulary;

$installer = \Drupal::service('module_installer');

// --- Modules -----------------------------------------------------------------
$modules = [
  // Core.
  'node', 'taxonomy', 'menu_ui', 'menu_link_content', 'path', 'options',
  'views', 'block', 'block_content', 'file', 'image', 'media',
  'media_library', 'editor', 'ckeditor5', 'filter', 'dblog', 'datetime',
  // Contrib.
  'admin_toolbar', 'admin_toolbar_tools', 'gin_toolbar', 'token',
  'pathauto', 'redirect', 'metatag', 'metatag_open_graph', 'config_split',
  'search_api', 'search_api_db', 'simple_sitemap',
];
$installer->install($modules);
print "Modules installed\n";

// Gin as admin theme, Olivero placeholder until pe_theme lands.
\Drupal::service('theme_installer')->install(['gin', 'olivero']);
\Drupal::configFactory()->getEditable('system.theme')
  ->set('admin', 'gin')
  ->set('default', 'olivero')
  ->save();
\Drupal::configFactory()->getEditable('node.settings')
  ->set('use_admin_theme', TRUE)
  ->save();
print "Themes set (admin: gin)\n";

// --- Vocabularies ------------------------------------------------------------
$vocabs = [
  'topics' => 'Topics',
  'regions' => 'Regions',
  'resource_type' => 'Resource type',
];
foreach ($vocabs as $vid => $label) {
  if (!Vocabulary::load($vid)) {
    Vocabulary::create(['vid' => $vid, 'name' => $label])->save();
    print "Created vocabulary $vid\n";
  }
}

// --- Media types -------------------------------------------------------------
$media_types = [
  'document' => ['label' => 'Document', 'source' => 'file',
    'extensions' => 'pdf doc docx odt ppt pptx xls xlsx zip epub txt'],
  'image' => ['label' => 'Image', 'source' => 'image', 'extensions' => NULL],
  'audio' => ['label' => 'Audio', 'source' => 'audio_file',
    'extensions' => 'mp3 wav m4a ogg'],
  'remote_video' => ['label' => 'Remote video', 'source' => 'oembed:video',
    'extensions' => NULL],
];
$display_repo = \Drupal::service('entity_display.repository');
foreach ($media_types as $id => $info) {
  if (MediaType::load($id)) {
    continue;
  }
  $type = MediaType::create([
    'id' => $id,
    'label' => $info['label'],
    'source' => $info['source'],
  ]);
  $type->save();
  $source = $type->getSource();
  $source_field = $source->createSourceField($type);
  $source_field->getFieldStorageDefinition()->save();
  if ($info['extensions'] && $source_field->getType() === 'file') {
    $source_field->setSetting('file_extensions', $info['extensions']);
  }
  $source_field->save();
  $type->set('source_configuration', [
    'source_field' => $source_field->getName(),
  ] + $type->getSource()->getConfiguration());
  $type->save();
  $display_repo->getFormDisplay('media', $id)
    ->setComponent($source_field->getName(), ['weight' => 0])
    ->save();
  $display_repo->getViewDisplay('media', $id)
    ->setComponent($source_field->getName(), ['weight' => 0, 'label' => 'hidden'])
    ->save();
  print "Created media type $id (source field {$source_field->getName()})\n";
}

print "Phase 1 done. Now: ddev drush cex -y\n";
