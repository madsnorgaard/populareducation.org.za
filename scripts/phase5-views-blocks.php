<?php

/**
 * @file
 * Phase 5: section listing views, front + search views, pe_theme blocks.
 *
 * Run once with `ddev drush scr scripts/phase5-views-blocks.php`, then
 * `ddev drush cex -y`. Idempotent.
 */

use Drupal\block\Entity\Block;
use Drupal\views\Entity\View;

$access = ['type' => 'perm', 'options' => ['perm' => 'access content']];
$status_filter = [
  'id' => 'status',
  'table' => 'node_field_data',
  'field' => 'status',
  'value' => '1',
  'plugin_id' => 'boolean',
  'entity_type' => 'node',
  'entity_field' => 'status',
];
$bundle_filter = fn(string $bundle) => [
  'id' => 'type',
  'table' => 'node_field_data',
  'field' => 'type',
  'value' => [$bundle => $bundle],
  'plugin_id' => 'bundle',
  'entity_type' => 'node',
  'entity_field' => 'type',
];
$topic_filter = [
  'id' => 'tid',
  'table' => 'taxonomy_index',
  'field' => 'tid',
  'relationship' => 'none',
  'plugin_id' => 'taxonomy_index_tid',
  'value' => [],
  'vid' => 'topics',
  'type' => 'select',
  'hierarchy' => FALSE,
  'limit' => TRUE,
  'exposed' => TRUE,
  'expose' => [
    'operator_id' => 'tid_op',
    'label' => 'Topic',
    'operator' => 'tid_op',
    'identifier' => 'topic',
    'required' => FALSE,
    'multiple' => FALSE,
    'reduce' => TRUE,
  ],
  'reduce_duplicates' => TRUE,
];
$title_sort = [
  'id' => 'title',
  'table' => 'node_field_data',
  'field' => 'title',
  'order' => 'ASC',
  'plugin_id' => 'standard',
  'entity_type' => 'node',
  'entity_field' => 'title',
];
$created_sort = [
  'id' => 'created',
  'table' => 'node_field_data',
  'field' => 'created',
  'order' => 'DESC',
  'plugin_id' => 'date',
  'entity_type' => 'node',
  'entity_field' => 'created',
];

$make_listing = function (string $id, string $label, string $bundle, string $path, array $sort, bool $topics = TRUE) use ($access, $status_filter, $bundle_filter, $topic_filter) {
  if (View::load($id)) {
    return;
  }
  $filters = ['status' => $status_filter, 'type' => $bundle_filter($bundle)];
  if ($topics) {
    $filters['tid'] = $topic_filter;
  }
  View::create([
    'id' => $id,
    'label' => $label,
    'description' => "$label listing with topic filter.",
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => [
        'display_plugin' => 'default',
        'id' => 'default',
        'display_title' => 'Default',
        'position' => 0,
        'display_options' => [
          'access' => $access,
          'cache' => ['type' => 'tag', 'options' => []],
          'query' => ['type' => 'views_query', 'options' => []],
          'exposed_form' => ['type' => 'basic', 'options' => []],
          'pager' => ['type' => 'full', 'options' => ['offset' => 0, 'items_per_page' => 36]],
          'style' => ['type' => 'default', 'options' => []],
          'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser']],
          'filters' => $filters,
          'sorts' => [$sort['id'] => $sort],
          'title' => $label,
          'empty' => [
            'area' => [
              'id' => 'area',
              'table' => 'views',
              'field' => 'area',
              'plugin_id' => 'text',
              'empty' => TRUE,
              'content' => [
                'value' => '<p>Nothing published here yet. Migrated drafts are being reviewed.</p>',
                'format' => 'basic_html',
              ],
            ],
          ],
        ],
      ],
      'page_1' => [
        'display_plugin' => 'page',
        'id' => 'page_1',
        'display_title' => 'Page',
        'position' => 1,
        'display_options' => ['path' => ltrim($path, '/'), 'display_extenders' => []],
      ],
      'embed_1' => [
        'display_plugin' => 'embed',
        'id' => 'embed_1',
        'display_title' => 'Embed',
        'position' => 2,
        'display_options' => [
          'pager' => ['type' => 'some', 'options' => ['offset' => 0, 'items_per_page' => 6]],
          'defaults' => ['pager' => FALSE],
          'display_extenders' => [],
        ],
      ],
    ],
  ])->save();
  print "Created view $id ($path)\n";
};

$make_listing('tools', 'Tools', 'tool', '/tools', $title_sort);
$make_listing('library', 'Library', 'library_item', '/library', $title_sort);
$make_listing('organisations', 'Organisations', 'organisation', '/organisations', $title_sort);
$make_listing('galleries', 'Galleries', 'gallery', '/galleries', $created_sort);
$make_listing('audio_archive', 'Audio archive', 'audio_item', '/audio', $created_sort);
$make_listing('blog', 'Blog', 'blog_post', '/blog', $created_sort);

// --- Search (combine filter over title + body; no search backend needed) ----
if (!View::load('site_search')) {
  View::create([
    'id' => 'site_search',
    'label' => 'Search',
    'description' => 'Sitewide search over titles and bodies.',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => [
        'display_plugin' => 'default',
        'id' => 'default',
        'display_title' => 'Default',
        'position' => 0,
        'display_options' => [
          'access' => $access,
          'cache' => ['type' => 'tag', 'options' => []],
          'query' => ['type' => 'views_query', 'options' => []],
          'exposed_form' => ['type' => 'basic', 'options' => []],
          'pager' => ['type' => 'full', 'options' => ['offset' => 0, 'items_per_page' => 30]],
          'style' => ['type' => 'default', 'options' => []],
          'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser']],
          'filters' => [
            'status' => $status_filter,
            'combine' => [
              'id' => 'combine',
              'table' => 'views',
              'field' => 'combine',
              'plugin_id' => 'combine',
              'operator' => 'contains',
              'fields' => ['title' => 'title', 'body' => 'body'],
              'exposed' => TRUE,
              'expose' => [
                'operator_id' => 'combine_op',
                'label' => 'Search',
                'operator' => 'combine_op',
                'identifier' => 'q',
                'required' => FALSE,
              ],
            ],
          ],
          'sorts' => ['created' => $created_sort],
          'title' => 'Search',
          'empty' => [
            'area' => [
              'id' => 'area',
              'table' => 'views',
              'field' => 'area',
              'plugin_id' => 'text',
              'empty' => TRUE,
              'content' => [
                'value' => '<p>No results. Try a shorter word, or browse the Tools and Library sections.</p>',
                'format' => 'basic_html',
              ],
            ],
          ],
        ],
      ],
      'page_1' => [
        'display_plugin' => 'page',
        'id' => 'page_1',
        'display_title' => 'Page',
        'position' => 1,
        'display_options' => ['path' => 'search', 'display_extenders' => []],
      ],
    ],
  ])->save();
  print "Created view site_search (/search)\n";
}

// --- Front: recent tools drive the spotlight; path /front is the site
// front page so page--front.html.twig wraps it. --------------------------------
if (!View::load('front_tools')) {
  View::create([
    'id' => 'front_tools',
    'label' => 'Front: tools spotlight',
    'description' => 'Recent tools for the front page.',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => [
        'display_plugin' => 'default',
        'id' => 'default',
        'display_title' => 'Default',
        'position' => 0,
        'display_options' => [
          'access' => $access,
          'cache' => ['type' => 'tag', 'options' => []],
          'query' => ['type' => 'views_query', 'options' => []],
          'exposed_form' => ['type' => 'basic', 'options' => []],
          'pager' => ['type' => 'some', 'options' => ['offset' => 0, 'items_per_page' => 6]],
          'style' => ['type' => 'default', 'options' => []],
          'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser']],
          'filters' => ['status' => $status_filter, 'type' => $bundle_filter('tool')],
          'sorts' => ['created' => $created_sort],
        ],
      ],
      'page_1' => [
        'display_plugin' => 'page',
        'id' => 'page_1',
        'display_title' => 'Page',
        'position' => 1,
        'display_options' => ['path' => 'front', 'display_extenders' => []],
      ],
    ],
  ])->save();
  print "Created view front_tools (/front)\n";
}
\Drupal::configFactory()->getEditable('system.site')
  ->set('page.front', '/front')
  ->save();

// --- pe_theme blocks ---------------------------------------------------------
$blocks = [
  'pe_theme_content' => ['plugin' => 'system_main_block', 'region' => 'content', 'weight' => 0, 'label_display' => '0', 'label' => 'Main content'],
  'pe_theme_page_title' => ['plugin' => 'page_title_block', 'region' => 'content', 'weight' => -10, 'label_display' => '0', 'label' => 'Page title'],
  'pe_theme_messages' => ['plugin' => 'system_messages_block', 'region' => 'content', 'weight' => -20, 'label_display' => '0', 'label' => 'Messages'],
  'pe_theme_main_menu' => ['plugin' => 'system_menu_block:main', 'region' => 'header', 'weight' => 0, 'label_display' => '0', 'label' => 'Main navigation'],
  'pe_theme_footer_menu' => ['plugin' => 'system_menu_block:footer', 'region' => 'footer', 'weight' => 0, 'label_display' => '0', 'label' => 'Footer'],
  'pe_theme_local_tasks' => ['plugin' => 'local_tasks_block', 'region' => 'content', 'weight' => -15, 'label_display' => '0', 'label' => 'Tabs'],
];
foreach ($blocks as $id => $info) {
  if (!Block::load($id)) {
    Block::create([
      'id' => $id,
      'theme' => 'pe_theme',
      'plugin' => $info['plugin'],
      'region' => $info['region'],
      'weight' => $info['weight'],
      'settings' => [
        'id' => $info['plugin'],
        'label' => $info['label'],
        'label_display' => $info['label_display'],
        'provider' => 'system',
      ],
      'visibility' => [],
    ])->save();
    print "Created block $id\n";
  }
}

print "Phase 5 done. Now: ddev drush cex -y\n";
