<?php

/**
 * @file
 * Provide views data for analyze_ai_content_marketing_audit.module.
 */

/**
 * Implements hook_views_data().
 */
function analyze_ai_content_marketing_audit_views_data() {
  $data = [];

  $data['analyze_ai_content_marketing_audit_results']['table']['group'] = t('Content Marketing Audit');
  $data['analyze_ai_content_marketing_audit_results']['table']['base'] = [
    'field' => 'id',
    'title' => t('Content Marketing Audit Results'),
    'help' => t('AI content marketing audit scores for content entities.'),
  ];

  // ID field.
  $data['analyze_ai_content_marketing_audit_results']['id'] = [
    'title' => t('Analysis ID'),
    'help' => t('The unique ID of the analysis result.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
  ];

  // Entity type field.
  $data['analyze_ai_content_marketing_audit_results']['entity_type'] = [
    'title' => t('Entity Type'),
    'help' => t('The type of entity that was analyzed.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Entity ID field with relationship to content.
  $data['analyze_ai_content_marketing_audit_results']['entity_id'] = [
    'title' => t('Entity ID'),
    'help' => t('The ID of the entity that was analyzed.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
    'relationship' => [
      'id' => 'standard',
      'base' => 'node_field_data',
      'base field' => 'nid',
      'field' => 'entity_id',
      'label' => t('Content'),
      'title' => t('Content'),
      'help' => t('The content entity that was analyzed.'),
    ],
  ];

  // Entity revision ID field.
  $data['analyze_ai_content_marketing_audit_results']['entity_revision_id'] = [
    'title' => t('Entity Revision ID'),
    'help' => t('The revision ID of the entity that was analyzed.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
  ];

  // Language code field.
  $data['analyze_ai_content_marketing_audit_results']['langcode'] = [
    'title' => t('Language'),
    'help' => t('The language of the analyzed content.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Factor ID field.
  $data['analyze_ai_content_marketing_audit_results']['factor_id'] = [
    'title' => t('Factor Type'),
    'help' => t('The marketing factor identifier (e.g., "usability", "accuracy").'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
    'relationship' => [
      'id' => 'standard',
      'base' => 'analyze_ai_content_marketing_audit_factors',
      'base field' => 'id',
      'field' => 'factor_id',
      'label' => t('Factor'),
      'title' => t('Marketing Audit Factor'),
      'help' => t('The marketing audit factor definition.'),
    ],
  ];

  // Score field.
  $data['analyze_ai_content_marketing_audit_results']['score'] = [
    'title' => t('Marketing Audit Score'),
    'help' => t('The marketing audit score (-1.00 to +1.00).'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
  ];

  // Score field that only shows for quantitative factors.
  $data['analyze_ai_content_marketing_audit_results']['score_quantitative'] = [
    'title' => t('Marketing Audit Score (Quantitative Only)'),
    'help' => t('The marketing audit score (-1.00 to +1.00) for quantitative factors only.'),
    'field' => [
      'id' => 'content_marketing_audit_score',
      'additional fields' => ['factor_id'],
      'float' => TRUE,
    ],
    'real field' => 'score',
  ];

  // Classification field for qualitative factors.
  $data['analyze_ai_content_marketing_audit_results']['classification'] = [
    'title' => t('Classification'),
    'help' => t('The classification for qualitative factors (e.g., "Decision", "Awareness").'),
    'field' => [
      'id' => 'content_marketing_audit_classification',
      'additional fields' => ['factor_id'],
    ],
    'real field' => 'score',
  ];

  // Content hash field.
  $data['analyze_ai_content_marketing_audit_results']['content_hash'] = [
    'title' => t('Content Hash'),
    'help' => t('The SHA256 hash of the analyzed content.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Config hash field.
  $data['analyze_ai_content_marketing_audit_results']['config_hash'] = [
    'title' => t('Config Hash'),
    'help' => t('The MD5 hash of the analysis configuration.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Analysis timestamp field.
  $data['analyze_ai_content_marketing_audit_results']['analyzed_timestamp'] = [
    'title' => t('Analysis Date'),
    'help' => t('The date and time when the analysis was performed.'),
    'field' => [
      'id' => 'date',
      'click sortable' => TRUE,
    ],
    'filter' => ['id' => 'date'],
    'sort' => ['id' => 'date'],
    'argument' => ['id' => 'date'],
  ];

  // Marketing audit factors table.
  $data['analyze_ai_content_marketing_audit_factors']['table']['group'] = t('Content Marketing Audit Factors');
  $data['analyze_ai_content_marketing_audit_factors']['table']['base'] = [
    'field' => 'id',
    'title' => t('Content Marketing Audit Factors'),
    'help' => t('Marketing audit factor definitions.'),
  ];

  // Factor ID field.
  $data['analyze_ai_content_marketing_audit_factors']['id'] = [
    'title' => t('Factor ID'),
    'help' => t('The unique factor identifier.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Factor label field.
  $data['analyze_ai_content_marketing_audit_factors']['label'] = [
    'title' => t('Factor Label'),
    'help' => t('The human-readable factor name.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Factor type field.
  $data['analyze_ai_content_marketing_audit_factors']['type'] = [
    'title' => t('Factor Type'),
    'help' => t('Whether the factor is quantitative or qualitative.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Factor description field.
  $data['analyze_ai_content_marketing_audit_factors']['description'] = [
    'title' => t('Factor Description'),
    'help' => t('The factor description.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Factor options field.
  $data['analyze_ai_content_marketing_audit_factors']['options'] = [
    'title' => t('Factor Options'),
    'help' => t('The discrete options for qualitative factors.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  // Factor weight field.
  $data['analyze_ai_content_marketing_audit_factors']['weight'] = [
    'title' => t('Factor Weight'),
    'help' => t('The factor ordering weight.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
  ];

  // Factor status field.
  $data['analyze_ai_content_marketing_audit_factors']['status'] = [
    'title' => t('Factor Status'),
    'help' => t('Whether the factor is enabled.'),
    'field' => ['id' => 'boolean'],
    'filter' => ['id' => 'boolean'],
    'sort' => ['id' => 'standard'],
  ];

  return $data;
}
