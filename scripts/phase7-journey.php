<?php

/**
 * @file
 * Phase 7: the learning journey. Audio archive across bundles, section
 * intros on every listing, and the Start here page + menu link.
 *
 * `ddev drush scr scripts/phase7-journey.php`, then `ddev drush cex -y`.
 * Idempotent.
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\views\Entity\View;

// --- Audio archive: recordings live on many bundles (organisations, library
// items, pages), so /audio lists any node carrying audio. -------------------
$view = View::load('audio_archive');
if ($view) {
  $display = $view->get('display');
  $filters = $display['default']['display_options']['filters'];
  unset($filters['type']);
  $filters['field_audio_target_id'] = [
    'id' => 'field_audio_target_id',
    'table' => 'node__field_audio',
    'field' => 'field_audio_target_id',
    'operator' => 'not empty',
    'value' => [],
    'plugin_id' => 'numeric',
  ];
  $display['default']['display_options']['filters'] = $filters;
  $display['default']['display_options']['query'] = [
    'type' => 'views_query',
    'options' => ['distinct' => TRUE],
  ];
  $view->set('display', $display);
  $view->save();
  print "audio_archive now lists any node with recordings\n";
}

// --- Section intros: one sentence saying where you are in the journey. -----
$intros = [
  'tools' => 'Start with a tool: every entry is something you can run - a game to open a meeting, a guide to plan a campaign, a handbook to go deeper. Filter by topic to match your struggle.',
  'library' => 'The reading room: articles, papers, pamphlets and histories of popular education, in South Africa and beyond. Take what the tools started further.',
  'organisations' => 'Nobody does this alone. These are the organisations doing popular education - find the ones near you or working on your issue.',
  'galleries' => 'What it looks like when people learn together: workshops, murals, theatre, marches - the movement in pictures.',
  'audio_archive' => 'Listen in: recordings of interviews and gatherings, kept with the pages they belong to.',
  'blog' => 'Dispatches from the project as it happened: launches, lectures, burning issues.',
];
foreach ($intros as $view_id => $text) {
  $view = View::load($view_id);
  if (!$view) {
    continue;
  }
  $display = $view->get('display');
  $display['default']['display_options']['header'] = [
    'area' => [
      'id' => 'area',
      'table' => 'views',
      'field' => 'area',
      'plugin_id' => 'text',
      'empty' => TRUE,
      'content' => [
        'value' => '<p class="section-intro">' . $text . '</p>',
        'format' => 'basic_html',
      ],
    ],
  ];
  $view->set('display', $display);
  $view->save();
  print "intro set on $view_id\n";
}

// --- Start here: the front door of the journey. ----------------------------
$existing = \Drupal::entityTypeManager()->getStorage('node')
  ->loadByProperties(['title' => 'Start here', 'type' => 'page']);
if (!$existing) {
  $body = <<<HTML
<p>Popular education is learning that starts from people's own lives and builds collective power to change them. This site keeps the South African traditions of that work in usable order. Here is one way in.</p>
<h2>1. Get the idea</h2>
<p>Read <a href="/tools/what-popular-education">What is popular education?</a> - one page, with a printable PDF - then browse the <a href="/definitions-popular-education">definitions</a> educators around the world have given it.</p>
<h2>2. Run something</h2>
<p>Pick a <a href="/tools">tool</a> and use it. Open a meeting with <a href="/tools/100-ways-energise-groups-games-use-workshops-meetings-and-community">an energiser</a>, plan action with <a href="/tools/how-plan-campaign-v1">How to plan a campaign</a>, or work through a full handbook. Everything downloads free.</p>
<h2>3. Go deeper</h2>
<p>The <a href="/library">library</a> holds the articles, manuals and histories behind the practice - filter by the topic you are working on.</p>
<h2>4. Find your people</h2>
<p>Popular education happens in organisations and movements. The <a href="/organisations">directory</a> lists who is doing the work; the <a href="/galleries">galleries</a> and <a href="/audio">recordings</a> show and tell what it is like.</p>
<p>This archive was rebuilt from the original populareducation.org.za so the work can continue. Use it, copy it, pass it on.</p>
HTML;
  $node = Node::create([
    'type' => 'page',
    'title' => 'Start here',
    'body' => ['value' => $body, 'format' => 'basic_html'],
    'status' => 1,
    'uid' => 1,
    'path' => ['alias' => '/start-here', 'pathauto' => 0],
  ]);
  $node->save();
  print "Start here page created at /start-here\n";
}

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
if (!$storage->loadByProperties(['menu_name' => 'main', 'title' => 'Start here'])) {
  MenuLinkContent::create([
    'menu_name' => 'main',
    'title' => 'Start here',
    'link' => ['uri' => 'internal:/start-here'],
    'weight' => -1,
  ])->save();
  print "Start here menu link added\n";
}

print "Phase 7 done. Now: ddev drush cex -y\n";
