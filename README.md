# AI Content Marketing Audit

AI-powered content marketing analysis evaluating seven critical
factors that drive engagement and business results.

## Features

- **Seven Marketing Factors**: Usability, Knowledge Level, Actionability,
  Accuracy, Business Value, Messaging, Brand Voice Fit
- **Dual Analysis**: Quantitative scoring (-1.0 to +1.0) and qualitative
  classification
- **Configurable Factors**: Add, edit, delete custom audit factors
- **Batch Processing**: Analyze large content volumes efficiently
- **Views Integration**: Custom reports and dashboards
- **Smart Caching**: Performance optimization with content/config-based
  invalidation

## Requirements

- [Analyze](https://www.drupal.org/project/analyze) framework
- [AI](https://www.drupal.org/project/ai) module with configured provider

## Installation

```bash
composer require drupal/analyze_ai_content_marketing_audit
drush en analyze_ai_content_marketing_audit
```

## Configuration

### Basic Setup
1. Configure AI provider at `/admin/config/analyze/ai`
2. Manage factors at `/admin/config/analyze/content-marketing-audit`
3. Enable per content type at `/admin/config/system/analyze-settings`
4. Set permissions at
   `/admin/people/permissions#module-analyze_ai_content_marketing_audit`

### Factor Management
- **Add factors**: Click "Add factor" with ID, label, description, weight
- **Edit factors**: Modify existing factors (ID cannot be changed)
- **Delete factors**: Removes factor and all associated results

## AI Coding Assistant Integration

The Content Marketing Audit module includes a built-in
[Agent Skills](https://agentskills.io) file (via the base
Analyze module) that teaches AI coding assistants how to run
marketing audit analysis through natural language. Run
`drush analyze:setup-ai` to enable, then ask naturally:

```
"Audit content marketing quality on all articles"
"Check the usability and actionability scores for landing pages"
"Run a marketing audit on all published content"
"Analyze business value across the blog section"
```

Batch processing is available via the centralized Analyze
batch system:

```bash
# Check analysis coverage
drush analyze:batch --status

# Run this analyzer on all enabled content types
drush analyze:batch \
  --analyzers=analyze_ai_content_marketing_audit_analyzer

# Run on specific content types with limit
drush analyze:batch \
  --analyzers=analyze_ai_content_marketing_audit_analyzer \
  --types=node:article --limit=50

# Force re-analysis of already analyzed content
drush analyze:batch \
  --analyzers=analyze_ai_content_marketing_audit_analyzer --force
```

Compatible with Claude Code, Codex CLI, Gemini CLI, GitHub
Copilot, Cursor, and other tools supporting the
[Agent Skills standard](https://agentskills.io/specification).

## Analysis

### Scoring System
- **+1.0**: Excellent performance
- **0.7 to 0.9**: Good performance
- **0.3 to 0.6**: Average performance
- **-0.3 to 0.2**: Needs improvement
- **-1.0 to -0.4**: Poor performance

### Default Factors
1. **Usability**: Content clarity and actionability
2. **Knowledge Level**: Expertise demonstration
3. **Actionability**: Clear next steps provided
4. **Accuracy**: Factual correctness and currency
5. **Business Value**: Support for business goals
6. **Messaging**: Clarity and consistency
7. **Brand Voice Fit**: Alignment with brand guidelines

## Views Integration

### Default Reports
Access at `/admin/reports/content-marketing-audit` showing content title, factor
type, score, and analysis date.

### Custom Views
- Base table: "Content Marketing Audit Results"
- Filter by factor type, score range, content title, analysis date
- Install [Views Color Scales](https://www.drupal.org/project/views_color_scales)
  for color-coded results

## API Usage

```php
// Get storage service
$storage = \Drupal::service('analyze_ai_content_marketing_audit.storage');

// Get/save scores
$score = $storage->getScore($entity, 'usability');
$storage->saveScore($entity, 'accuracy', 0.85);

// Analyze content
$analyzer = \Drupal::service('plugin.manager.analyze')
  ->createInstance('content_marketing_audit_analyzer');
$score = $analyzer->analyze($entity, 'messaging');
```

## Troubleshooting

### Analysis Issues
- Verify AI provider configuration
- Check factor status and descriptions
- Enable analysis for content types

### Batch Processing
- Reduce batch size for timeouts (10-25 entities)
- Process during off-peak hours for API limits
- Monitor server memory and execution time

### Performance
- Cache invalidation is automatic
- Only re-analyzes when content/config changes
- Use reasonable batch sizes (50-100 entities)
