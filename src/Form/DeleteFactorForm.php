<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_content_marketing_audit\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\analyze_ai_content_marketing_audit\Service\ContentMarketingAuditStorageService;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for deleting a content marketing audit factor.
 */
final class DeleteFactorForm extends ConfirmFormBase {

  private ?array $factor = NULL;
  private ?string $factorId = NULL;

  public function __construct(
    private readonly ContentMarketingAuditStorageService $storageService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('analyze_ai_content_marketing_audit.storage'),
    );
  }

  public function getFormId(): string {
    return 'analyze_ai_content_marketing_audit_delete_factor';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $factor_id = NULL): array {
    $this->factorId = $factor_id;
    $this->factor = $this->storageService->getFactor($factor_id);
    
    if (!$this->factor) {
      $this->messenger()->addError($this->t('Content marketing audit factor not found.'));
      $form_state->setRedirectUrl(Url::fromRoute('analyze_ai_content_marketing_audit.settings'));
      return [];
    }

    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): string {
    return $this->t('Are you sure you want to delete the content marketing audit factor %label?', [
      '%label' => $this->factor['label'] ?? $this->factorId,
    ]);
  }

  public function getDescription(): string {
    return $this->t('This action cannot be undone. All analysis results for this factor will also be deleted.');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('analyze_ai_content_marketing_audit.settings');
  }

  public function getConfirmText(): string {
    return $this->t('Delete');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->storageService->deleteFactor($this->factorId);

    $this->messenger()->addStatus($this->t('Content marketing audit factor %label has been deleted.', [
      '%label' => $this->factor['label'],
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}