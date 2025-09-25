<?php

namespace Drupal\analyze_ai_content_marketing_audit\Plugin\Analyze;

use Drupal\Core\Entity\EntityInterface;
use Drupal\analyze\AnalyzePluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Link;
use Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A content marketing audit analyzer.
 *
 * @Analyze(
 *   id = "analyze_content_marketing_audit_analyzer",
 *   label = @Translation("Content Marketing Audit"),
 *   description = @Translation("Analyzes content marketing factors.")
 * )
 */
final class ContentMarketingAuditAnalyzer extends AnalyzePluginBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService $storage
   *   The content marketing audit storage service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $helper,
    $currentUser,
    ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RendererInterface $renderer,
    protected LanguageManagerInterface $languageManager,
    MessengerInterface $messenger,
    ContentMarketingAuditStorageService $storage,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $helper, $currentUser, $configFactory);
    $this->messenger = $messenger;
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
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('language_manager'),
      $container->get('messenger'),
      $container->get('analyze_ai_content_marketing_audit.storage'),
    );
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
      $settings_link = Link::createFromRoute($this->t('Enable content marketing audit'), 'analyze.analyze_settings')->toString();
      return $this->createStatusTable($this->t('Content marketing audit analysis is not enabled for this content type. @link to configure content types.', ['@link' => $settings_link]));
    }

    $enabled_factors = $this->getEnabledFactors($entity->getEntityTypeId(), $entity->bundle());
    if (empty($enabled_factors)) {
      $factors_link = Link::createFromRoute($this->t('Configure marketing factors'), 'analyze_ai_content_marketing_audit.settings')->toString();
      return $this->createStatusTable($this->t('No content marketing audit factors are currently enabled. @link to select factors to analyze.', ['@link' => $factors_link]));
    }

    return $this->createStatusTable($this->t('Basic content marketing audit functionality. Consider using the AI-powered version for detailed analysis.'));
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

}
