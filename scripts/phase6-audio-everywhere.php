<?php

/**
 * @file
 * Phase 6: recordings can hang off any bundle (the D7 site attached MP3s
 * to organisation and plain pages, e.g. Junction Ave Theatre Company).
 *
 * Run once with `ddev drush scr scripts/phase6-audio-everywhere.php`, then
 * `ddev drush cex -y`. Idempotent.
 */

use Drupal\field\Entity\FieldConfig;

$display_repo = \Drupal::service('entity_display.repository');
$bundles = ['organisation', 'page', 'tool', 'library_item', 'blog_post', 'gallery'];
foreach ($bundles as $bundle) {
  if (!FieldConfig::loadByName('node', $bundle, 'field_audio')) {
    FieldConfig::create([
      'field_name' => 'field_audio',
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => 'Audio',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => ['target_bundles' => ['audio' => 'audio']],
      ],
    ])->save();
    $display_repo->getFormDisplay('node', $bundle)
      ->setComponent('field_audio', ['type' => 'media_library_widget'])
      ->save();
    print "Added field_audio to $bundle\n";
  }
}
print "Phase 6 done. Now: ddev drush cex -y\n";
