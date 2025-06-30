<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_content_marketing_audit\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Form\FormStateInterface;
use Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Gettext\PoItem;

/**
 * A handler to display numeric scores only for quantitative factors.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("content_marketing_audit_score")
 */
class ContentMarketingAuditScore extends FieldPluginBase {

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
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['set_precision'] = ['default' => FALSE];
    $options['precision'] = ['default' => 0];
    $options['decimal'] = ['default' => '.'];
    $options['separator'] = ['default' => ','];
    $options['format_plural'] = ['default' => FALSE];
    $options['format_plural_string'] = ['default' => '1' . PoItem::DELIMITER . '@count'];
    $options['prefix'] = ['default' => ''];
    $options['suffix'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['set_precision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Specify validation criteria'),
      '#default_value' => $this->options['set_precision'],
    ];
    $form['precision'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Precision'),
      '#default_value' => $this->options['precision'],
      '#description' => $this->t('Specify how many digits to print after the decimal point.'),
      '#states' => [
        'visible' => [
          ':input[name="options[set_precision]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['decimal'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Decimal point'),
      '#default_value' => $this->options['decimal'],
      '#size' => 2,
    ];
    $form['separator'] = [
      '#type' => 'select',
      '#title' => $this->t('Thousands marker'),
      '#options' => [
        '' => $this->t('- None -'),
        ',' => $this->t('Comma'),
        ' ' => $this->t('Space'),
        '.' => $this->t('Period'),
        '\'' => $this->t('Apostrophe'),
      ],
      '#default_value' => $this->options['separator'],
      '#size' => 2,
    ];

    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#default_value' => $this->options['prefix'],
      '#description' => $this->t('Text to put before the number, such as currency symbol.'),
    ];
    $form['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix'),
      '#default_value' => $this->options['suffix'],
      '#description' => $this->t('Text to put after the number, such as currency symbol.'),
    ];

    parent::buildOptionsForm($form, $form_state);
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

    // Only show numeric scores for quantitative factors.
    if ($factor['type'] !== 'quantitative') {
      return '';
    }

    // Format the numeric value.
    $value = (float) $score;

    if ($this->options['set_precision']) {
      $precision = (int) $this->options['precision'];
      $value = number_format($value, $precision, $this->options['decimal'], $this->options['separator']);
    }
    else {
      $value = number_format($value, 2, $this->options['decimal'], $this->options['separator']);
    }

    return $this->sanitizeValue($this->options['prefix'], 'xss') . $value . $this->sanitizeValue($this->options['suffix'], 'xss');
  }

}
