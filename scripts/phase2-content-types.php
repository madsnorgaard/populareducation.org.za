<?php

/**
 * @file
 * Phase 2: content types + shared field storages for the rebuilt site.
 *
 * Run once with `ddev drush scr scripts/phase2-content-types.php`, then
 * `ddev drush cex -y`. Idempotent.
 *
 * Bundles mirror what the Drupal 7 site actually held (see
 * docs/CONTENT-MODEL.md): tools for grassroots education and campaigning,
 * an organisations directory, a library, photo galleries, the PEN 2018
 * audio archive, plain pages, and blog posts.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

$display_repo = \Drupal::service('entity_display.repository');

// --- Content types -----------------------------------------------------------
$types = [
  'tool' => [
    'name' => 'Tool',
    'description' => 'A practical popular-education resource: workshop guide, game, handbook, exercise.',
  ],
  'organisation' => [
    'name' => 'Organisation',
    'description' => 'An organisation doing popular education - the movement directory.',
  ],
  'library_item' => [
    'name' => 'Library item',
    'description' => 'A text from the library: article, paper, book chapter, pamphlet.',
  ],
  'gallery' => [
    'name' => 'Gallery',
    'description' => 'A photo gallery from an event, workshop or campaign.',
  ],
  'audio_item' => [
    'name' => 'Audio item',
    'description' => 'A recording, e.g. from the Listening into Popular Education archive (PEN 2018).',
  ],
  'page' => [
    'name' => 'Page',
    'description' => 'A plain page: About, Definitions, section landing pages.',
  ],
  'blog_post' => [
    'name' => 'Blog post',
    'description' => 'A dated post or report.',
  ],
];
foreach ($types as $id => $info) {
  if (!NodeType::load($id)) {
    NodeType::create([
      'type' => $id,
      'name' => $info['name'],
      'description' => $info['description'],
      'new_revision' => TRUE,
      'preview_mode' => 1,
      'display_submitted' => FALSE,
    ])->save();
    print "Created content type $id\n";
  }
}
// Body field (node_add_body_field() is deprecated; minimal profile ships no
// body storage at all).
if (!FieldStorageConfig::loadByName('node', 'body')) {
  FieldStorageConfig::create([
    'field_name' => 'body',
    'entity_type' => 'node',
    'type' => 'text_with_summary',
    'cardinality' => 1,
  ])->save();
  print "Created storage node.body\n";
}
foreach (array_keys($types) as $id) {
  if (!FieldConfig::loadByName('node', $id, 'body')) {
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => $id,
      'label' => 'Body',
      'settings' => ['display_summary' => TRUE, 'allowed_formats' => []],
    ])->save();
    $display_repo->getFormDisplay('node', $id)
      ->setComponent('body', ['type' => 'text_textarea_with_summary'])
      ->save();
    $display_repo->getViewDisplay('node', $id)
      ->setComponent('body', ['label' => 'hidden', 'type' => 'text_default'])
      ->save();
    print "Created $id.body\n";
  }
}

// --- Shared field storages ---------------------------------------------------
$storages = [
  'field_topics' => ['type' => 'entity_reference', 'settings' => ['target_type' => 'taxonomy_term'], 'cardinality' => -1],
  'field_regions' => ['type' => 'entity_reference', 'settings' => ['target_type' => 'taxonomy_term'], 'cardinality' => -1],
  'field_resource_type' => ['type' => 'entity_reference', 'settings' => ['target_type' => 'taxonomy_term'], 'cardinality' => 1],
  'field_documents' => ['type' => 'entity_reference', 'settings' => ['target_type' => 'media'], 'cardinality' => -1],
  'field_images' => ['type' => 'entity_reference', 'settings' => ['target_type' => 'media'], 'cardinality' => -1],
  'field_audio' => ['type' => 'entity_reference', 'settings' => ['target_type' => 'media'], 'cardinality' => 1],
  'field_active' => ['type' => 'boolean', 'settings' => [], 'cardinality' => 1],
  'field_website' => ['type' => 'link', 'settings' => [], 'cardinality' => 1],
  'field_external_link' => ['type' => 'link', 'settings' => [], 'cardinality' => 1],
  'field_authors' => ['type' => 'string', 'settings' => ['max_length' => 255], 'cardinality' => -1],
  'field_speakers' => ['type' => 'string', 'settings' => ['max_length' => 255], 'cardinality' => -1],
  'field_event' => ['type' => 'string', 'settings' => ['max_length' => 255], 'cardinality' => 1],
  'field_source_org' => ['type' => 'string', 'settings' => ['max_length' => 255], 'cardinality' => 1],
  'field_legacy_id' => ['type' => 'string', 'settings' => ['max_length' => 255], 'cardinality' => 1],
];
foreach ($storages as $name => $def) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $def['type'],
      'settings' => $def['settings'],
      'cardinality' => $def['cardinality'],
    ])->save();
    print "Created storage node.$name\n";
  }
}

// link module needed for link fields.
\Drupal::service('module_installer')->install(['link']);

// --- Field instances ---------------------------------------------------------
$term_handler = fn(string $vid) => [
  'handler' => 'default:taxonomy_term',
  'handler_settings' => ['target_bundles' => [$vid => $vid], 'auto_create' => TRUE],
];
$media_handler = fn(string $bundle) => [
  'handler' => 'default:media',
  'handler_settings' => ['target_bundles' => [$bundle => $bundle]],
];
$instances = [
  'field_topics' => ['label' => 'Topics', 'settings' => $term_handler('topics')],
  'field_regions' => ['label' => 'Regions', 'settings' => $term_handler('regions')],
  'field_resource_type' => ['label' => 'Resource type', 'settings' => $term_handler('resource_type')],
  'field_documents' => ['label' => 'Documents', 'settings' => $media_handler('document')],
  'field_images' => ['label' => 'Images', 'settings' => $media_handler('image')],
  'field_audio' => ['label' => 'Audio', 'settings' => $media_handler('audio')],
  'field_active' => [
    'label' => 'Still active',
    'description' => 'Is this organisation still active?',
    'settings' => ['on_label' => 'Active', 'off_label' => 'No longer active'],
  ],
  'field_website' => ['label' => 'Website', 'settings' => []],
  'field_external_link' => ['label' => 'External link', 'settings' => []],
  'field_authors' => ['label' => 'Authors', 'settings' => []],
  'field_speakers' => ['label' => 'Speakers', 'settings' => []],
  'field_event' => ['label' => 'Event', 'settings' => []],
  'field_source_org' => ['label' => 'Source organisation', 'settings' => []],
  'field_legacy_id' => [
    'label' => 'Legacy ID',
    'description' => 'Original path on the Drupal 7 site. Set by pe_migrate; do not edit.',
    'settings' => [],
  ],
];
$bundles = [
  'tool' => ['field_topics', 'field_resource_type', 'field_documents', 'field_images', 'field_source_org', 'field_external_link', 'field_legacy_id'],
  'organisation' => ['field_topics', 'field_regions', 'field_active', 'field_website', 'field_images', 'field_documents', 'field_legacy_id'],
  'library_item' => ['field_topics', 'field_resource_type', 'field_documents', 'field_authors', 'field_external_link', 'field_legacy_id'],
  'gallery' => ['field_topics', 'field_images', 'field_legacy_id'],
  'audio_item' => ['field_topics', 'field_audio', 'field_speakers', 'field_event', 'field_legacy_id'],
  'page' => ['field_documents', 'field_images', 'field_legacy_id'],
  'blog_post' => ['field_topics', 'field_images', 'field_documents', 'field_legacy_id'],
];
foreach ($bundles as $bundle => $fields) {
  foreach ($fields as $name) {
    if (!FieldConfig::loadByName('node', $bundle, $name)) {
      $def = $instances[$name];
      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => $def['label'],
        'description' => $def['description'] ?? '',
        'required' => $def['required'] ?? FALSE,
        'settings' => $def['settings'],
      ])->save();
      print "Created $bundle.$name\n";
    }
  }
  // Form display: sensible widgets, legacy id hidden.
  $form = $display_repo->getFormDisplay('node', $bundle);
  foreach ($fields as $name) {
    if ($name === 'field_legacy_id') {
      $form->removeComponent($name);
      continue;
    }
    $widget = match (TRUE) {
      in_array($name, ['field_documents', 'field_images', 'field_audio']) => 'media_library_widget',
      in_array($name, ['field_topics', 'field_regions', 'field_resource_type']) => 'entity_reference_autocomplete_tags',
      default => NULL,
    };
    $form->setComponent($name, $widget ? ['type' => $widget] : []);
  }
  $form->save();
}

print "Phase 2 done. Now: ddev drush cex -y\n";
