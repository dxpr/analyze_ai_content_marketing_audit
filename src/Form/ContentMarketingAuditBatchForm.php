<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_content_marketing_audit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditBatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Batch form for content marketing audit analysis.
 */
final class ContentMarketingAuditBatchForm extends FormBase {

  public function __construct(
    private readonly ContentMarketingAuditBatchService $batchService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('analyze_ai_content_marketing_audit.batch'),
    );
  }

  public function getFormId(): string {
    return 'analyze_ai_content_marketing_audit_batch';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#markup' => $this->t('<p>Analyze content for marketing audit factors. Results are cached to improve performance.</p>'),
    ];

    $available_types = $this->getAvailableEntityTypes();
    
    if (empty($available_types)) {
      $configure_url = \Drupal\Core\Url::fromRoute('analyze.analyze_settings');
      $form['no_bundles'] = [
        '#markup' => $this->t('<p>No content types have content marketing audit analysis enabled. Please <a href="@url">configure the Analyze module</a> first.</p>', [
          '@url' => $configure_url->toString(),
        ]),
      ];
      return $form;
    }

    $form['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content Types'),
      '#options' => $available_types,
      '#required' => TRUE,
    ];

    $form['force_refresh'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force re-analysis'),
      '#description' => $this->t('Re-analyze content even if recent results exist.'),
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit'),
      '#default_value' => 50,
      '#min' => 1,
      '#max' => 1000,
      '#description' => $this->t('Maximum number of entities to process.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Start Analysis'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $selected_types = array_filter($values['entity_types']);
    
    $entities = $this->batchService->getEntitiesForAnalysis(
      $selected_types,
      (bool) $values['force_refresh'],
      (int) $values['limit']
    );
    
    if (empty($entities)) {
      $this->messenger()->addWarning($this->t('No entities found for analysis.'));
      return;
    }

    $batch = [
      'title' => $this->t('Analyzing Content Marketing Audit'),
      'operations' => [],
      'finished' => [static::class, 'batchFinished'],
    ];

    // Process in chunks of 5.
    $chunks = array_chunk($entities, 5);
    foreach ($chunks as $chunk) {
      $batch['operations'][] = [
        [$this->batchService, 'processBatch'],
        [$chunk, (bool) $values['force_refresh']],
      ];
    }

    batch_set($batch);
  }

  public static function batchFinished(bool $success, array $results, array $operations): void {
    if ($success) {
      $processed = $results['processed'] ?? 0;
      \Drupal::messenger()->addStatus(t('Successfully analyzed @count entities.', [
        '@count' => $processed,
      ]));

      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          \Drupal::messenger()->addError($error);
        }
      }
    } else {
      \Drupal::messenger()->addError(t('Batch processing failed.'));
    }
  }

  private function getAvailableEntityTypes(): array {
    // Get entity types where content marketing audit analyzer is enabled.
    $config = \Drupal::config('analyze.settings');
    $status = $config->get('status') ?? [];
    
    $options = [];
    foreach ($status as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle => $analyzers) {
        if (isset($analyzers['content_marketing_audit_analyzer'])) {
          $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
          $label = $bundle_info[$bundle]['label'] ?? $bundle;
          $options["{$entity_type_id}:{$bundle}"] = "{$entity_type_id} - {$label}";
        }
      }
    }

    return $options;
  }

}