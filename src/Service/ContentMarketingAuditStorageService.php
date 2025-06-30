<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_content_marketing_audit\Service;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Service for storing and retrieving content marketing audit analysis results.
 */
final class ContentMarketingAuditStorageService {

  use DependencySerializationTrait;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Gets the content marketing audit score for a specific entity and factor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the score for.
   * @param string $factor_id
   *   The factor ID to get the score for.
   *
   * @return float|null
   *   The score or NULL if not found.
   */
  public function getScore(EntityInterface $entity, string $factor_id): ?float {
    $content_hash = $this->generateContentHash($entity);
    $config_hash = $this->generateConfigHash();

    $query = $this->database->select('analyze_ai_content_marketing_audit_results', 'r')
      ->fields('r', ['score'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->condition('langcode', $entity->language()->getId())
      ->condition('factor_id', $factor_id)
      ->condition('content_hash', $content_hash)
      ->condition('config_hash', $config_hash)
      ->orderBy('analyzed_timestamp', 'DESC')
      ->range(0, 1);

    $result = $query->execute()->fetchField();
    return $result !== FALSE ? (float) $result : NULL;
  }

  /**
   * Saves a content marketing audit score for an entity and factor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to save the score for.
   * @param string $factor_id
   *   The factor ID to save the score for.
   * @param float $score
   *   The score to save.
   */
  public function saveScore(EntityInterface $entity, string $factor_id, float $score): void {
    $content_hash = $this->generateContentHash($entity);
    $config_hash = $this->generateConfigHash();

    // Delete existing records for this entity/factor/content/config combo.
    $this->database->delete('analyze_ai_content_marketing_audit_results')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->condition('langcode', $entity->language()->getId())
      ->condition('factor_id', $factor_id)
      ->condition('content_hash', $content_hash)
      ->condition('config_hash', $config_hash)
      ->execute();

    // Insert new record.
    $this->database->insert('analyze_ai_content_marketing_audit_results')
      ->fields([
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
        'entity_revision_id' => $entity->getRevisionId(),
        'langcode' => $entity->language()->getId(),
        'factor_id' => $factor_id,
        'score' => $score,
        'content_hash' => $content_hash,
        'config_hash' => $config_hash,
        'analyzed_timestamp' => time(),
      ])
      ->execute();
  }

  /**
   * Deletes all scores for a specific entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to delete scores for.
   */
  public function deleteScores(EntityInterface $entity): void {
    $this->database->delete('analyze_ai_content_marketing_audit_results')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute();
  }

  /**
   * Invalidates cached scores when configuration changes.
   */
  public function invalidateConfigCache(): void {
    // We check config hash on retrieval, so nothing needed here.
    // Old cached results will not be found due to config hash mismatch.
  }

  /**
   * Gets all content marketing audit factors.
   *
   * @param string|null $type
   *   Optional filter by factor type (quantitative or qualitative).
   *
   * @return array<string, mixed>
   *   Array of factor data keyed by factor ID.
   */
  public function getFactors(?string $type = NULL): array {
    $query = $this->database->select('analyze_ai_content_marketing_audit_factors', 'f')
      ->fields('f')
      ->condition('status', 1)
      ->orderBy('weight')
      ->orderBy('label');

    if ($type !== NULL) {
      $query->condition('type', $type);
    }

    return $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * Gets quantitative factors only.
   *
   * @return array<string, mixed>
   *   Array of quantitative factor data keyed by factor ID.
   */
  public function getQuantitativeFactors(): array {
    return $this->getFactors('quantitative');
  }

  /**
   * Gets qualitative factors only.
   *
   * @return array<string, mixed>
   *   Array of qualitative factor data keyed by factor ID.
   */
  public function getQualitativeFactors(): array {
    return $this->getFactors('qualitative');
  }

  /**
   * Gets the options for a qualitative factor.
   *
   * @param string $factor_id
   *   The factor ID.
   *
   * @return array<string>
   *   Array of discrete options.
   */
  public function getFactorOptions(string $factor_id): array {
    $factor = $this->getFactor($factor_id);
    if (!$factor || $factor['type'] !== 'qualitative') {
      return [];
    }

    if (!empty($factor['options'])) {
      $decoded = json_decode($factor['options'], TRUE);
      return is_array($decoded) ? $decoded : [];
    }

    return [];
  }

  /**
   * Gets a specific content marketing audit factor.
   *
   * @param string $factor_id
   *   The factor ID.
   *
   * @return array<string, mixed>|null
   *   The factor data or NULL if not found.
   */
  public function getFactor(string $factor_id): ?array {
    $query = $this->database->select('analyze_ai_content_marketing_audit_factors', 'f')
      ->fields('f')
      ->condition('id', $factor_id);

    $result = $query->execute()->fetchAssoc();
    return $result ?: NULL;
  }

  /**
   * Saves a content marketing audit factor.
   *
   * @param string $factor_id
   *   The factor ID.
   * @param string $label
   *   The factor label.
   * @param string $description
   *   The factor description.
   * @param string $type
   *   The factor type (quantitative or qualitative).
   * @param array<string>|null $options
   *   The discrete options for qualitative factors.
   * @param int $weight
   *   The factor weight.
   * @param int $status
   *   The factor status.
   */
  public function saveFactor(string $factor_id, string $label, string $description, string $type, ?array $options, int $weight, int $status): void {
    $this->database->merge('analyze_ai_content_marketing_audit_factors')
      ->key('id', $factor_id)
      ->fields([
        'label' => $label,
        'description' => $description,
        'type' => $type,
        'options' => $options ? json_encode($options) : NULL,
        'weight' => $weight,
        'status' => $status,
      ])
      ->execute();
  }

  /**
   * Deletes a content marketing audit factor.
   *
   * @param string $factor_id
   *   The factor ID to delete.
   */
  public function deleteFactor(string $factor_id): void {
    // Delete the factor.
    $this->database->delete('analyze_ai_content_marketing_audit_factors')
      ->condition('id', $factor_id)
      ->execute();

    // Delete associated results.
    $this->database->delete('analyze_ai_content_marketing_audit_results')
      ->condition('factor_id', $factor_id)
      ->execute();
  }

  /**
   * Generates a content hash for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to generate hash for.
   *
   * @return string
   *   The content hash.
   */
  private function generateContentHash(EntityInterface $entity): string {
    // Use the entity's own language for content hash.
    $current_language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    \Drupal::languageManager()->setConfigOverrideLanguage($entity->language());

    try {
      $content = '';
      if ($entity instanceof FieldableEntityInterface) {
        if ($entity->hasField('title')) {
          $content .= $entity->get('title')->value ?? '';
        }
        if ($entity->hasField('body')) {
          $body = $entity->get('body')->value ?? '';
          $content .= strip_tags($body);
        }
      }

      return hash('sha256', $content . $entity->getEntityTypeId() . $entity->id() . $entity->language()->getId());
    } finally {
      \Drupal::languageManager()->setConfigOverrideLanguage(\Drupal::languageManager()->getLanguage($current_language));
    }
  }

  /**
   * Generates a configuration hash.
   *
   * @return string
   *   The configuration hash.
   */
  private function generateConfigHash(): string {
    // Include factor configuration in hash.
    $factors = $this->getFactors();
    $config_data = [
      'factors' => $factors,
      'ai_provider' => \Drupal::config('ai.settings')->get('default_provider'),
    ];

    return md5(serialize($config_data));
  }

}
