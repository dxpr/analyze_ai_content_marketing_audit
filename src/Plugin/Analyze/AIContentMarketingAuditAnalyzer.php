<?php

namespace Drupal\analyze_ai_content_marketing_audit\Plugin\Analyze;

use Drupal\Core\Entity\EntityInterface;
use Drupal\analyze\AnalyzePluginBase;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\Core\Link;
use Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService;

/**
 * A content marketing audit analyzer that uses AI to analyze content factors.
 *
 * @Analyze(
 *   id = "analyze_ai_content_marketing_audit_analyzer",
 *   label = @Translation("AI Content Marketing Audit"),
 *   description = @Translation("Analyzes content marketing factors using AI.")
 * )
 */
final class AIContentMarketingAuditAnalyzer extends AnalyzePluginBase {

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|null
   */
  protected ?ConfigFactoryInterface $configFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The prompt JSON decoder service.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface
   */
  protected PromptJsonDecoderInterface $promptJsonDecoder;

  /**
   * The content marketing audit storage service.
   *
   * @var \Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService
   */
  protected ContentMarketingAuditStorageService $storage;

  /**
   * Creates the plugin.
   *
   * @param array<string, mixed> $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array<string, mixed> $plugin_definition
   *   Plugin Definition.
   * @param \Drupal\analyze\HelperInterface $helper
   *   Analyze helper service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI provider manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The prompt JSON decoder service.
   * @param \Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService $storage
   *   The content marketing audit storage service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $helper,
    $currentUser,
    AiProviderPluginManager $aiProvider,
    ?ConfigFactoryInterface $config_factory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RendererInterface $renderer,
    protected LanguageManagerInterface $languageManager,
    MessengerInterface $messenger,
    PromptJsonDecoderInterface $promptJsonDecoder,
    ContentMarketingAuditStorageService $storage,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $helper, $currentUser);
    $this->aiProvider = $aiProvider;
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->promptJsonDecoder = $promptJsonDecoder;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('analyze.helper'),
      $container->get('current_user'),
      $container->get('ai.provider'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('language_manager'),
      $container->get('messenger'),
      $container->get('ai.prompt_json_decode'),
      $container->get('analyze_ai_content_marketing_audit.storage'),
    );
  }

  /**
   * Get enabled content marketing audit factors for the given entity bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return array<string, array<string, mixed>>
   *   An array of enabled factors.
   */
  private function getEnabledFactors(string $entity_type_id, string $bundle): array {
    $factors = $this->storage->getFactors();

    $enabled = array_filter($factors, function ($factor) {
      return $factor['status'] == 1;
    });

    return $enabled;
  }

  /**
   * Creates a fallback status table.
   *
   * @param string $message
   *   The status message to display.
   *
   * @return array<string, mixed>
   *   The render array for the status table.
   */
  private function createStatusTable(string $message): array {
    // If this is the AI provider message and user has permission,
    // append the settings link.
    if ($message === 'No chat AI provider is configured for content marketing audit analysis.' && $this->currentUser->hasPermission('administer analyze settings')) {
      $link = Link::createFromRoute($this->t('Configure AI provider'), 'ai.settings_form');
      $message = $this->t('No chat AI provider is configured for content marketing audit analysis. @link', ['@link' => $link->toString()]);
    }

    return [
      '#theme' => 'analyze_table',
      '#table_title' => 'Content Marketing Audit',
      '#rows' => [
        [
          'label' => 'Status',
          'data' => $message,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function renderSummary(EntityInterface $entity): array {
    $status_config = $this->getConfigFactory()->get('analyze.settings');
    $status = $status_config->get('status') ?? [];
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    if (!isset($status[$entity_type][$bundle][$this->getPluginId()])) {
      return $this->createStatusTable('Content marketing audit analysis is not enabled for this content type.');
    }

    $enabled_factors = $this->getEnabledFactors($entity->getEntityTypeId(), $entity->bundle());
    if (empty($enabled_factors)) {
      return $this->createStatusTable('No content marketing audit factors are currently enabled.');
    }

    // Try to get cached scores first.
    $scores = $this->getStoredScores($entity);

    // If no cached scores, perform analysis.
    if (empty($scores)) {
      $scores = $this->analyzeContentMarketingAudit($entity);
      if (!empty($scores)) {
        $this->saveScores($entity, $scores);
      }
    }

    // Show the first enabled factor.
    $factor = reset($enabled_factors);
    $id = key($enabled_factors);

    if (isset($scores[$id])) {
      $score = $scores[$id];

      if ($factor['type'] === 'qualitative') {
        // For qualitative factors, show a simple table with the classification.
        $classification = $this->convertNumericToQualitative($id, $score);
        return [
          '#theme' => 'analyze_table',
          '#table_title' => $factor['label'],
          '#rows' => [
            [
              'label' => 'Classification',
              'data' => $classification,
            ],
          ],
        ];
      }
      else {
        // For quantitative factors, show the gauge.
        $gauge_value = ($score + 1) / 2;
        return [
          '#theme' => 'analyze_gauge',
          '#caption' => $this->t('@label', ['@label' => $factor['label']]),
          '#range_min_label' => $this->t('Poor'),
          '#range_mid_label' => $this->t('Average'),
          '#range_max_label' => $this->t('Excellent'),
          '#range_min' => -1,
          '#range_max' => 1,
          '#value' => $gauge_value,
          '#display_value' => sprintf('%+.1f', $score),
        ];
      }
    }

    // If no scores available but everything is configured correctly,
    // show a helpful message.
    if (!empty($content = $this->getHtml($entity))) {
      return $this->createStatusTable('No chat AI provider is configured for content marketing audit analysis.');
    }

    return $this->createStatusTable('No content available for analysis.');
  }

  /**
   * {@inheritdoc}
   */
  public function renderFullReport(EntityInterface $entity): array {
    $status_config = $this->getConfigFactory()->get('analyze.settings');
    $status = $status_config->get('status') ?? [];
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    if (!isset($status[$entity_type][$bundle][$this->getPluginId()])) {
      return $this->createStatusTable('Content marketing audit analysis is not enabled for this content type.');
    }

    $enabled_factors = $this->getEnabledFactors($entity->getEntityTypeId(), $entity->bundle());
    if (empty($enabled_factors)) {
      return $this->createStatusTable('No content marketing audit factors are currently enabled.');
    }

    // Try to get cached scores first.
    $scores = $this->getStoredScores($entity);

    // If no cached scores, perform analysis.
    if (empty($scores)) {
      $scores = $this->analyzeContentMarketingAudit($entity);
      if (!empty($scores)) {
        $this->saveScores($entity, $scores);
      }
    }

    // If no scores available but content exists, show the table message.
    if (empty($scores) && !empty($this->getHtml($entity))) {
      return $this->createStatusTable('No chat AI provider is configured for content marketing audit analysis.');
    }

    // If no content available, show that message.
    if (empty($this->getHtml($entity))) {
      return $this->createStatusTable('No content available for analysis.');
    }

    // Only build the gauge display if we have scores.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['analyze-content-marketing-audit-report'],
      ],
    ];

    $build['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Content Marketing Audit Analysis'),
    ];

    // Separate qualitative and quantitative factors.
    $qualitative_rows = [];
    $quantitative_factors = [];

    foreach ($enabled_factors as $factor_id => $factor) {
      if (isset($scores[$factor_id])) {
        $score = $scores[$factor_id];

        if ($factor['type'] === 'qualitative') {
          // Collect qualitative factors for the table at the top.
          $classification = $this->convertNumericToQualitative($factor_id, $score);
          $qualitative_rows[] = [
            'label' => $factor['label'],
            'data' => $classification,
          ];
        }
        else {
          // Collect quantitative factors for gauges below.
          $quantitative_factors[$factor_id] = $factor;
        }
      }
    }

    // Display qualitative factors in a single table at the top.
    if (!empty($qualitative_rows)) {
      $build['qualitative_table'] = [
        '#theme' => 'analyze_table',
        '#table_title' => 'Content Classifications',
        '#rows' => $qualitative_rows,
        '#weight' => -10,
      ];
    }

    // Display quantitative factors as individual gauges below.
    foreach ($quantitative_factors as $factor_id => $factor) {
      if (isset($scores[$factor_id])) {
        $score = $scores[$factor_id];
        $gauge_value = ($score + 1) / 2;
        $build[$factor_id] = [
          '#theme' => 'analyze_gauge',
          '#caption' => $this->t('@label', ['@label' => $factor['label']]),
          '#range_min_label' => $this->t('Poor'),
          '#range_mid_label' => $this->t('Average'),
          '#range_max_label' => $this->t('Excellent'),
          '#range_min' => -1,
          '#range_max' => 1,
          '#value' => $gauge_value,
          '#display_value' => sprintf('%+.1f', $score),
        ];
      }
    }

    return $build;
  }

  /**
   * Gets stored scores for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array<string, float>
   *   Array of scores keyed by factor ID.
   */
  private function getStoredScores(EntityInterface $entity): array {
    $scores = [];
    $factors = $this->storage->getFactors();

    foreach ($factors as $factor_id => $factor) {
      $score = $this->storage->getScore($entity, $factor_id);
      if ($score !== NULL) {
        $scores[$factor_id] = $score;
      }
    }

    return $scores;
  }

  /**
   * Saves scores for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $scores
   *   Array of scores/classifications keyed by factor ID.
   */
  private function saveScores(EntityInterface $entity, array $scores): void {
    foreach ($scores as $factor_id => $value) {
      // Convert qualitative classifications to numeric values for storage.
      if (is_string($value)) {
        $numeric_value = $this->convertQualitativeToNumeric($factor_id, $value);
        $this->storage->saveScore($entity, $factor_id, $numeric_value);
      }
      else {
        $this->storage->saveScore($entity, $factor_id, (float) $value);
      }
    }
  }

  /**
   * Converts qualitative classification to numeric value for storage.
   *
   * @param string $factor_id
   *   The factor ID.
   * @param string $classification
   *   The classification value.
   *
   * @return float
   *   Numeric representation for storage.
   */
  private function convertQualitativeToNumeric(string $factor_id, string $classification): float {
    $options = $this->getQualitativeFactorOptions($factor_id);
    $index = array_search($classification, $options, TRUE);

    if ($index === FALSE) {
      // Default fallback.
      return 0.0;
    }

    // Convert index to a value between -1.0 and 1.0.
    $option_count = count($options);
    return ($index / max(1, $option_count - 1)) * 2.0 - 1.0;
  }

  /**
   * Converts numeric value back to qualitative classification.
   *
   * @param string $factor_id
   *   The factor ID.
   * @param float $numeric_value
   *   The stored numeric value.
   *
   * @return string
   *   The classification string.
   */
  private function convertNumericToQualitative(string $factor_id, float $numeric_value): string {
    $options = $this->getQualitativeFactorOptions($factor_id);
    $option_count = count($options);

    // Convert from -1.0 to 1.0 range back to index.
    $index = round(($numeric_value + 1.0) / 2.0 * max(1, $option_count - 1));
    $index = max(0, min($option_count - 1, $index));

    return $options[$index] ?? $options[0];
  }

  /**
   * Analyzes content marketing audit factors using AI.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to analyze.
   *
   * @return array<string, float|string>
   *   Array of scores/classifications keyed by factor ID.
   */
  private function analyzeContentMarketingAudit(EntityInterface $entity): array {
    $content = $this->getHtml($entity);
    if (empty($content)) {
      return [];
    }

    $enabled_factors = $this->getEnabledFactors($entity->getEntityTypeId(), $entity->bundle());
    if (empty($enabled_factors)) {
      return [];
    }

    // Separate quantitative and qualitative factors.
    $quantitative_factors = array_filter($enabled_factors, fn($factor) => $factor['type'] === 'quantitative');
    $qualitative_factors = array_filter($enabled_factors, fn($factor) => $factor['type'] === 'qualitative');

    $results = [];

    // Analyze quantitative factors (scoring).
    if (!empty($quantitative_factors)) {
      $quantitative_results = $this->analyzeQuantitativeFactors($entity, $quantitative_factors, $content);
      $results = array_merge($results, $quantitative_results);
    }

    // Analyze qualitative factors (classification).
    if (!empty($qualitative_factors)) {
      $qualitative_results = $this->analyzeQualitativeFactors($entity, $qualitative_factors, $content);
      $results = array_merge($results, $qualitative_results);
    }

    return $results;
  }

  /**
   * Analyzes quantitative factors using AI scoring.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to analyze.
   * @param array $factors
   *   The quantitative factors to analyze.
   * @param string $content
   *   The content to analyze.
   *
   * @return array<string, float>
   *   Array of scores keyed by factor ID.
   */
  private function analyzeQuantitativeFactors(EntityInterface $entity, array $factors, string $content): array {
    try {
      $ai_provider = $this->getAiProvider();
      if (!$ai_provider) {
        return [];
      }

      // Build the JSON template for quantitative factors.
      $json_keys = [];
      $factor_descriptions = [];

      foreach ($factors as $factor_id => $factor) {
        $json_keys[] = '"' . $factor_id . '": 0.0';
        $factor_descriptions[] = $factor_id . ': ' . $factor['description'];
      }

      $json_template = '{' . implode(', ', $json_keys) . '}';
      $factors_text = implode("\n", $factor_descriptions);

      $prompt = <<<EOT
<task>Analyze the following content for quantitative marketing audit factors.</task>
<content>
$content
</content>

<factors>
$factors_text
</factors>

<instructions>Provide precise scores between -1.0 and +1.0 for each factor where:
- -1.0 indicates very poor performance on that marketing factor
- 0.0 indicates average/neutral performance
- +1.0 indicates excellent performance on that marketing factor</instructions>
<output_format>Respond with a simple JSON object containing only the required scores:
$json_template</output_format>
EOT;

      $chat_array = [
        new ChatMessage('user', $prompt),
      ];

      // Get response.
      $messages = new ChatInput($chat_array);
      $defaults = $this->getDefaultModel();
      $message = $ai_provider->chat($messages, $defaults['model_id'])->getNormalized();

      // Use the injected PromptJsonDecoder service.
      $decoded = $this->promptJsonDecoder->decode($message);

      // If we couldn't decode the JSON at all.
      if (!is_array($decoded)) {
        return [];
      }

      // Validate and clean the scores.
      $scores = [];
      foreach ($factors as $factor_id => $factor) {
        if (isset($decoded[$factor_id]) && is_numeric($decoded[$factor_id])) {
          // Clamp the score to -1.0 to 1.0 range.
          $score = max(-1.0, min(1.0, (float) $decoded[$factor_id]));
          $scores[$factor_id] = $score;
        }
      }

      return $scores;
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Quantitative factor analysis failed: @message', [
        '@message' => $e->getMessage(),
      ]));
      return [];
    }
  }

  /**
   * Analyzes qualitative factors using AI classification.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to analyze.
   * @param array $factors
   *   The qualitative factors to analyze.
   * @param string $content
   *   The content to analyze.
   *
   * @return array<string, string>
   *   Array of classifications keyed by factor ID.
   */
  private function analyzeQualitativeFactors(EntityInterface $entity, array $factors, string $content): array {
    try {
      $ai_provider = $this->getAiProvider();
      if (!$ai_provider) {
        return [];
      }

      // Build the JSON template for qualitative factors.
      $json_keys = [];
      $factor_descriptions = [];

      foreach ($factors as $factor_id => $factor) {
        $options = $this->getQualitativeFactorOptions($factor_id);
        $json_keys[] = '"' . $factor_id . '": "' . $options[0] . '"';
        $factor_descriptions[] = $factor_id . ': ' . $factor['description'] . ' (Options: ' . implode(', ', $options) . ')';
      }

      $json_template = '{' . implode(', ', $json_keys) . '}';
      $factors_text = implode("\n", $factor_descriptions);

      $prompt = <<<EOT
<task>Classify the following content for qualitative marketing audit factors.</task>
<content>
$content
</content>

<factors>
$factors_text
</factors>

<instructions>For each factor, select the most appropriate classification from the provided options. Choose the option that best describes where this content fits.</instructions>
<output_format>Respond with a simple JSON object containing only the required classifications:
$json_template</output_format>
EOT;

      $chat_array = [
        new ChatMessage('user', $prompt),
      ];

      // Get response.
      $messages = new ChatInput($chat_array);
      $defaults = $this->getDefaultModel();
      $message = $ai_provider->chat($messages, $defaults['model_id'])->getNormalized();

      // Use the injected PromptJsonDecoder service.
      $decoded = $this->promptJsonDecoder->decode($message);

      // If we couldn't decode the JSON at all.
      if (!is_array($decoded)) {
        return [];
      }

      // Validate classifications.
      $classifications = [];
      foreach ($factors as $factor_id => $factor) {
        if (isset($decoded[$factor_id])) {
          $options = $this->getQualitativeFactorOptions($factor_id);
          $value = (string) $decoded[$factor_id];
          // For qualitative factors, we store the classification as a string.
          // We'll use a numeric representation for storage but keep the
          // string value.
          if (in_array($value, $options, TRUE)) {
            $classifications[$factor_id] = $value;
          }
        }
      }

      return $classifications;
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Qualitative factor analysis failed: @message', [
        '@message' => $e->getMessage(),
      ]));
      return [];
    }
  }

  /**
   * Gets the options for a qualitative factor.
   *
   * @param string $factor_id
   *   The factor ID.
   *
   * @return array<string>
   *   Array of available options.
   */
  private function getQualitativeFactorOptions(string $factor_id): array {
    $options = $this->storage->getFactorOptions($factor_id);

    // Fallback to default options if none stored.
    if (empty($options)) {
      switch ($factor_id) {
        case 'funnel_stage':
          return ['Awareness', 'Consideration', 'Decision', 'Retention'];

        default:
          return ['Option 1', 'Option 2', 'Option 3'];
      }
    }

    return $options;
  }

  /**
   * Gets the HTML content of an entity for analysis.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to render.
   *
   * @return string
   *   A HTML string of rendered content.
   */
  private function getHtml(EntityInterface $entity): string {
    // Get the current active langcode from the site.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Get the rendered entity view in default mode.
    $view = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, 'default', $langcode);
    $rendered = $this->renderer->render($view);

    // Convert to string and strip HTML for content marketing audit analysis.
    $content = is_object($rendered) && method_exists($rendered, '__toString')
      ? $rendered->__toString()
      : (string) $rendered;

    // Clean up the content for analysis.
    $content = strip_tags($content);
    $content = str_replace('&nbsp;', ' ', $content);
    // Replace multiple whitespace characters (spaces, tabs, newlines)
    // with a single space.
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);

    return $content;
  }

  /**
   * Gets the AI provider for content marketing audit analysis.
   *
   * @return \Drupal\ai\AiProviderInterface|null
   *   The AI provider instance or NULL if not available.
   */
  private function getAiProvider() {
    // Check if we have any chat providers available.
    if (!$this->aiProvider->hasProvidersForOperationType('chat', TRUE)) {
      return NULL;
    }

    // Get the default provider for chat.
    $defaults = $this->getDefaultModel();
    if (empty($defaults['provider_id'])) {
      return NULL;
    }

    // Initialize AI provider.
    $ai_provider = $this->aiProvider->createInstance($defaults['provider_id']);

    // Configure provider with low temperature for more consistent results.
    $ai_provider->setConfiguration(['temperature' => 0.2]);

    return $ai_provider;
  }

  /**
   * Gets the default model configuration for chat operations.
   *
   * @return array<string, string>|null
   *   Array containing provider_id and model_id, or NULL if not configured.
   */
  private function getDefaultModel(): ?array {
    $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
    if (empty($defaults['provider_id']) || empty($defaults['model_id'])) {
      return NULL;
    }
    return $defaults;
  }

  /**
   * Gets a human-readable status for a score.
   *
   * @param float $score
   *   The score.
   *
   * @return string
   *   The status description.
   */
  private function getScoreStatus(float $score): string {
    if ($score >= 0.7) {
      return $this->t('Excellent');
    }
    elseif ($score >= 0.3) {
      return $this->t('Good');
    }
    elseif ($score >= -0.3) {
      return $this->t('Average');
    }
    elseif ($score >= -0.7) {
      return $this->t('Needs Improvement');
    }
    else {
      return $this->t('Poor');
    }
  }

}
