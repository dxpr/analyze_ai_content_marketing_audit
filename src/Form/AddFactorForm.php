<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_content_marketing_audit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding a new content marketing audit factor.
 */
final class AddFactorForm extends FormBase {

  public function __construct(
    private readonly ContentMarketingAuditStorageService $storageService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('analyze_ai_content_marketing_audit.storage'),
    );
  }

  public function getFormId(): string {
    return 'analyze_ai_content_marketing_audit_add_factor';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Factor ID'),
      '#description' => $this->t('A unique machine name for this factor. Use lowercase letters, numbers, and underscores only.'),
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => [$this, 'factorExists'],
        'source' => ['label'],
      ],
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('The human-readable name for this factor.'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('A detailed description of what this factor measures.'),
      '#rows' => 3,
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Factor Type'),
      '#description' => $this->t('Quantitative factors are scored from -1.0 to 1.0. Qualitative factors classify content into categories.'),
      '#options' => [
        'quantitative' => $this->t('Quantitative (Scored)'),
        'qualitative' => $this->t('Qualitative (Categorical)'),
      ],
      '#default_value' => 'quantitative',
      '#required' => TRUE,
    ];

    $form['options'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Discrete Options'),
      '#description' => $this->t('For qualitative factors only. Enter one option per line. These are the categories the AI can classify content into.'),
      '#rows' => 4,
      '#placeholder' => $this->t("Awareness\nConsideration\nDecision\nRetention"),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'qualitative'],
        ],
        'required' => [
          ':input[name="type"]' => ['value' => 'qualitative'],
        ],
      ],
    ];

    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#description' => $this->t('Factors with lower weights are shown first.'),
      '#default_value' => 0,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Whether this factor is active for analysis.'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Add factor'),
        '#button_type' => 'primary',
      ],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute('analyze_ai_content_marketing_audit.settings'),
        '#attributes' => ['class' => ['button']],
      ],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $factor_id = $form_state->getValue('id');
    
    if ($this->factorExists($factor_id)) {
      $form_state->setErrorByName('id', $this->t('A factor with this ID already exists.'));
    }

    // Validate options for qualitative factors.
    $type = $form_state->getValue('type');
    if ($type === 'qualitative') {
      $options_text = trim($form_state->getValue('options', ''));
      if (empty($options_text)) {
        $form_state->setErrorByName('options', $this->t('Discrete options are required for qualitative factors.'));
      } else {
        $options = array_filter(array_map('trim', explode("\n", $options_text)));
        if (count($options) < 2) {
          $form_state->setErrorByName('options', $this->t('At least 2 discrete options are required for qualitative factors.'));
        }
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    
    // Process options for qualitative factors.
    $options = NULL;
    if ($values['type'] === 'qualitative' && !empty($values['options'])) {
      $options = array_filter(array_map('trim', explode("\n", $values['options'])));
    }
    
    $this->storageService->saveFactor(
      $values['id'],
      $values['label'],
      $values['description'] ?? '',
      $values['type'],
      $options,
      (int) $values['weight'],
      (int) $values['status']
    );

    $this->messenger()->addStatus($this->t('Content marketing audit factor %label has been added.', [
      '%label' => $values['label'],
    ]));

    $form_state->setRedirectUrl(Url::fromRoute('analyze_ai_content_marketing_audit.settings'));
  }

  /**
   * Checks if a factor with the given ID exists.
   *
   * @param string $factor_id
   *   The factor ID to check.
   *
   * @return bool
   *   TRUE if the factor exists, FALSE otherwise.
   */
  public function factorExists(string $factor_id): bool {
    return $this->storageService->getFactor($factor_id) !== NULL;
  }

}