<?php

/**
 * @file
 * Local + CI settings for populareducation.org.za.
 *
 * Production never reads this file: the Docker image (docker/Dockerfile)
 * copies the repo-root settings.php over this path, so everything here is
 * DDEV / CI only. Deliberately NOT #ddev-generated - DDEV must not rewrite
 * it; the DDEV-specific parts live in settings.ddev.php (gitignored).
 */

$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = dirname(DRUPAL_ROOT) . '/private';
$settings['hash_salt'] = getenv('HASH_SALT') ?: 'local-dev-only-not-a-secret';
$settings['update_free_access'] = FALSE;
$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];

// Dev-only modules are split out of production config; CONFIG_SPLIT_DEVELOPMENT
// is set to true in .ddev/config.yaml and false everywhere else.
$config['config_split.config_split.develop']['status'] = strtolower((string) getenv('CONFIG_SPLIT_DEVELOPMENT')) === 'true';

// CI provides DB_* env vars (see .github/workflows/ci.yml); DDEV overrides
// the database below via settings.ddev.php instead.
if (getenv('DB_HOST')) {
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
}

if (getenv('IS_DDEV_PROJECT') == 'true' && file_exists(__DIR__ . '/settings.ddev.php')) {
  include __DIR__ . '/settings.ddev.php';
}

if (file_exists(__DIR__ . '/settings.local.php')) {
  include __DIR__ . '/settings.local.php';
}
