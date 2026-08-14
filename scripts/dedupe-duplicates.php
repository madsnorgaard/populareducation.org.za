<?php

/**
 * @file
 * Merge duplicate nodes left by D7 slug variants (foo vs foo-0).
 *
 * Keeps the richer node of each same-title-same-bundle group, unpublishes
 * the rest, and repoints/creates redirects so every legacy path and alias
 * still lands on the keeper. `ddev drush scr scripts/dedupe-duplicates.php`
 * - idempotent.
 */

use Drupal\redirect\Entity\Redirect;

$db = \Drupal::database();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$redirect_storage = \Drupal::entityTypeManager()->getStorage('redirect');
$alias_manager = \Drupal::service('path_alias.manager');

$groups = $db->query(
  "SELECT title, type, GROUP_CONCAT(nid ORDER BY nid) nids
   FROM node_field_data WHERE status = 1
   GROUP BY title, type HAVING COUNT(*) > 1"
)->fetchAll();

$weight = function ($node): int {
  $w = mb_strlen($node->hasField('body') ? (string) $node->get('body')->value : '');
  foreach (['field_documents', 'field_images', 'field_audio'] as $f) {
    if ($node->hasField($f)) {
      $w += 800 * count($node->get($f)->getValue());
    }
  }
  return $w;
};

foreach ($groups as $g) {
  $nodes = $storage->loadMultiple(explode(',', $g->nids));
  uasort($nodes, static fn($a, $b) => $weight($b) <=> $weight($a));
  $keeper = array_shift($nodes);
  $keeper_uri = 'internal:/node/' . $keeper->id();
  print "[{$g->type}] {$g->title}: keeping {$keeper->id()}\n";

  foreach ($nodes as $loser) {
    // Old links must keep working: repoint redirects that target the loser.
    $targeting = $redirect_storage->loadByProperties([
      'redirect_redirect__uri' => 'internal:/node/' . $loser->id(),
    ]);
    foreach ($targeting as $r) {
      $r->setRedirect('/node/' . $keeper->id());
      $r->save();
      print "  repointed redirect " . $r->getSourcePathWithQuery() . "\n";
    }
    // The loser's own alias becomes a redirect to the keeper.
    $alias = $alias_manager->getAliasByPath('/node/' . $loser->id());
    $source = ltrim($alias, '/');
    if ($alias !== '/node/' . $loser->id()
        && !$redirect_storage->loadByProperties(['redirect_source__path' => $source])) {
      Redirect::create([
        'redirect_source' => ['path' => $source, 'query' => []],
        'redirect_redirect' => ['uri' => $keeper_uri],
        'status_code' => 301,
        'language' => 'und',
      ])->save();
      print "  redirect /$source -> keeper\n";
    }
    $loser->set('status', 0);
    $loser->save();
    // The alias must go, or it keeps resolving (403) ahead of the redirect.
    $aliases = \Drupal::entityTypeManager()->getStorage('path_alias')
      ->loadByProperties(['path' => '/node/' . $loser->id()]);
    foreach ($aliases as $a) {
      $a->delete();
    }
    print "  unpublished {$loser->id()} (" . count($aliases) . " alias removed)\n";
  }
}
print "done\n";
