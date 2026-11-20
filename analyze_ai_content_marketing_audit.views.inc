<?php

/**
 * @file
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\analyze_ai_content_marketing_audit\Hook\AnalyzeAiContentMarketingAuditViewsHooks;

/**
 * @file
 * Provide views data for analyze_ai_content_marketing_audit.module.
 */

/**
 * Implements hook_views_data().
 */
#[LegacyHook]
function analyze_ai_content_marketing_audit_views_data() {
  return \Drupal::service(AnalyzeAiContentMarketingAuditViewsHooks::class)->viewsData();
}
