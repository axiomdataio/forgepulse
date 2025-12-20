# Laravel Vapor Migration Guide for ForgePulse

This guide outlines the configuration changes required to migrate the ForgePulse workflow package to a Laravel application deployed on Laravel Vapor.

## Overview

Laravel Vapor is a serverless deployment platform for Laravel applications running on AWS Lambda. It requires specific configurations for storage, queues, caching, and other stateless operations.

---

## 1. Queue Configuration

### Current Configuration
ForgePulse uses configurable queue settings in `config/forgepulse.php`:
- `queue_connection`: defaults to 'default'
- `queue_name`: defaults to 'workflows'

### Vapor Requirements

#### Update `config/forgepulse.php`
```php
'execution' => [
    // Queue connection for workflow jobs (use 'sqs' for Vapor)
    'queue_connection' => env('FORGEPULSE_QUEUE_CONNECTION', 'sqs'),
    
    // Queue name for workflow jobs
    'queue_name' => env('FORGEPULSE_QUEUE_NAME', 'workflows'),
],
```

#### Update `config/queue.php`
Ensure SQS is configured as the default queue driver:

```php
'default' => env('QUEUE_CONNECTION', 'sqs'),

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

#### Environment Variables (.env)
```env
FORGEPULSE_QUEUE_CONNECTION=sqs
FORGEPULSE_QUEUE_NAME=workflows
QUEUE_CONNECTION=sqs
SQS_PREFIX=https://sqs.us-east-1.amazonaws.com/YOUR_ACCOUNT_ID
SQS_QUEUE=workflows
AWS_DEFAULT_REGION=us-east-1
```

#### Vapor Configuration (vapor.yml)
```yaml
id: YOUR_PROJECT_ID
name: your-app-name
environments:
  production:
    queues:
      - workflows
    timeout: 300  # Match FORGEPULSE_TIMEOUT
```

---

## 2. Storage Configuration

### Current Configuration
ForgePulse stores workflow templates on disk:
- `templates.disk`: defaults to 'local'
- `templates.directory`: 'workflow-templates'

### Vapor Requirements

Lambda functions have read-only filesystems except for `/tmp` (512MB limit). You **must** use S3 for persistent storage.

#### Update `config/forgepulse.php`
```php
'templates' => [
    // Storage disk for template exports (use 's3' for Vapor)
    'disk' => env('FORGEPULSE_TEMPLATE_DISK', 's3'),
    
    // Directory for template files
    'directory' => env('FORGEPULSE_TEMPLATE_DIR', 'workflow-templates'),
],
```

#### Update `config/filesystems.php`
```php
'default' => env('FILESYSTEM_DISK', 's3'),

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
```

#### Environment Variables (.env)
```env
FORGEPULSE_TEMPLATE_DISK=s3
FORGEPULSE_TEMPLATE_DIR=workflow-templates
FILESYSTEM_DISK=s3
AWS_BUCKET=your-bucket-name
AWS_DEFAULT_REGION=us-east-1
```

#### Vapor Configuration (vapor.yml)
Vapor automatically creates and configures an S3 bucket, or you can specify your own:

```yaml
environments:
  production:
    storage: your-custom-bucket-name  # Optional: use custom bucket
```

---

## 3. Cache Configuration

### Current Configuration
ForgePulse uses configurable caching:
- `cache.store`: defaults to 'default'
- `cache.prefix`: 'forgepulse'

### Vapor Requirements

Lambda's ephemeral filesystem makes file-based caching unreliable. Use DynamoDB or Redis.

#### Update `config/forgepulse.php`
```php
'cache' => [
    // Enable workflow definition caching
    'enabled' => env('FORGEPULSE_CACHE_ENABLED', true),
    
    // Cache store (use 'dynamodb' or 'redis' for Vapor)
    'store' => env('FORGEPULSE_CACHE_STORE', 'dynamodb'),
    
    // Cache TTL (in seconds)
    'ttl' => env('FORGEPULSE_CACHE_TTL', 3600),
    
    // Cache key prefix
    'prefix' => 'forgepulse',
],
```

#### Update `config/cache.php`

**Option A: DynamoDB (Recommended for Vapor)**
```php
'default' => env('CACHE_DRIVER', 'dynamodb'),

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

**Option B: Redis (Requires ElastiCache)**
```php
'default' => env('CACHE_DRIVER', 'redis'),

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],
```

#### Environment Variables (.env)

**For DynamoDB:**
```env
FORGEPULSE_CACHE_STORE=dynamodb
CACHE_DRIVER=dynamodb
DYNAMODB_CACHE_TABLE=cache
```

**For Redis:**
```env
FORGEPULSE_CACHE_STORE=redis
CACHE_DRIVER=redis
REDIS_HOST=your-elasticache-endpoint.cache.amazonaws.com
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### Vapor Configuration (vapor.yml)

**For DynamoDB:**
```yaml
environments:
  production:
    cache: dynamodb  # Vapor creates the table automatically
```

**For Redis:**
```yaml
environments:
  production:
    cache: your-elasticache-cluster-name
```

---

## 4. Database Configuration

### Current Configuration
ForgePulse uses standard Laravel migrations and Eloquent models.

### Vapor Requirements

Vapor works with RDS (MySQL, PostgreSQL) or Aurora Serverless.

#### Update `config/database.php`
No changes needed, but ensure proper connection settings:

```php
'connections' => [
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

#### Vapor Configuration (vapor.yml)
```yaml
environments:
  production:
    database: your-database-name  # Vapor-managed RDS
    # OR
    database: your-existing-rds-cluster-id  # Existing RDS
```

#### Running Migrations
```bash
vapor deploy production --migrations
```

---

## 5. Session Configuration

### Vapor Requirements

File-based sessions won't work. Use database, DynamoDB, or Redis.

#### Update `config/session.php`
```php
'driver' => env('SESSION_DRIVER', 'dynamodb'),

'table' => 'sessions',  // For database driver
```

#### Environment Variables (.env)
```env
SESSION_DRIVER=dynamodb
SESSION_LIFETIME=120
```

#### Vapor Configuration (vapor.yml)
Sessions are automatically configured when you set the cache driver.

---

## 6. Logging Configuration

### Current Configuration
ForgePulse uses configurable logging:
- `logging.channel`: defaults to 'stack'

### Vapor Requirements

Use CloudWatch for centralized logging.

#### Update `config/forgepulse.php`
```php
'logging' => [
    // Log channel (use 'stderr' for Vapor CloudWatch)
    'channel' => env('FORGEPULSE_LOG_CHANNEL', 'stderr'),
    
    // Enable detailed execution logging
    'enabled' => env('FORGEPULSE_LOGGING_ENABLED', true),
],
```

#### Update `config/logging.php`
```php
'default' => env('LOG_CHANNEL', 'stderr'),

'channels' => [
    'stderr' => [
        'driver' => 'monolog',
        'level' => env('LOG_LEVEL', 'debug'),
        'handler' => StreamHandler::class,
        'formatter' => env('LOG_STDERR_FORMATTER'),
        'with' => [
            'stream' => 'php://stderr',
        ],
    ],
],
```

#### Environment Variables (.env)
```env
FORGEPULSE_LOG_CHANNEL=stderr
LOG_CHANNEL=stderr
LOG_LEVEL=info
```

---

## 7. Asset Publishing

### Issue
ForgePulse publishes CSS/JS assets to `public/vendor/forgepulse/` which won't work on Lambda's read-only filesystem.

### Solution

#### Option A: Use Mix/Vite to Bundle Assets
Copy assets from package to your app's resources directory:

```bash
cp -r vendor/alizharb/forgepulse/resources/css resources/css/forgepulse
cp -r vendor/alizharb/forgepulse/resources/js resources/js/forgepulse
```

Import in your build process:
```js
// vite.config.js
export default defineConfig({
    plugins: [
        laravel([
            'resources/css/app.css',
            'resources/css/forgepulse/forgepulse.css',
            'resources/js/app.js',
            'resources/js/forgepulse/app.js',
        ]),
    ],
});
```

#### Option B: Serve from S3/CloudFront
Publish assets to S3 during deployment:

```yaml
# vapor.yml
environments:
  production:
    build:
      - 'php artisan forgepulse:publish-assets'
```

Create custom command:
```php
// app/Console/Commands/PublishForgePulseAssets.php
Artisan::command('forgepulse:publish-assets', function () {
    $disk = Storage::disk('s3-public');
    
    // Copy CSS
    $cssPath = base_path('vendor/alizharb/forgepulse/resources/css');
    foreach (File::files($cssPath) as $file) {
        $disk->put('vendor/forgepulse/css/' . $file->getFilename(), $file->getContents());
    }
    
    // Copy JS
    $jsPath = base_path('vendor/alizharb/forgepulse/resources/js');
    foreach (File::files($jsPath) as $file) {
        $disk->put('vendor/forgepulse/js/' . $file->getFilename(), $file->getContents());
    }
});
```

Update views to use CDN URLs:
```blade
<link href="{{ config('app.asset_url') }}/vendor/forgepulse/css/forgepulse.css" rel="stylesheet">
```

---

## 8. Environment Variables

### Complete .env Configuration for Vapor

```env
# Application
APP_NAME="ForgePulse App"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.vapor-domain.com

# Database (Vapor-managed)
DB_CONNECTION=mysql
DB_HOST=your-rds-endpoint.amazonaws.com
DB_PORT=3306
DB_DATABASE=forge
DB_USERNAME=forge
DB_PASSWORD=your-secure-password

# Queue (SQS)
QUEUE_CONNECTION=sqs
SQS_PREFIX=https://sqs.us-east-1.amazonaws.com/YOUR_ACCOUNT_ID
SQS_QUEUE=workflows
FORGEPULSE_QUEUE_CONNECTION=sqs
FORGEPULSE_QUEUE_NAME=workflows

# Storage (S3)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
FORGEPULSE_TEMPLATE_DISK=s3

# Cache (DynamoDB)
CACHE_DRIVER=dynamodb
DYNAMODB_CACHE_TABLE=cache
FORGEPULSE_CACHE_STORE=dynamodb

# Session
SESSION_DRIVER=dynamodb
SESSION_LIFETIME=120

# Logging
LOG_CHANNEL=stderr
FORGEPULSE_LOG_CHANNEL=stderr
LOG_LEVEL=info

# ForgePulse Specific
FORGEPULSE_TIMEOUT=300
FORGEPULSE_MAX_RETRIES=3
FORGEPULSE_RETRY_DELAY=5
FORGEPULSE_ASYNC=true
FORGEPULSE_CACHE_ENABLED=true
FORGEPULSE_CACHE_TTL=3600
FORGEPULSE_LOGGING_ENABLED=true
FORGEPULSE_LOG_RETENTION=30
FORGEPULSE_API_ENABLED=true
FORGEPULSE_API_RATE_LIMIT=60,1
```

---

## 9. Vapor CLI Configuration (vapor.yml)

### Complete vapor.yml Example

```yaml
id: YOUR_PROJECT_ID
name: forgepulse-app
environments:
  production:
    # Memory allocation (128-10240 MB)
    memory: 1024
    
    # CLI memory for commands
    cli-memory: 512
    
    # Timeout (max 900 seconds / 15 minutes)
    timeout: 300
    
    # Database
    database: forgepulse-production
    
    # Cache
    cache: dynamodb
    
    # Storage
    storage: forgepulse-storage-bucket
    
    # Queues
    queues:
      - workflows
    
    # Domain
    domain: app.yourdomain.com
    
    # Build commands
    build:
      - 'composer install --no-dev --optimize-autoloader'
      - 'php artisan config:cache'
      - 'php artisan route:cache'
      - 'php artisan view:cache'
      - 'npm ci && npm run build'
    
    # Deploy commands
    deploy:
      - 'php artisan migrate --force'
      - 'php artisan optimize'
    
    # Environment
    runtime: 'php-8.3'
    
    # Concurrency (auto-scaling)
    concurrency: 50
    
    # Scheduler (for scheduled tasks)
    scheduler: true
    
    # Octane (optional, for better performance)
    # octane: true
```

---

## 10. Code Changes Required

### Update Service Provider

Modify `ForgePulseServiceProvider.php` to detect Vapor environment:

```php
public function boot(): void
{
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    $this->loadViewsFrom(__DIR__.'/../resources/views', 'forgepulse');
    $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'forgepulse');

    $this->registerLivewireComponents();
    $this->registerPolicies();
    $this->registerApiRoutes();

    if ($this->app->runningInConsole()) {
        $this->publishes([
            __DIR__.'/../config/forgepulse.php' => config_path('forgepulse.php'),
        ], 'forgepulse-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'forgepulse-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/forgepulse'),
        ], 'forgepulse-views');

        // Only publish assets if NOT running on Vapor
        if (! isset($_ENV['VAPOR_SSM_PATH'])) {
            $this->publishes([
                __DIR__.'/../resources/js' => public_path('vendor/forgepulse/js'),
                __DIR__.'/../resources/css' => public_path('vendor/forgepulse/css'),
            ], 'forgepulse-assets');
        }
    }
}
```

### Update TemplateManager Service

Add validation for S3 operations:

```php
public function export(Workflow $workflow, ?string $filename = null): string
{
    $diskName = config('forgepulse.templates.disk', 'local');
    $disk = Storage::disk($diskName);
    
    // Ensure disk is writable (important for Vapor)
    if ($diskName === 'local' && isset($_ENV['VAPOR_SSM_PATH'])) {
        throw new \RuntimeException(
            'Cannot use local disk on Vapor. Please configure S3 storage.'
        );
    }
    
    $directory = config('forgepulse.templates.directory', 'workflow-templates');
    $filename = $filename ?? $this->generateFilename($workflow);
    $path = "{$directory}/{$filename}";

    // ... rest of the method
}
```

---

## 11. Testing on Vapor

### Local Testing with Vapor CLI

```bash
# Install Vapor CLI
composer require laravel/vapor-cli

# Test locally before deploying
vapor local
```

### Deploy to Vapor

```bash
# Initial deployment
vapor deploy production

# With migrations
vapor deploy production --migrations

# View logs
vapor logs production

# Monitor queue
vapor queue:monitor production

# Check metrics
vapor metrics production
```

---

## 12. Performance Optimizations

### Warm-up Configuration

Add to `vapor.yml` to reduce cold starts:

```yaml
environments:
  production:
    warm: 10  # Keep 10 instances warm
```

### Optimize Job Processing

Update `ExecuteWorkflowJob.php`:

```php
/**
 * The number of seconds the job can run before timing out.
 */
public int $timeout;

public function __construct(
    public readonly WorkflowExecution $execution
) {
    $this->tries = config('forgepulse.execution.max_retries', 3);
    
    // Vapor Lambda max timeout is 900 seconds (15 minutes)
    $configTimeout = config('forgepulse.execution.timeout', 3600);
    $this->timeout = min($configTimeout, 900);
    
    $this->onQueue(config('forgepulse.execution.queue_name', 'workflows'));
}
```

---

## 13. Monitoring and Debugging

### CloudWatch Logs

Access logs via Vapor CLI:
```bash
vapor logs production --filter="ForgePulse"
```

### Queue Monitoring

```bash
vapor queue:monitor production
```

### Custom Metrics

Add CloudWatch metrics for workflow executions:

```php
// In WorkflowEngine.php
use Illuminate\Support\Facades\Log;

public function execute(WorkflowExecution $execution): void
{
    Log::info('Workflow execution started', [
        'workflow_id' => $execution->workflow_id,
        'execution_id' => $execution->id,
        'environment' => config('app.env'),
    ]);
    
    // ... execution logic
    
    Log::info('Workflow execution completed', [
        'execution_id' => $execution->id,
        'duration' => $execution->completed_at->diffInSeconds($execution->started_at),
    ]);
}
```

---

## 14. Security Considerations

### IAM Permissions

Ensure your Vapor IAM role has proper permissions:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::your-bucket-name/*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "sqs:SendMessage",
        "sqs:ReceiveMessage",
        "sqs:DeleteMessage",
        "sqs:GetQueueAttributes"
      ],
      "Resource": "arn:aws:sqs:*:*:workflows"
    },
    {
      "Effect": "Allow",
      "Action": [
        "dynamodb:GetItem",
        "dynamodb:PutItem",
        "dynamodb:UpdateItem",
        "dynamodb:DeleteItem"
      ],
      "Resource": "arn:aws:dynamodb:*:*:table/cache"
    }
  ]
}
```

### Environment Variables

Never commit sensitive values. Use Vapor's environment management:

```bash
vapor env:set production AWS_SECRET_ACCESS_KEY=your-secret
vapor env:set production DB_PASSWORD=your-db-password
```

---

## 15. Migration Checklist

- [ ] Update `config/forgepulse.php` with Vapor-compatible defaults
- [ ] Configure SQS queue connection in `config/queue.php`
- [ ] Configure S3 storage disk in `config/filesystems.php`
- [ ] Configure DynamoDB/Redis cache in `config/cache.php`
- [ ] Update session driver to `dynamodb` or `database`
- [ ] Update log channel to `stderr`
- [ ] Create `vapor.yml` configuration file
- [ ] Set environment variables via Vapor CLI
- [ ] Update asset publishing strategy
- [ ] Add Vapor environment detection to service provider
- [ ] Update job timeout limits (max 900 seconds)
- [ ] Test locally with `vapor local`
- [ ] Deploy to staging environment first
- [ ] Run migrations via `vapor deploy --migrations`
- [ ] Monitor CloudWatch logs for errors
- [ ] Test queue processing
- [ ] Test file upload/download
- [ ] Test workflow execution end-to-end
- [ ] Configure auto-scaling and warm instances
- [ ] Set up CloudWatch alarms
- [ ] Document deployment process for team

---

## 16. Common Issues and Solutions

### Issue: "No such file or directory" errors

**Solution:** You're trying to write to read-only filesystem. Use S3 for storage.

```php
// Bad
file_put_contents('/var/www/file.json', $data);

// Good
Storage::disk('s3')->put('file.json', $data);
```

### Issue: Queue jobs timing out

**Solution:** Reduce job timeout to max 900 seconds or split into smaller jobs.

```php
$this->timeout = min(config('forgepulse.execution.timeout'), 900);
```

### Issue: Cache not persisting between requests

**Solution:** Switch from file cache to DynamoDB or Redis.

```env
CACHE_DRIVER=dynamodb
FORGEPULSE_CACHE_STORE=dynamodb
```

### Issue: Session data lost

**Solution:** Use database or DynamoDB sessions.

```env
SESSION_DRIVER=dynamodb
```

### Issue: Assets not loading

**Solution:** Serve assets from S3/CloudFront or bundle with Vite.

---

## Conclusion

Migrating ForgePulse to Laravel Vapor requires updating storage, queues, caching, and session handling to use AWS-managed services. The key changes are:

1. **Queues:** Use SQS instead of database/Redis
2. **Storage:** Use S3 instead of local filesystem
3. **Cache:** Use DynamoDB or ElastiCache instead of file cache
4. **Sessions:** Use DynamoDB or database instead of file sessions
5. **Logging:** Use CloudWatch via stderr channel
6. **Assets:** Bundle with Vite or serve from S3

Follow this guide step-by-step, test thoroughly in a staging environment, and monitor CloudWatch logs during initial deployment.

For additional support, refer to:
- [Laravel Vapor Documentation](https://docs.vapor.build)
- [ForgePulse Documentation](./docs/introduction.md)
