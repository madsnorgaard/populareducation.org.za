<?php

/**
 * @file
 * populareducation.org.za - environment-driven settings (no secrets in git).
 */

$databases = [];
$databases['default']['default'] = [
  'database' => getenv('DB_NAME'),
  'username' => getenv('DB_USER'),
  'password' => getenv('DB_PASS'),
  'prefix' => '',
  'host' => getenv('DB_HOST'),
  'port' => getenv('DB_PORT') ?: '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
];

$settings['hash_salt'] = getenv('HASH_SALT');
$settings['update_free_access'] = FALSE;
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

$settings['trusted_host_patterns'][] = getenv('TRUSTED_HOST_PATTERNS') ?: '^(www\.)?populareducation\.org\.za$|^popular\.madsnorgaard\.net$';

$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];
$settings['entity_update_batch_size'] = 100;
$settings['config_sync_directory'] = '../config/sync';

$settings['file_private_path'] = getenv('PRIVATE_FILE_PATH') ?: '/opt/drupal/private';

// Dev-only modules (field_ui, views_ui, pe_migrate, ...) are split out of
// production config.
$config['config_split.config_split.develop']['status'] = strtolower((string) getenv('CONFIG_SPLIT_DEVELOPMENT')) === 'true';

// Harvest output consumed by pe_migrate (develop split only).
// Point at a checkout of populareducation-harvest's output/populareducation.
if (getenv('PE_MIGRATE_SOURCE')) {
  $settings['pe_migrate_source'] = getenv('PE_MIGRATE_SOURCE');
}

// Redis cache backend (when the extension + module are present).
if (extension_loaded('redis') && file_exists($app_root . '/modules/contrib/redis/redis.services.yml')) {
  $settings['container_yamls'][] = $app_root . '/modules/contrib/redis/redis.services.yml';
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = getenv('REDIS_HOST');
  $settings['redis.connection']['port'] = getenv('REDIS_PORT') ?: '6379';
  $settings['cache']['default'] = 'cache.backend.redis';
  $settings['cache_prefix'] = 'populareducation_';
}

// Sitemap URLs must use the canonical public domain, not the request host.
$config['simple_sitemap.settings']['base_url'] = 'https://' . (getenv('DOMAIN_NAME') ?: 'populareducation.org.za');

// Behind Traefik (TLS terminated at the proxy).
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = [@$_SERVER['REMOTE_ADDR']];

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
