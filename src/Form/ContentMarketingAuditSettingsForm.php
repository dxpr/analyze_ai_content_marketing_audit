<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_content_marketing_audit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for content marketing audit factors.
 */
final class ContentMarketingAuditSettingsForm extends FormBase {

  public function __construct(
    private readonly ContentMarketingAuditStorageService $storageService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('analyze_ai_content_marketing_audit.storage'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'analyze_ai_content_marketing_audit_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#markup' => $this->t('<p>Configure content marketing audit factors for AI analysis. Each factor evaluates content against specific marketing criteria.</p>'),
    ];

    // Get factors by type.
    $quantitative_factors = $this->storageService->getQuantitativeFactors();
    $qualitative_factors = $this->storageService->getQualitativeFactors();

    if (empty($quantitative_factors) && empty($qualitative_factors)) {
      $form['empty'] = [
        '#markup' => $this->t('<p>No content marketing audit factors configured. <a href="@add_url">Add a factor</a> to get started.</p>', [
          '@add_url' => Url::fromRoute('analyze_ai_content_marketing_audit.factor.add')->toString(),
        ]),
      ];
      return $form;
    }

    // Quantitative factors section.
    if (!empty($quantitative_factors)) {
      $form['quantitative_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Quantitative Factors (Scored -1.0 to 1.0)'),
        '#open' => TRUE,
      ];

      $form['quantitative_section']['quantitative_factors'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Factor'),
          $this->t('Description'),
          $this->t('Weight'),
          $this->t('Status'),
          $this->t('Operations'),
        ],
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'quantitative-factor-weight',
          ],
        ],
        '#empty' => $this->t('No quantitative factors available.'),
      ];

      $this->buildFactorTable($form['quantitative_section']['quantitative_factors'], $quantitative_factors, 'quantitative-factor-weight');
    }

    // Qualitative factors section.
    if (!empty($qualitative_factors)) {
      $form['qualitative_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Qualitative Factors (Categorical Classification)'),
        '#open' => TRUE,
      ];

      $form['qualitative_section']['qualitative_factors'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Factor'),
          $this->t('Description'),
          $this->t('Weight'),
          $this->t('Status'),
          $this->t('Operations'),
        ],
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'qualitative-factor-weight',
          ],
        ],
        '#empty' => $this->t('No qualitative factors available.'),
      ];

      $this->buildFactorTable($form['qualitative_section']['qualitative_factors'], $qualitative_factors, 'qualitative-factor-weight');
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save configuration'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Process quantitative factors.
    $quantitative_factors = $form_state->getValue(['quantitative_section', 'quantitative_factors'], []);
    $this->processFactorSubmission($quantitative_factors);

    // Process qualitative factors.
    $qualitative_factors = $form_state->getValue(['qualitative_section', 'qualitative_factors'], []);
    $this->processFactorSubmission($qualitative_factors);

    // Invalidate cache when factors are updated.
    $this->storageService->invalidateConfigCache();

    $this->messenger()->addStatus($this->t('Content marketing audit factor configuration has been saved.'));
  }

  /**
   * Helper method to build a factor table.
   */
  private function buildFactorTable(array &$table, array $factors, string $weight_class): void {
    foreach ($factors as $factor_id => $factor) {
      $table[$factor_id]['#attributes']['class'][] = 'draggable';

      $table[$factor_id]['label'] = [
        '#markup' => '<strong>' . $this->t('@label', ['@label' => $factor['label']]) . '</strong><br><code>' . $factor_id . '</code>',
      ];

      $table[$factor_id]['description'] = [
        '#markup' => $factor['description'] ?? '',
      ];

      $table[$factor_id]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => $factor['weight'],
        '#attributes' => ['class' => [$weight_class]],
      ];

      $table[$factor_id]['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#title_display' => 'invisible',
        '#default_value' => $factor['status'],
      ];

      $operations = [];
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('analyze_ai_content_marketing_audit.factor.edit', ['factor_id' => $factor_id]),
      ];
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('analyze_ai_content_marketing_audit.factor.delete', ['factor_id' => $factor_id]),
      ];

      $table[$factor_id]['operations'] = [
        '#type' => 'operations',
        '#links' => $operations,
      ];
    }
  }

  /**
   * Helper method to process factor form submission.
   */
  private function processFactorSubmission(array $factors): void {
    foreach ($factors as $factor_id => $values) {
      $factor = $this->storageService->getFactor($factor_id);
      if ($factor) {
        // Preserve existing options when updating from the settings form.
        $options = NULL;
        if (!empty($factor['options'])) {
          $decoded = json_decode($factor['options'], TRUE);
          $options = is_array($decoded) ? $decoded : NULL;
        }

        $this->storageService->saveFactor(
          $factor_id,
          $factor['label'],
          $factor['description'],
          $factor['type'],
          $options,
          (int) $values['weight'],
          (int) $values['status']
        );
      }
    }
  }

}
