# ForgePulse Laravel Vapor Deployment Guide

**Complete step-by-step guide for deploying ForgePulse workflows on Laravel Vapor**

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Understanding Vapor Limitations](#understanding-vapor-limitations)
3. [Initial Setup](#initial-setup)
4. [Configuration Changes](#configuration-changes)
5. [Asset Management](#asset-management)
6. [Queue Configuration](#queue-configuration)
7. [Storage Configuration](#storage-configuration)
8. [Database & Cache Setup](#database--cache-setup)
9. [Deployment Process](#deployment-process)
10. [Testing & Verification](#testing--verification)
11. [Troubleshooting](#troubleshooting)
12. [Production Optimizations](#production-optimizations)

---

## Prerequisites

### Required Services

- **Laravel Vapor Account** - [vapor.laravel.com](https://vapor.laravel.com)
- **AWS Account** - Vapor uses AWS Lambda, SQS, S3, etc.
- **Laravel Application** - Laravel 12+
- **PHP Version** - 8.3 or higher

### Install Vapor CLI

```bash
# Install globally
composer global require laravel/vapor-cli

# Or in your project
composer require --dev laravel/vapor-cli

# Login to Vapor
vapor login
```

### Install ForgePulse

```bash
# Install the package
composer require alizharb/forgepulse

# Publish configuration
php artisan vendor:publish --tag=forgepulse-config

# Publish migrations
php artisan vendor:publish --tag=forgepulse-migrations

# Run migrations locally first
php artisan migrate
```

---

## Understanding Vapor Limitations

### What Doesn't Work on Vapor

| Feature | Status | Impact | Solution |
|---------|--------|--------|----------|
| **PCNTL Extension** | ❌ Not Available | Step timeouts won't work | Use job-level timeouts |
| **Local File Storage** | ❌ Ephemeral | Files disappear between requests | Use S3 for all storage |
| **Blocking sleep()** | ⚠️ Inefficient | Wastes Lambda time | Use delayed queue jobs |
| **File Sessions** | ❌ Won't Persist | Session data lost | Use database/DynamoDB sessions |

### What Works Great on Vapor

✅ Database operations (RDS, Aurora)
✅ Queue jobs (SQS)
✅ Livewire components
✅ API endpoints
✅ Events & notifications
✅ S3 file operations

---

## Initial Setup

### 1. Initialize Vapor in Your Project

```bash
# Create vapor.yml configuration
vapor init
```

This creates a `vapor.yml` file in your project root.

### 2. Create Basic Vapor Configuration

**`vapor.yml`:**

```yaml
id: 1 # Your Vapor project ID
name: your-app-name

environments:
  production:
    memory: 1024
    cli-memory: 512
    runtime: php-8.3
    timeout: 60
    database: forgepulse-db
    cache: forgepulse-cache
    storage: forgepulse-storage
    
    queue: workflows
    
    build:
      - 'composer install --no-dev --optimize-autoloader'
      - 'php artisan config:cache'
      - 'php artisan route:cache'
      - 'php artisan view:cache'
      - 'npm ci && npm run build'
    
    deploy:
      - 'php artisan migrate --force'
    
  staging:
    memory: 512
    cli-memory: 512
    runtime: php-8.3
    timeout: 60
    database: forgepulse-staging-db
    cache: forgepulse-staging-cache
    storage: forgepulse-staging-storage
    queue: workflows-staging
    
    build:
      - 'composer install --optimize-autoloader'
      - 'php artisan config:cache'
      - 'php artisan route:cache'
      - 'php artisan view:cache'
      - 'npm ci && npm run build'
    
    deploy:
      - 'php artisan migrate --force'
```

---

## Configuration Changes

### 1. Update ForgePulse Configuration

**`config/forgepulse.php`:**

```php
<?php

return [
    'execution' => [
        // Use environment variable to switch between local and Vapor
        'timeout' => env('FORGEPULSE_TIMEOUT', 300),
        'max_retries' => env('FORGEPULSE_MAX_RETRIES', 3),
        'retry_delay' => env('FORGEPULSE_RETRY_DELAY', 5),
        
        // IMPORTANT: Use SQS on Vapor
        'queue_connection' => env('FORGEPULSE_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sqs')),
        'queue_name' => env('FORGEPULSE_QUEUE_NAME', 'workflows'),
        
        // Always async on Vapor
        'async_by_default' => env('FORGEPULSE_ASYNC', true),
    ],

    'templates' => [
        // IMPORTANT: Use S3 on Vapor, local for development
        'disk' => env('FORGEPULSE_TEMPLATE_DISK', env('FILESYSTEM_DISK', 's3')),
        'directory' => 'workflow-templates',
        'versioning' => env('FORGEPULSE_TEMPLATE_VERSIONING', true),
        'sharing' => env('FORGEPULSE_TEMPLATE_SHARING', true),
    ],

    'cache' => [
        'enabled' => env('FORGEPULSE_CACHE_ENABLED', true),
        'ttl' => env('FORGEPULSE_CACHE_TTL', 3600),
        'prefix' => 'forgepulse',
        
        // IMPORTANT: Use DynamoDB or Redis on Vapor
        'store' => env('FORGEPULSE_CACHE_STORE', env('CACHE_DRIVER', 'dynamodb')),
    ],

    'logging' => [
        'enabled' => env('FORGEPULSE_LOGGING_ENABLED', true),
        
        // IMPORTANT: Use stderr for CloudWatch on Vapor
        'channel' => env('FORGEPULSE_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'log_data' => env('FORGEPULSE_LOG_DATA', true),
        'retention_days' => env('FORGEPULSE_LOG_RETENTION', 30),
    ],

    // Rest of the configuration remains the same...
    'permissions' => [
        'enabled' => env('FORGEPULSE_RBAC_ENABLED', true),
        'can_create' => ['admin', 'workflow-manager'],
        'can_execute' => ['admin', 'workflow-manager', 'workflow-executor'],
        'can_manage_templates' => ['admin', 'workflow-manager'],
        'team_based' => env('FORGEPULSE_TEAM_BASED', false),
    ],

    'step_types' => [
        'action' => \AlizHarb\ForgePulse\Services\StepHandlers\ActionHandler::class,
        'condition' => \AlizHarb\ForgePulse\Services\StepHandlers\ConditionHandler::class,
        'delay' => \AlizHarb\ForgePulse\Services\StepHandlers\DelayHandler::class,
        'notification' => \AlizHarb\ForgePulse\Services\StepHandlers\NotificationHandler::class,
        'webhook' => \AlizHarb\ForgePulse\Services\StepHandlers\WebhookHandler::class,
        'event' => \AlizHarb\ForgePulse\Services\StepHandlers\EventHandler::class,
        'job' => \AlizHarb\ForgePulse\Services\StepHandlers\JobHandler::class,
    ],

    'events' => [
        'workflow_started' => true,
        'workflow_completed' => true,
        'workflow_failed' => true,
        'step_executed' => true,
        'step_failed' => true,
    ],

    'notifications' => [
        'enabled' => env('FORGEPULSE_NOTIFICATIONS_ENABLED', true),
        'channels' => ['mail', 'database'],
        'on_completion' => env('FORGEPULSE_NOTIFY_COMPLETION', true),
        'on_failure' => env('FORGEPULSE_NOTIFY_FAILURE', true),
    ],

    'ui' => [
        'dark_mode' => env('FORGEPULSE_DARK_MODE', true),
        'autosave_interval' => env('FORGEPULSE_AUTOSAVE_INTERVAL', 30),
        'grid_snap' => env('FORGEPULSE_GRID_SNAP', true),
        'grid_size' => env('FORGEPULSE_GRID_SIZE', 20),
        'zoom_enabled' => env('FORGEPULSE_ZOOM_ENABLED', true),
    ],

    'api' => [
        'enabled' => env('FORGEPULSE_API_ENABLED', true),
        'middleware' => ['api', 'auth:sanctum'],
        'rate_limit' => env('FORGEPULSE_API_RATE_LIMIT', '60,1'),
    ],

    'versioning' => [
        'enabled' => env('FORGEPULSE_VERSIONING_ENABLED', true),
        'max_versions' => env('FORGEPULSE_MAX_VERSIONS', 50),
        'auto_version_on_save' => env('FORGEPULSE_AUTO_VERSION', true),
        'retention_days' => env('FORGEPULSE_VERSION_RETENTION', 90),
    ],

    'teams' => [
        'enabled' => env('FORGEPULSE_TEAMS_ENABLED', false),
        'model' => env('FORGEPULSE_TEAM_MODEL', 'App\\Models\\Team'),
    ],
];
```

### 2. Environment Variables

**Local `.env`:**

```bash
# Local Development
FORGEPULSE_TEMPLATE_DISK=local
FORGEPULSE_QUEUE_CONNECTION=sync
FORGEPULSE_CACHE_STORE=file
FORGEPULSE_LOG_CHANNEL=stack
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
```

**Vapor Production Environment Variables:**

Configure these in the Vapor dashboard or via CLI:

```bash
# Via Vapor CLI
vapor env:pull production
```

Then edit `.env.production` and push back:

```bash
# Vapor Production Environment
APP_ENV=production
APP_DEBUG=false

# ForgePulse Vapor Settings
FORGEPULSE_TEMPLATE_DISK=s3
FORGEPULSE_QUEUE_CONNECTION=sqs
FORGEPULSE_CACHE_STORE=dynamodb
FORGEPULSE_LOG_CHANNEL=stderr
FORGEPULSE_ASYNC=true
FORGEPULSE_TIMEOUT=300
FORGEPULSE_MAX_RETRIES=3

# Laravel Vapor Settings
QUEUE_CONNECTION=sqs
FILESYSTEM_DISK=s3
SESSION_DRIVER=database
CACHE_DRIVER=dynamodb
LOG_CHANNEL=stderr

# AWS Configuration (Vapor sets these automatically)
AWS_BUCKET=your-vapor-bucket
AWS_DEFAULT_REGION=us-east-1

# Database (Vapor RDS)
DB_CONNECTION=mysql
DB_HOST=your-rds-endpoint
DB_PORT=3306
DB_DATABASE=forgepulse
DB_USERNAME=vapor
DB_PASSWORD=your-secure-password

# Queue
SQS_PREFIX=https://sqs.us-east-1.amazonaws.com/your-account-id
SQS_QUEUE=workflows
```

Push updated environment:

```bash
vapor env:push production
```

---

## Asset Management

### Option 1: Use Vapor Asset Sync (Recommended)

**1. Publish ForgePulse Assets:**

```bash
php artisan vendor:publish --tag=forgepulse-assets
```

**2. Update Your Layout to Use Vapor Asset Helper:**

**`resources/views/layouts/app.blade.php`:**

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- ForgePulse CSS - Uses Vapor asset helper -->
    <link href="{{ asset('vendor/forgepulse/css/forgepulse.css') }}" rel="stylesheet">
    
    <!-- Your app CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles
</head>
<body>
    {{ $slot }}
    
    @livewireScripts
</body>
</html>
```

**3. Add Asset Sync to Deploy:**

Update `vapor.yml`:

```yaml
deploy:
  - 'php artisan migrate --force'
  - 'php artisan vendor:publish --tag=forgepulse-assets --force'
```

Vapor automatically syncs `public/` to S3 and serves via CloudFront.

### Option 2: Bundle with Vite/Mix

**1. Copy Assets to Resources:**

```bash
# Create vendor directory
mkdir -p resources/css/vendor
mkdir -p resources/js/vendor

# Copy ForgePulse assets
cp vendor/alizharb/forgepulse/resources/css/forgepulse.css resources/css/vendor/
```

**2. Import in Your CSS:**

**`resources/css/app.css`:**

```css
@import './vendor/forgepulse.css';

/* Your app styles */
```

**3. Build with Vite:**

```bash
npm run build
```

Assets are automatically included in your Vite build and deployed with Vapor.

---

## Queue Configuration

### 1. Create SQS Queue via Vapor

```bash
# Via Vapor dashboard: Resources > Queues > Create Queue
# Or use AWS CLI:

aws sqs create-queue \
  --queue-name workflows \
  --region us-east-1
```

### 2. Configure Queue in `vapor.yml`

```yaml
environments:
  production:
    queue: workflows
    cli-memory: 512
    timeout: 60
```

### 3. Update Queue Configuration

**`config/queue.php`:**

```php
'connections' => [
    'sqs' => [
        'driver' => 'sqs',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
        'queue' => env('SQS_QUEUE', 'default'),
        'suffix' => env('SQS_SUFFIX'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'after_commit' => false,
    ],
],
```

### 4. Test Queue Locally

```bash
# Install SQS driver
composer require aws/aws-sdk-php

# Test dispatching a job
php artisan tinker

>>> use AlizHarb\ForgePulse\Models\Workflow;
>>> $workflow = Workflow::first();
>>> $workflow->execute(['test' => 'data']);
```

---

## Storage Configuration

### 1. Create S3 Bucket via Vapor

```bash
# Via Vapor CLI
vapor storage forgepulse-storage --region=us-east-1

# Or via dashboard: Resources > Storage > Create Bucket
```

### 2. Configure S3 in Laravel

**`config/filesystems.php`:**

```php
'disks' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
    ],
],

'default' => env('FILESYSTEM_DISK', 's3'),
```

### 3. Test S3 Storage

```bash
php artisan tinker

>>> use Illuminate\Support\Facades\Storage;
>>> Storage::disk('s3')->put('test.txt', 'Hello Vapor!');
>>> Storage::disk('s3')->get('test.txt');
```

---

## Database & Cache Setup

### 1. Create Database via Vapor

```bash
# Via Vapor CLI
vapor database forgepulse-db --region=us-east-1

# Or via dashboard: Resources > Databases > Create Database
```

### 2. Create Cache (DynamoDB or Redis)

**Option A: DynamoDB (Recommended for Vapor):**

```bash
# Via Vapor dashboard: Resources > Caches > Create DynamoDB Table
vapor cache forgepulse-cache
```

**`config/cache.php`:**

```php
'stores' => [
    'dynamodb' => [
        'driver' => 'dynamodb',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
        'endpoint' => env('DYNAMODB_ENDPOINT'),
    ],
],
```

**Option B: Redis (Elasticache):**

```bash
vapor cache forgepulse-cache --type=redis
```

### 3. Sessions Configuration

**Update `config/session.php`:**

```php
'driver' => env('SESSION_DRIVER', 'database'),
```

**Create sessions table:**

```bash
php artisan session:table
php artisan migrate
```

---

## Deployment Process

### 1. Pre-Deployment Checklist

```bash
# Ensure all tests pass
composer test

# Check for linting errors
composer analyse

# Ensure migrations are ready
php artisan migrate:status

# Build assets
npm run build

# Clear local caches
php artisan config:clear
php artisan cache:clear
```

### 2. Deploy to Staging First

```bash
# Deploy to staging environment
vapor deploy staging

# Monitor deployment
vapor logs staging --lines=100 --follow
```

### 3. Test Staging

```bash
# Open staging URL
vapor url staging

# Test workflow creation
curl https://your-staging-url.com/api/forgepulse/workflows \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"

# Check queue is working
vapor queue:monitor staging

# View logs
vapor logs staging --lines=50
```

### 4. Deploy to Production

```bash
# Deploy to production
vapor deploy production

# Monitor deployment
vapor logs production --lines=100 --follow

# Check health
vapor metrics production
```

---

## Testing & Verification

### 1. Test Workflow Execution

**Via Tinker:**

```bash
vapor tinker production

>>> use AlizHarb\ForgePulse\Models\Workflow;
>>> $workflow = Workflow::create([
...     'name' => 'Test Vapor Workflow',
...     'description' => 'Testing on Vapor',
...     'status' => 'active'
... ]);

>>> $workflow->steps()->create([
...     'name' => 'Send Notification',
...     'type' => 'notification',
...     'position' => 1,
...     'configuration' => [
...         'notification_class' => 'App\\Notifications\\TestNotification',
...         'recipients' => [1]
...     ]
... ]);

>>> $execution = $workflow->execute(['user_id' => 1]);
>>> $execution->status;
```

### 2. Test Template Export/Import

```bash
vapor tinker production

>>> use AlizHarb\ForgePulse\Services\TemplateManager;
>>> $manager = app(TemplateManager::class);

>>> // Export
>>> $workflow = Workflow::first();
>>> $path = $manager->export($workflow);
>>> echo "Exported to: $path";

>>> // Import
>>> $imported = $manager->import($path, 'Imported Test');
>>> $imported->name;
```

### 3. Test Queue Processing

```bash
# Check queue metrics
vapor queue:monitor production

# View queue logs
vapor logs production --filter="ExecuteWorkflowJob"

# Scale queue workers if needed
vapor queue:scale production --workers=5
```

### 4. Monitor CloudWatch Logs

```bash
# View recent logs
vapor logs production --lines=100

# Follow live logs
vapor logs production --follow

# Filter logs
vapor logs production --filter="workflow"
vapor logs production --filter="ERROR"
```

---

## Troubleshooting

### Problem: Assets Not Loading

**Symptom:** CSS styles missing, 404 errors for assets

**Solution:**

```bash
# Republish assets
php artisan vendor:publish --tag=forgepulse-assets --force

# Clear Vapor asset cache
vapor asset:prune production

# Redeploy
vapor deploy production
```

### Problem: Template Export Fails

**Symptom:** Error when exporting workflows

**Check S3 Configuration:**

```bash
vapor tinker production

>>> use Illuminate\Support\Facades\Storage;
>>> Storage::disk('s3')->exists('/');
>>> Storage::disk('s3')->put('test.txt', 'test');
```

**Ensure Environment Variables:**

```bash
vapor env:pull production
# Check AWS_BUCKET is set
vapor env:push production
```

### Problem: Queue Jobs Not Processing

**Symptom:** Workflows stuck in "pending" status

**Check Queue:**

```bash
# View queue metrics
vapor queue:monitor production

# Check logs
vapor logs production --filter="SQS"

# Scale workers
vapor queue:scale production --workers=3
```

### Problem: Database Connection Issues

**Symptom:** SQLSTATE errors

**Check Database:**

```bash
# Test connection
vapor tinker production

>>> DB::connection()->getPdo();
>>> DB::table('workflows')->count();
```

**Check Environment:**

```bash
vapor env:pull production
# Verify DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
```

### Problem: Memory Exhausted

**Symptom:** Lambda function out of memory

**Solution:**

Update `vapor.yml`:

```yaml
environments:
  production:
    memory: 2048  # Increase from 1024
    cli-memory: 1024  # Increase CLI memory
```

Redeploy:

```bash
vapor deploy production
```

### Problem: PCNTL Timeout Warning

**Symptom:** Warning about PCNTL not available

**This is expected!** The package gracefully handles this. No action needed.

To suppress warnings, update job timeout:

```bash
# In .env.production
FORGEPULSE_TIMEOUT=300
```

---

## Production Optimizations

### 1. Optimize Queue Performance

```yaml
# vapor.yml
environments:
  production:
    queue: workflows
    cli-memory: 512
    timeout: 120  # Increase for long workflows
    
    queues:
      - name: workflows
        workers: 5  # Scale based on load
        timeout: 300
```

### 2. Enable Caching

```bash
# Add to vapor.yml deploy hooks
deploy:
  - 'php artisan migrate --force'
  - 'php artisan config:cache'
  - 'php artisan route:cache'
  - 'php artisan view:cache'
  - 'php artisan event:cache'
```

### 3. Database Connection Pooling

Use RDS Proxy for better connection management:

```bash
# Create RDS Proxy via AWS Console or Vapor
vapor database:proxy forgepulse-db

# Update DB_HOST to proxy endpoint
```

### 4. Monitor Performance

```bash
# View metrics
vapor metrics production

# Set up alarms
vapor alarm production \
  --metric=5XXError \
  --threshold=10 \
  --period=5

# Monitor costs
vapor billing
```

### 5. Implement Workflow Cleanup

Create a scheduled command:

**`app/Console/Commands/CleanupOldWorkflowLogs.php`:**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use AlizHarb\ForgePulse\Models\WorkflowExecutionLog;

class CleanupOldWorkflowLogs extends Command
{
    protected $signature = 'forgepulse:cleanup';
    protected $description = 'Clean up old workflow execution logs';

    public function handle()
    {
        $retentionDays = config('forgepulse.logging.retention_days', 30);
        
        $deleted = WorkflowExecutionLog::where('created_at', '<', now()->subDays($retentionDays))
            ->delete();
        
        $this->info("Deleted {$deleted} old logs");
    }
}
```

**Schedule in `app/Console/Kernel.php`:**

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('forgepulse:cleanup')
        ->daily()
        ->onOneServer();
}
```

### 6. Set Up Monitoring Alerts

```bash
# Email on workflow failures
vapor alarm production \
  --metric=WorkflowFailed \
  --threshold=5 \
  --email=admin@example.com

# Slack notifications
vapor notification production \
  --slack=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

---

## Cost Optimization Tips

1. **Right-size Lambda Memory:** Start with 1024MB, adjust based on metrics
2. **Use Reserved Capacity:** For predictable workloads
3. **Optimize Queue Workers:** Don't over-provision workers
4. **Enable CloudFront Caching:** For assets and static content
5. **Use S3 Intelligent Tiering:** For workflow templates
6. **Set Lifecycle Policies:** Archive old logs to S3 Glacier

---

## Security Best Practices

### 1. Restrict S3 Bucket Access

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "AWS": "arn:aws:iam::ACCOUNT:role/vapor-role"
      },
      "Action": ["s3:GetObject", "s3:PutObject"],
      "Resource": "arn:aws:s3:::your-bucket/*"
    }
  ]
}
```

### 2. Enable Database Encryption

```bash
# Via Vapor dashboard: Databases > Settings > Enable Encryption
```

### 3. Use Secrets Manager for Sensitive Config

```bash
# Store API keys in AWS Secrets Manager
aws secretsmanager create-secret \
  --name forgepulse/production/api-key \
  --secret-string "your-secret-key"
```

**Access in Laravel:**

```php
$secret = Cache::remember('api-key', 3600, function () {
    $client = new \Aws\SecretsManager\SecretsManagerClient([
        'version' => '2017-10-17',
        'region' => 'us-east-1',
    ]);
    
    $result = $client->getSecretValue(['SecretId' => 'forgepulse/production/api-key']);
    return $result['SecretString'];
});
```

### 4. Enable VPC for Database

```yaml
# vapor.yml
environments:
  production:
    network: forgepulse-vpc
    database: forgepulse-db
```

---

## Rollback Procedure

If deployment fails:

```bash
# Rollback to previous deployment
vapor rollback production

# Or rollback to specific deployment
vapor deployments production
vapor rollback production 123

# Monitor rollback
vapor logs production --follow
```

---

## Summary Checklist

- [ ] Install ForgePulse package
- [ ] Configure `vapor.yml`
- [ ] Update `config/forgepulse.php` for Vapor compatibility
- [ ] Set Vapor environment variables
- [ ] Configure S3 bucket for templates
- [ ] Configure SQS queue
- [ ] Set up database (RDS)
- [ ] Configure cache (DynamoDB or Redis)
- [ ] Update sessions to database
- [ ] Configure asset deployment
- [ ] Test on staging environment
- [ ] Deploy to production
- [ ] Verify workflow execution
- [ ] Set up monitoring and alerts
- [ ] Configure backup strategy
- [ ] Document custom configurations

---

## Additional Resources

- **Vapor Documentation:** https://docs.vapor.build
- **ForgePulse Documentation:** https://alizharb.github.io/forgepulse/
- **Laravel Queues:** https://laravel.com/docs/queues
- **AWS Lambda Best Practices:** https://docs.aws.amazon.com/lambda/latest/dg/best-practices.html

---

## Support

If you encounter issues:

1. Check Vapor logs: `vapor logs production`
2. Review CloudWatch metrics: `vapor metrics production`
3. Test locally with Vapor-like settings
4. Open issue on GitHub: https://github.com/alizharb/forgepulse/issues
5. Contact Vapor support: support@vapor.build

---

**Last Updated:** December 2025
**ForgePulse Version:** 1.2.0+
**Laravel Version:** 12+
**Vapor Runtime:** PHP 8.3

