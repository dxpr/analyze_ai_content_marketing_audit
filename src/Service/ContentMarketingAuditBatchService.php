<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_content_marketing_audit\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Service for batch processing content marketing audit analysis.
 */
final class ContentMarketingAuditBatchService {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ContentMarketingAuditStorageService $storageService,
  ) {}

  /**
   * Gets entities that need content marketing audit analysis.
   *
   * @param array $entity_bundles
   *   Array of entity_type:bundle strings.
   * @param bool $force_refresh
   *   Whether to include entities with existing analysis.
   * @param int $limit
   *   Maximum number of entities to return.
   *
   * @return array<int, array{entity_type: string, entity_id: int|string, bundle: string}>
   *   Array of entity info arrays.
   */
  public function getEntitiesForAnalysis(array $entity_bundles, bool $force_refresh = FALSE, int $limit = 0): array {
    $entities = [];

    foreach ($entity_bundles as $entity_bundle) {
      [$entity_type_id, $bundle] = explode(':', $entity_bundle);

      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $bundle)
      // Only published content.
        ->condition('status', 1);

      if (!$force_refresh) {
        // Exclude entities that already have recent analysis.
        $analyzed_ids = $this->getAnalyzedEntityIds($entity_type_id, $bundle);
        if (!empty($analyzed_ids)) {
          $query->condition($storage->getEntityType()->getKey('id'), $analyzed_ids, 'NOT IN');
        }
      }

      if ($limit > 0) {
        $remaining = $limit - count($entities);
        if ($remaining <= 0) {
          break;
        }
        $query->range(0, $remaining);
      }

      $ids = $query->execute();

      foreach ($ids as $id) {
        $entities[] = [
          'entity_type' => $entity_type_id,
          'entity_id' => $id,
          'bundle' => $bundle,
        ];
      }
    }

    return $entities;
  }

  /**
   * Gets IDs of entities that already have analysis.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return array<int, int|string>
   *   Array of entity IDs.
   */
  private function getAnalyzedEntityIds(string $entity_type_id, string $bundle): array {
    $connection = \Drupal::database();

    $query = $connection->select('analyze_ai_content_marketing_audit_results', 'r')
      ->fields('r', ['entity_id'])
      ->condition('entity_type', $entity_type_id)
    // Only recent analysis.
      ->condition('analyzed_timestamp', strtotime('-7 days'), '>')
      ->distinct();

    $ids = $query->execute()->fetchCol();
    return array_unique($ids);
  }

  /**
   * Processes a batch of entities for content marketing audit analysis.
   *
   * @param array $entities
   *   Array of entity info.
   * @param bool $force_refresh
   *   Whether to force fresh analysis.
   * @param array $context
   *   Batch context.
   */
  public function processBatch(array $entities, bool $force_refresh, array &$context): void {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($entities);
      $context['results']['processed'] = 0;
      $context['results']['errors'] = [];
    }

    $analyzer = \Drupal::service('plugin.manager.analyze')
      ->createInstance('content_marketing_audit_analyzer');

    foreach ($entities as $entity_data) {
      try {
        $entity = $this->entityTypeManager
          ->getStorage($entity_data['entity_type'])
          ->load($entity_data['entity_id']);

        if ($entity) {
          if ($force_refresh) {
            // Delete existing scores to force fresh analysis.
            $this->storageService->deleteScores($entity);
          }

          // Capture any output to prevent JSON corruption.
          ob_start();
          $analyzer->renderSummary($entity);
          ob_end_clean();

          $context['results']['processed']++;
        }
      }
      catch (\Exception $e) {
        $context['results']['errors'][] = $this->t('Error processing @type @id: @message', [
          '@type' => $entity_data['entity_type'],
          '@id' => $entity_data['entity_id'],
          '@message' => $e->getMessage(),
        ]);
      }

      $context['sandbox']['progress']++;
    }

    $context['message'] = $this->t('Processing @current of @max entities...', [
      '@current' => $context['sandbox']['progress'],
      '@max' => $context['sandbox']['max'],
    ]);

    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

}
