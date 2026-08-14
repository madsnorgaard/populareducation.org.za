<?php

/**
 * @file
 * Phase 4: text formats + editor, field tweaks, pe_migrate enablement.
 *
 * Run once with `ddev drush scr scripts/phase4-editorial.php`, then
 * `ddev drush cex -y`. Idempotent.
 */

use Drupal\editor\Entity\Editor;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;

// --- Text formats ------------------------------------------------------------
if (!FilterFormat::load('basic_html')) {
  FilterFormat::create([
    'format' => 'basic_html',
    'name' => 'Basic HTML',
    'weight' => 0,
    'filters' => [
      'filter_html' => [
        'status' => TRUE,
        'weight' => -10,
        'settings' => [
          'allowed_html' => '<a href hreflang> <em> <strong> <cite> <blockquote cite> <code> <ul type> <ol start type> <li> <dl> <dt> <dd> <h2 id> <h3 id> <h4 id> <h5 id> <h6 id> <p> <br> <span> <img src alt height width data-entity-type data-entity-uuid data-align data-caption> <hr>',
          'filter_html_help' => FALSE,
          'filter_html_nofollow' => FALSE,
        ],
      ],
      'filter_align' => ['status' => TRUE],
      'filter_caption' => ['status' => TRUE],
      'filter_url' => ['status' => TRUE, 'settings' => ['filter_url_length' => 72]],
    ],
  ])->save();
  print "Created basic_html format\n";
}
if (!FilterFormat::load('full_html')) {
  FilterFormat::create([
    'format' => 'full_html',
    'name' => 'Full HTML',
    'weight' => 1,
    'filters' => [
      'filter_align' => ['status' => TRUE],
      'filter_caption' => ['status' => TRUE],
    ],
  ])->save();
  print "Created full_html format\n";
}
if (!Editor::load('basic_html')) {
  Editor::create([
    'format' => 'basic_html',
    'editor' => 'ckeditor5',
    'settings' => [
      'toolbar' => [
        'items' => [
          'heading', 'bold', 'italic', '|', 'link', 'bulletedList',
          'numberedList', 'blockQuote', '|', 'sourceEditing',
        ],
      ],
      'plugins' => [
        'ckeditor5_heading' => [
          'enabled_headings' => ['heading2', 'heading3', 'heading4'],
        ],
        'ckeditor5_list' => [
          'properties' => ['reversed' => FALSE, 'startIndex' => TRUE],
          'multiBlock' => TRUE,
        ],
        'ckeditor5_sourceEditing' => ['allowed_tags' => []],
      ],
    ],
    'image_upload' => ['status' => FALSE],
  ])->save();
  print "Created basic_html CKEditor5 config\n";
}

// --- Field tweaks ------------------------------------------------------------
// An audio page from the PEN 2018 archive can carry many recordings.
$audio = FieldStorageConfig::loadByName('node', 'field_audio');
if ($audio && $audio->getCardinality() !== -1) {
  $audio->setCardinality(-1);
  $audio->save();
  print "field_audio cardinality -> unlimited\n";
}

// --- pe_migrate (lands in the develop split on export) -----------------------
\Drupal::service('module_installer')->install(['pe_migrate']);
print "pe_migrate enabled\n";

print "Phase 4 done. Now: ddev drush cex -y\n";
