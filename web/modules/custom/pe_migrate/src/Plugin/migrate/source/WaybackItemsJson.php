<?php

declare(strict_types=1);

namespace Drupal\pe_migrate\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin reading the merged Wayback harvest JSON.
 *
 * Reads the per-page JSON records produced by populareducation-harvest
 * (scripts/merge_and_report.py) from `<source_root>/items/merged/*.json`.
 * The source root comes from the `pe_migrate_source` settings key so the
 * migration YAML never hardcodes paths.
 *
 * Available configuration keys:
 * - kinds: (optional) Array of harvest kinds to include
 *   (tool, organisation, library_item, gallery, audio_item, page,
 *   blog_post). Empty means all content kinds; 'listing' rows are always
 *   excluded.
 * - row_mode: (optional) One of:
 *   - 'node' (default): one row per JSON record, keyed by legacy_path.
 *   - 'media': one row per unique downloaded media file (keyed by sha256)
 *     across the selected records; media_kind is document|image|audio.
 *   - 'redirect': one row per legacy path/nid that must 301 to the migrated
 *     node, keyed by source path.
 */
#[MigrateSource('pe_wayback_items')]
final class WaybackItemsJson extends SourcePluginBase implements ContainerFactoryPluginInterface {

  protected const ROW_MODE_NODE = 'node';
  protected const ROW_MODE_MEDIA = 'media';
  protected const ROW_MODE_REDIRECT = 'redirect';

  /**
   * The pe_migrate logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface|null $migration
   *   The migration this source plugin belongs to.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    if ($migration === NULL) {
      throw new \InvalidArgumentException('The pe_wayback_items source plugin requires a migration.');
    }
    $instance = new static($configuration, $plugin_id, $plugin_definition, $migration);
    $logger_factory = $container->get('logger.factory');
    $instance->logger = $logger_factory->get('pe_migrate');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration this source plugin belongs to.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    if (!in_array($this->rowMode(), [self::ROW_MODE_NODE, self::ROW_MODE_MEDIA, self::ROW_MODE_REDIRECT], TRUE)) {
      throw new \InvalidArgumentException(sprintf("Invalid row_mode '%s' for pe_wayback_items; use 'node', 'media' or 'redirect'.", $this->rowMode()));
    }
  }

  /**
   * Returns the configured row mode.
   */
  protected function rowMode(): string {
    $row_mode = $this->configuration['row_mode'] ?? self::ROW_MODE_NODE;
    return is_string($row_mode) ? $row_mode : '';
  }

  /**
   * Returns the harvest kinds to include (empty means all content kinds).
   *
   * @return string[]
   *   The kinds.
   */
  protected function kinds(): array {
    $kinds = $this->configuration['kinds'] ?? [];
    return is_array($kinds) ? array_values(array_map('strval', $kinds)) : [];
  }

  /**
   * Returns the harvest source root (no trailing slash).
   */
  protected function sourceRoot(): string {
    $root = $this->configuration['source_root']
      ?? Settings::get('pe_migrate_source', '/var/www/html/private/harvest');
    return rtrim(is_string($root) ? $root : '', '/');
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \Iterator {
    $rows = match ($this->rowMode()) {
      self::ROW_MODE_MEDIA => $this->buildMediaRows(),
      self::ROW_MODE_REDIRECT => $this->buildRedirectRows(),
      default => $this->buildNodeRows(),
    };
    return new \ArrayIterator($rows);
  }

  /**
   * Loads, filters and sorts the merged harvest JSON records.
   *
   * @return array<int, array<string, mixed>>
   *   Decoded records, sorted by legacy path.
   */
  protected function loadRecords(): array {
    $dir = $this->sourceRoot() . '/items/merged';
    if (!is_dir($dir)) {
      throw new MigrateException(sprintf('Harvest directory %s not found. Stage the harvest under the source root and/or set $settings[\'pe_migrate_source\'] - see pe_migrate/README.md.', $dir));
    }
    $files = glob($dir . '/*.json') ?: [];
    sort($files);
    $kinds = $this->kinds();
    $records = [];
    foreach ($files as $file) {
      $contents = file_get_contents($file);
      if ($contents === FALSE) {
        throw new MigrateException(sprintf('Unable to read harvest record %s.', $file));
      }
      $record = json_decode($contents, TRUE);
      if (!is_array($record)) {
        throw new MigrateException(sprintf('Harvest record %s is not valid JSON.', $file));
      }
      $kind = (string) ($record['kind'] ?? '');
      if ($kind === 'listing') {
        continue;
      }
      if ($kinds && !in_array($kind, $kinds, TRUE)) {
        continue;
      }
      $records[] = $record;
    }
    return $records;
  }

  /**
   * Collects downloaded media refs of a record, bucketed by media kind.
   *
   * @param array<string, mixed> $record
   *   The harvest record.
   *
   * @return array<int, array<string, mixed>>
   *   Media refs with sha256/local_path plus a resolved media_kind.
   */
  protected function mediaRefs(array $record): array {
    $refs = [];
    foreach (['images', 'files'] as $bucket) {
      $list = is_array($record[$bucket] ?? NULL) ? $record[$bucket] : [];
      foreach ($list as $ref) {
        $usable = is_array($ref) && !empty($ref['downloaded'])
          && !empty($ref['sha256']) && !empty($ref['local_path']);
        if (!$usable) {
          continue;
        }
        $kind = (string) ($ref['kind'] ?? 'file');
        $ref['media_kind'] = match ($kind) {
          'image' => 'image',
          'audio' => 'audio',
          default => 'document',
        };
        $refs[] = $ref;
      }
    }
    return $refs;
  }

  /**
   * Builds node-mode rows: one row per JSON record.
   *
   * @return array<int, array<string, mixed>>
   *   The source rows.
   */
  protected function buildNodeRows(): array {
    $rows = [];
    foreach ($this->loadRecords() as $record) {
      $doc_refs = $image_refs = $audio_refs = [];
      foreach ($this->mediaRefs($record) as $ref) {
        $entry = ['sha256' => $ref['sha256']];
        match ($ref['media_kind']) {
          'image' => $image_refs[] = $entry,
          'audio' => $audio_refs[] = $entry,
          default => $doc_refs[] = $entry,
        };
      }
      $legacy_path = (string) ($record['identifiers']['legacy_path'] ?? '/' . $record['source_id']);
      $active_raw = strtolower((string) ($record['extra']['field_active'] ?? ''));
      $inactive_words = ['no', '0', 'false', 'inactive', 'no longer active'];
      $active = $active_raw === ''
        ? NULL
        : (int) !in_array($active_raw, $inactive_words, TRUE);
      $rows[] = [
        'legacy_path' => $legacy_path,
        'legacy_nid' => $record['identifiers']['legacy_nid'] ?? NULL,
        'title' => html_entity_decode((string) ($record['title'] ?? ''), ENT_QUOTES | ENT_HTML5),
        'kind' => $record['kind'] ?? NULL,
        'body' => $record['body'] ?? NULL,
        'summary' => $record['summary'] ?? NULL,
        'date_iso' => $record['date_iso'] ?? NULL,
        'subjects' => array_values(array_filter(array_map('strval', $record['subjects'] ?? []))),
        'active' => $active,
        'doc_refs' => $doc_refs,
        'image_refs' => $image_refs,
        'audio_refs' => $audio_refs,
        'alias' => $legacy_path,
      ];
    }
    return $rows;
  }

  /**
   * Builds media-mode rows: one row per unique downloaded file (sha256).
   *
   * Filename collisions with differing content get an 8-char sha prefix, so
   * destination URIs are collision-safe and deterministic.
   *
   * @return array<int, array<string, mixed>>
   *   The source rows.
   */
  protected function buildMediaRows(): array {
    $rows = [];
    $seen_sha = [];
    $filename_sha = [];
    $root = $this->sourceRoot();
    $media_kinds = $this->configuration['media_kinds'] ?? [];
    $media_kinds = is_array($media_kinds) ? array_map('strval', $media_kinds) : [];
    foreach ($this->loadRecords() as $record) {
      $title = html_entity_decode((string) ($record['title'] ?? ''), ENT_QUOTES | ENT_HTML5);
      foreach ($this->mediaRefs($record) as $ref) {
        if ($media_kinds && !in_array($ref['media_kind'], $media_kinds, TRUE)) {
          continue;
        }
        $sha256 = (string) $ref['sha256'];
        if (isset($seen_sha[$sha256])) {
          continue;
        }
        $seen_sha[$sha256] = TRUE;
        $local_path = (string) $ref['local_path'];
        $filename = (string) ($ref['filename'] ?? '') ?: preg_replace('/^[0-9a-f]{16}_/', '', basename($local_path));
        if (isset($filename_sha[$filename]) && $filename_sha[$filename] !== $sha256) {
          $destination_filename = substr($sha256, 0, 8) . '_' . $filename;
        }
        else {
          $filename_sha[$filename] = $sha256;
          $destination_filename = $filename;
        }
        $caption = trim((string) ($ref['caption'] ?? ''));
        $rows[] = [
          'sha256' => $sha256,
          'filename' => $filename,
          'mime' => $ref['mime'] ?? NULL,
          'media_kind' => $ref['media_kind'],
          'caption' => $caption !== '' ? $caption : NULL,
          'parent_title' => $title,
          'source_full_path' => $root . '/' . ltrim($local_path, '/'),
          'destination_uri' => 'public://legacy/' . $destination_filename,
        ];
      }
    }
    return $rows;
  }

  /**
   * Builds redirect-mode rows.
   *
   * One row per record: the legacy alias path, skipped for 'page' records
   * (they keep their exact legacy alias - a redirect would loop). Legacy
   * /node/N shortlinks are deliberately NOT redirected: the legacy nid
   * namespace overlaps the new site's real /node/N paths, so such
   * redirects hijack live nodes and create wrong redirect chains.
   *
   * @return array<int, array<string, mixed>>
   *   The source rows.
   */
  protected function buildRedirectRows(): array {
    $rows = [];
    foreach ($this->loadRecords() as $record) {
      $legacy_path = (string) ($record['identifiers']['legacy_path'] ?? '');
      $kind = (string) ($record['kind'] ?? '');
      if ($legacy_path === '' || $legacy_path === '/' || $kind === 'page') {
        continue;
      }
      $rows[] = [
        'source_path' => ltrim($legacy_path, '/'),
        'legacy_path' => $legacy_path,
      ];
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, string>
   *   Field machine names mapped to their descriptions.
   */
  public function fields(): array {
    return match ($this->rowMode()) {
      self::ROW_MODE_MEDIA => [
        'sha256' => 'SHA-256 of the file (row key).',
        'filename' => 'Original filename from the legacy site.',
        'mime' => 'MIME type.',
        'media_kind' => 'document | image | audio.',
        'caption' => 'Caption/alt text harvested with the file.',
        'parent_title' => 'Title of the page the file was found on.',
        'source_full_path' => 'Absolute path to the harvested file.',
        'destination_uri' => 'Collision-safe public:// destination URI.',
      ],
      self::ROW_MODE_REDIRECT => [
        'source_path' => 'Legacy path without leading slash (row key).',
        'legacy_path' => 'Legacy path of the migrated record.',
      ],
      default => [
        'legacy_path' => 'Legacy path on the D7 site (row key).',
        'legacy_nid' => 'Legacy node id when known.',
        'title' => 'Page title.',
        'kind' => 'Harvest kind (bundle).',
        'body' => 'Cleaned body HTML.',
        'summary' => 'Plain-text summary.',
        'date_iso' => 'ISO date when known.',
        'subjects' => 'Taxonomy term labels.',
        'active' => 'Organisation still active (bool).',
        'doc_refs' => 'Attached documents [{sha256}].',
        'image_refs' => 'Attached images [{sha256}].',
        'audio_refs' => 'Attached audio [{sha256}].',
        'alias' => 'Legacy path, used as alias for page records.',
      ],
    };
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, array<string, string>>
   *   The source id definition.
   */
  public function getIds(): array {
    return match ($this->rowMode()) {
      self::ROW_MODE_MEDIA => ['sha256' => ['type' => 'string']],
      self::ROW_MODE_REDIRECT => ['source_path' => ['type' => 'string']],
      default => ['legacy_path' => ['type' => 'string']],
    };
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return sprintf('pe_wayback_items (%s)', $this->rowMode());
  }

}
