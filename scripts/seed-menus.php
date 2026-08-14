<?php

/**
 * @file
 * Seed the main + footer menus. Content entities, safe to re-run.
 *
 * `ddev drush scr scripts/seed-menus.php`
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

if (!Menu::load('footer')) {
  Menu::create(['id' => 'footer', 'label' => 'Footer'])->save();
  print "Created footer menu\n";
}

$links = [
  ['main', 'Tools', 'internal:/tools', 0],
  ['main', 'Library', 'internal:/library', 1],
  ['main', 'Organisations', 'internal:/organisations', 2],
  ['main', 'Galleries', 'internal:/galleries', 3],
  ['main', 'Audio', 'internal:/audio', 4],
  ['main', 'Blog', 'internal:/blog', 5],
  ['main', 'About', 'internal:/content/about-us', 6],
  ['footer', 'About us', 'internal:/content/about-us', 0],
  ['footer', 'Contact', 'internal:/content/contact-us', 1],
  ['footer', 'What is popular education?', 'internal:/definitions-popular-education', 2],
  ['footer', 'How this site was rebuilt', 'https://github.com/madsnorgaard/populareducation-harvest', 3],
];
$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach ($links as [$menu, $title, $uri, $weight]) {
  $existing = $storage->loadByProperties(['menu_name' => $menu, 'title' => $title]);
  if ($existing) {
    continue;
  }
  MenuLinkContent::create([
    'menu_name' => $menu,
    'title' => $title,
    'link' => ['uri' => $uri],
    'weight' => $weight,
    'expanded' => FALSE,
  ])->save();
  print "Added $menu: $title\n";
}
print "Menus seeded\n";
