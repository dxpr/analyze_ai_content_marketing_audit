# Analyze AI Content Marketing Audit

This module provides comprehensive AI-powered content marketing audit functionality for Drupal, analyzing content across multiple marketing factors including usability, knowledge level, actionability, accuracy, business value, messaging, and brand voice fit.

## Features

- **Multi-Factor Analysis**: Evaluate content across 7 key marketing factors
- **Configurable Factors**: Add, edit, and delete audit factors via admin interface
- **AI-Powered Analysis**: Uses configurable AI providers for intelligent content evaluation
- **Smart Caching**: Content and configuration-based caching for optimal performance
- **Batch Processing**: Analyze large volumes of content efficiently
- **Views Integration**: Create custom reports and dashboards
- **Color-Coded Results**: Visual feedback with the Views Color Scales module

## Default Marketing Factors

The module comes with 7 pre-configured marketing audit factors:

1. **Usability** - How easy is the content to understand and act upon for the target audience?
2. **Knowledge Level** - How well does the content demonstrate expertise and authority on the topic?
3. **Actionability** - How well does the content provide clear next steps or actionable insights?
4. **Accuracy** - How factually correct and up-to-date is the content?
5. **Business Value** - How well does the content support business goals and provide value to stakeholders?
6. **Messaging** - How clear, compelling, and consistent is the messaging?
7. **Brand Voice Fit** - How well does the content align with the brand voice and tone guidelines?

## Requirements

This module requires the following modules:

- **Analyze** (drupal/analyze) - Base analysis framework
- **AI** (drupal/ai) - AI provider integration

## Installation

1. Install via Composer:
   ```bash
   composer require "drupal/analyze_ai_content_marketing_audit"
   ```

2. Enable the module:
   ```bash
   drush en analyze_ai_content_marketing_audit
   ```

3. Configure permissions at `/admin/people/permissions#module-analyze_ai_content_marketing_audit`

## Configuration

### Basic Setup

1. **Configure AI Provider**: Set up your AI provider at `/admin/config/analyze/ai`
2. **Manage Factors**: Configure audit factors at `/admin/config/analyze/content-marketing-audit`
3. **Enable Analysis**: Enable the analyzer per content type at `/admin/config/system/analyze-settings`
4. **Batch Analysis**: Process existing content at `/admin/config/analyze/content-marketing-audit/batch`

### Factor Management

#### Adding Custom Factors

1. Go to `/admin/config/analyze/content-marketing-audit`
2. Click "Add factor"
3. Provide:
   - **Factor ID**: Machine name (e.g., `engagement_quality`)
   - **Label**: Human-readable name (e.g., "Engagement Quality")
   - **Description**: What this factor measures
   - **Weight**: Display order
   - **Status**: Whether the factor is active

#### Editing Factors

- Click "Edit" next to any factor in the factor list
- Modify label, description, weight, or status
- Factor IDs cannot be changed after creation

#### Deleting Factors

- Click "Delete" next to any factor
- **Warning**: This permanently removes the factor and all associated analysis results

### Content Type Configuration

Enable content marketing audit analysis per content type:

1. **Via Analyze Settings**:
   - Go to `/admin/config/system/analyze-settings`
   - Find "AI Content Marketing Audit"
   - Enable for specific content types

2. **Via Content Analysis**:
   - View any content piece
   - Look for the "Analyze" tab
   - Find "AI Content Marketing Audit" results

### Batch Processing

For analyzing large amounts of existing content:

1. Go to `/admin/config/analyze/content-marketing-audit/batch`
2. Select content types to analyze
3. Choose whether to force re-analysis of previously analyzed content
4. Set processing limits (recommended: 50-100 entities per batch)
5. Start the batch job

**Performance Notes**:
- Results are cached based on content and configuration hashes
- Only re-analyzes when content or factors change
- Batch processing prevents timeouts on large datasets

## Analysis Results

### Scoring System

Each factor is scored from **-1.0 to +1.0**:
- **1.0**: Excellent performance
- **0.7 to 0.9**: Good performance  
- **0.3 to 0.6**: Average performance
- **-0.3 to 0.2**: Needs improvement
- **-1.0 to -0.4**: Poor performance

### Overall Score

The module calculates an overall content marketing score by averaging all factor scores, providing a comprehensive view of content quality.

### Display Options

- **Summary View**: Gauge showing overall score + factor breakdown table
- **Individual Scores**: Detailed scores for each factor
- **Status Indicators**: Human-readable performance levels
- **Timestamps**: When analysis was performed

## Views Integration

### Default View

The module provides a default view at `/admin/reports/content-marketing-audit` showing:
- Content title (linked)
- Factor type
- Analysis date
- Content type
- Marketing audit score

### Creating Custom Views

1. **Create New View**:
   - Base table: "Content Marketing Audit Results"
   - Add fields, filters, and sorts as needed

2. **Available Fields**:
   - Entity information (title, type, ID)
   - Factor details (type, score)
   - Analysis metadata (date, hashes)
   - Content relationships

3. **Useful Filters**:
   - Factor Type (filter by specific factors)
   - Score Range (find high/low performing content)
   - Content Title (search specific content)
   - Analysis Date (recent analyses)

### Color-Coded Results

Install the **Views Color Scales** module for visual score representation:
- Low scores (-1.0) display in red
- High scores (+1.0) display in green
- Smooth color transitions for intermediate values

## Technical Details

### Database Schema

The module uses two main tables:

1. **`analyze_ai_content_marketing_audit_factors`**:
   - Stores configurable audit factors
   - Fields: id, label, description, weight, status

2. **`analyze_ai_content_marketing_audit_results`**:
   - Stores analysis results
   - Fields: entity info, factor_id, score, hashes, timestamp

### Caching Strategy

**Smart Cache Invalidation**:
- **Content Hash**: SHA256 of analyzed content
- **Config Hash**: MD5 of factor configuration + AI settings
- **Automatic Invalidation**: When content or configuration changes

**Performance Benefits**:
- Avoids redundant AI API calls
- Instant display of cached results
- Configurable cache duration

### AI Integration

**Prompt Structure**:
- Factor-specific prompts for targeted analysis
- Industry best practices consideration
- Consistent scoring methodology
- Temperature control for reliable results

**Error Handling**:
- Graceful fallbacks for AI failures
- Logging of analysis errors
- NULL score handling for unavailable results

## Troubleshooting

### Analysis Not Working

1. **Check AI Provider Configuration**:
   - Verify provider is configured at `/admin/config/analyze/ai`
   - Test API credentials and connectivity

2. **Verify Factor Configuration**:
   - Ensure factors are enabled
   - Check factor descriptions are clear

3. **Enable Analysis for Content Types**:
   - Go to `/admin/config/system/analyze-settings`
   - Enable "AI Content Marketing Audit" for desired content types

### Batch Processing Issues

1. **Timeout Errors**:
   - Reduce batch size (try 10-25 entities)
   - Increase PHP execution time limits

2. **Memory Issues**:
   - Process fewer entities per batch
   - Ensure adequate server memory

3. **API Rate Limits**:
   - Add delays between API calls
   - Use batch processing during off-peak hours

### No Results Displayed

1. **Check Permissions**:
   - Ensure users have "view analyze results" permission

2. **Verify Content Analysis**:
   - Check that content has been analyzed
   - Look for analysis errors in logs

3. **Cache Issues**:
   - Clear Drupal caches
   - Force re-analysis if needed

## Performance Optimization

### Best Practices

1. **Batch Processing**:
   - Process content during low-traffic periods
   - Use reasonable batch sizes (50-100 entities)
   - Monitor server resources during processing

2. **Cache Management**:
   - Let the module handle cache invalidation automatically
   - Don't manually clear analysis caches unless necessary

3. **Factor Configuration**:
   - Keep factor descriptions focused and clear
   - Disable unused factors to improve performance
   - Regularly review factor relevance

### Monitoring

- **View Analysis Logs**: Check `/admin/reports/dblog` for analysis errors
- **Monitor API Usage**: Track AI provider API consumption
- **Performance Metrics**: Use performance monitoring tools during batch processing

## API Usage

### Programmatic Access

```php
// Get storage service
$storage = \Drupal::service('analyze_ai_content_marketing_audit.storage');

// Get score for specific entity and factor
$score = $storage->getScore($entity, 'usability');

// Save new score
$storage->saveScore($entity, 'accuracy', 0.85);

// Get all factors
$factors = $storage->getFactors();

// Get specific factor
$factor = $storage->getFactor('business_value');
```

### Custom Analysis

```php
// Get analyzer plugin
$analyzer = \Drupal::service('plugin.manager.analyze')
  ->createInstance('content_marketing_audit_analyzer');

// Analyze specific factor
$score = $analyzer->analyze($entity, 'messaging');

// Get cached or analyze
$score = $analyzer->getCachedOrAnalyze($entity, 'brand_voice_fit');
```

## Support and Contribution

### Reporting Issues

- **Bug Reports**: Use the project issue queue
- **Feature Requests**: Submit detailed use cases
- **Security Issues**: Follow responsible disclosure practices

### Contributing

- **Code Contributions**: Follow Drupal coding standards
- **Documentation**: Help improve this README
- **Testing**: Report compatibility with different AI providers

## Compatibility

- **Drupal**: 10.2+ and 11.x
- **PHP**: 8.1+
- **AI Providers**: Any provider supported by the AI module
- **Dependencies**: Views (core), Analyze framework, AI module

## License

This project is licensed under the GPL-2.0+ license.

## Maintainers

Current maintainers:
- [Your Name] - [Drupal.org profile]

This project is sponsored by:
- [Your Organization] - [Website]

For support, feature requests, and bug reports, please use the project's issue 
queue.

<!-- Test comment for pre-commit hook -->
