<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_content_marketing_audit\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to display qualitative factor classifications.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("content_marketing_audit_classification")
 */
class ContentMarketingAuditClassification extends FieldPluginBase {

  /**
   * The content marketing audit storage service.
   *
   * @var \Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService
   */
  protected ContentMarketingAuditStorageService $storage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->storage = $container->get('analyze_ai_content_marketing_audit.storage');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Add the table and field to the query.
    $this->ensureMyTable();
    
    // Explicitly add both score and factor_id fields to the query.
    $this->additional_fields['score'] = 'score';
    $this->additional_fields['factor_id'] = 'factor_id';
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $score = $this->getValue($values, 'score');
    $factor_id = $this->getValue($values, 'factor_id');
    
    if ($score === NULL || $factor_id === NULL) {
      return '';
    }

    $factor = $this->storage->getFactor($factor_id);
    if (!$factor) {
      return '';
    }

    // Only show classifications for qualitative factors.
    if ($factor['type'] !== 'qualitative') {
      return '';
    }

    return $this->formatQualitativeScore($factor_id, (float) $score);
  }

  /**
   * Formats a qualitative score as a classification.
   *
   * @param string $factor_id
   *   The factor ID.
   * @param float $score
   *   The numeric score.
   *
   * @return string
   *   The classification string.
   */
  private function formatQualitativeScore(string $factor_id, float $score): string {
    $options = $this->storage->getFactorOptions($factor_id);
    
    if (empty($options)) {
      return '';
    }

    $option_count = count($options);
    
    // Convert from -1.0 to 1.0 range back to index.
    $index = round(($score + 1.0) / 2.0 * max(1, $option_count - 1));
    $index = max(0, min($option_count - 1, $index));
    
    return $options[$index] ?? '';
  }

}