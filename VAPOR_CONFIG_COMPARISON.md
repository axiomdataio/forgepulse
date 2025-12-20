# ForgePulse Configuration: Local vs Vapor

**Side-by-side comparison of configuration settings for local development and Laravel Vapor deployment**

---

## Environment Variables Comparison

### Local Development (.env)

```bash
# Application
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=forgepulse_local
DB_USERNAME=root
DB_PASSWORD=

# Queue
QUEUE_CONNECTION=sync

# Filesystem
FILESYSTEM_DISK=local

# Cache
CACHE_DRIVER=file

# Session
SESSION_DRIVER=file

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug

# ForgePulse Settings
FORGEPULSE_TEMPLATE_DISK=local
FORGEPULSE_QUEUE_CONNECTION=sync
FORGEPULSE_CACHE_STORE=file
FORGEPULSE_LOG_CHANNEL=stack
FORGEPULSE_ASYNC=false
FORGEPULSE_TIMEOUT=300
FORGEPULSE_MAX_RETRIES=3
```

### Vapor Production (.env.production)

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.vaporapp.io

# Database (Auto-configured by Vapor)
DB_CONNECTION=mysql
DB_HOST=vapor-rds-endpoint.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=vapor
DB_USERNAME=vapor
DB_PASSWORD=<VAPOR_MANAGED>

# Queue
QUEUE_CONNECTION=sqs
SQS_PREFIX=https://sqs.us-east-1.amazonaws.com/123456789
SQS_QUEUE=workflows

# Filesystem
FILESYSTEM_DISK=s3
AWS_BUCKET=vapor-bucket-xyz
AWS_DEFAULT_REGION=us-east-1

# Cache
CACHE_DRIVER=dynamodb
DYNAMODB_CACHE_TABLE=cache

# Session
SESSION_DRIVER=database

# Logging
LOG_CHANNEL=stderr

# ForgePulse Settings
FORGEPULSE_TEMPLATE_DISK=s3
FORGEPULSE_QUEUE_CONNECTION=sqs
FORGEPULSE_CACHE_STORE=dynamodb
FORGEPULSE_LOG_CHANNEL=stderr
FORGEPULSE_ASYNC=true
FORGEPULSE_TIMEOUT=300
FORGEPULSE_MAX_RETRIES=3
FORGEPULSE_QUEUE_NAME=workflows

# AWS Credentials (Auto-configured by Vapor)
AWS_ACCESS_KEY_ID=<VAPOR_MANAGED>
AWS_SECRET_ACCESS_KEY=<VAPOR_MANAGED>
```

---

## config/forgepulse.php Changes

### Before (Default Package Config)

```php
return [
    'execution' => [
        'timeout' => env('FORGEPULSE_TIMEOUT', 300),
        'max_retries' => env('FORGEPULSE_MAX_RETRIES', 3),
        'retry_delay' => env('FORGEPULSE_RETRY_DELAY', 5),
        'queue_connection' => env('FORGEPULSE_QUEUE_CONNECTION', 'default'),
        'queue_name' => env('FORGEPULSE_QUEUE_NAME', 'workflows'),
        'async_by_default' => env('FORGEPULSE_ASYNC', true),
    ],

    'templates' => [
        'disk' => env('FORGEPULSE_TEMPLATE_DISK', 'local'),
        'directory' => 'workflow-templates',
        'versioning' => env('FORGEPULSE_TEMPLATE_VERSIONING', true),
        'sharing' => env('FORGEPULSE_TEMPLATE_SHARING', true),
    ],

    'cache' => [
        'enabled' => env('FORGEPULSE_CACHE_ENABLED', true),
        'ttl' => env('FORGEPULSE_CACHE_TTL', 3600),
        'prefix' => 'forgepulse',
        'store' => env('FORGEPULSE_CACHE_STORE', 'default'),
    ],

    'logging' => [
        'enabled' => env('FORGEPULSE_LOGGING_ENABLED', true),
        'channel' => env('FORGEPULSE_LOG_CHANNEL', 'stack'),
        'log_data' => env('FORGEPULSE_LOG_DATA', true),
        'retention_days' => env('FORGEPULSE_LOG_RETENTION', 30),
    ],
];
```

### After (Vapor-Compatible Config)

```php
return [
    'execution' => [
        'timeout' => env('FORGEPULSE_TIMEOUT', 300),
        'max_retries' => env('FORGEPULSE_MAX_RETRIES', 3),
        'retry_delay' => env('FORGEPULSE_RETRY_DELAY', 5),
        
        // Fallback to Laravel's queue connection
        'queue_connection' => env('FORGEPULSE_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sqs')),
        'queue_name' => env('FORGEPULSE_QUEUE_NAME', 'workflows'),
        'async_by_default' => env('FORGEPULSE_ASYNC', true),
    ],

    'templates' => [
        // Fallback to Laravel's filesystem disk
        'disk' => env('FORGEPULSE_TEMPLATE_DISK', env('FILESYSTEM_DISK', 's3')),
        'directory' => 'workflow-templates',
        'versioning' => env('FORGEPULSE_TEMPLATE_VERSIONING', true),
        'sharing' => env('FORGEPULSE_TEMPLATE_SHARING', true),
    ],

    'cache' => [
        'enabled' => env('FORGEPULSE_CACHE_ENABLED', true),
        'ttl' => env('FORGEPULSE_CACHE_TTL', 3600),
        'prefix' => 'forgepulse',
        
        // Fallback to Laravel's cache driver
        'store' => env('FORGEPULSE_CACHE_STORE', env('CACHE_DRIVER', 'dynamodb')),
    ],

    'logging' => [
        'enabled' => env('FORGEPULSE_LOGGING_ENABLED', true),
        
        // Fallback to Laravel's log channel
        'channel' => env('FORGEPULSE_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'log_data' => env('FORGEPULSE_LOG_DATA', true),
        'retention_days' => env('FORGEPULSE_LOG_RETENTION', 30),
    ],
];
```

**Key Changes:**
- Added fallback to Laravel's global settings using nested `env()` calls
- Works seamlessly in both local and Vapor environments
- No need for separate config files

---

## config/filesystems.php

### Required Addition for S3

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

// Change default based on environment
'default' => env('FILESYSTEM_DISK', 'local'),
```

---

## config/queue.php

### Required Addition for SQS

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

// Change default based on environment
'default' => env('QUEUE_CONNECTION', 'sync'),
```

---

## config/cache.php

### Required Addition for DynamoDB

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

// Change default based on environment
'default' => env('CACHE_DRIVER', 'file'),
```

---

## config/session.php

### Required Change

```php
// Change from 'file' to 'database' for Vapor
'driver' => env('SESSION_DRIVER', 'database'),
```

**Don't forget to run:**

```bash
php artisan session:table
php artisan migrate
```

---

## vapor.yml

### Complete Configuration

```yaml
id: 1
name: your-app-name

environments:
  production:
    # Lambda Configuration
    memory: 1024
    cli-memory: 512
    runtime: php-8.3
    timeout: 60
    
    # Resources (created via Vapor dashboard)
    database: forgepulse-db
    cache: forgepulse-cache
    storage: forgepulse-storage
    
    # Queue Configuration
    queue: workflows
    
    # Build Commands (runs during deployment)
    build:
      - 'composer install --no-dev --optimize-autoloader'
      - 'php artisan config:cache'
      - 'php artisan route:cache'
      - 'php artisan view:cache'
      - 'php artisan event:cache'
      - 'npm ci && npm run build'
    
    # Deploy Commands (runs after deployment)
    deploy:
      - 'php artisan migrate --force'
      - 'php artisan vendor:publish --tag=forgepulse-assets --force'
    
    # Environment Variables (optional, prefer Vapor dashboard)
    variables:
      FORGEPULSE_ASYNC: 'true'
      FORGEPULSE_TIMEOUT: '300'
  
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

## Blade Layout Changes

### Before (Local)

```blade
<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <link href="{{ asset('vendor/forgepulse/css/forgepulse.css') }}" rel="stylesheet">
</head>
<body>
    {{ $slot }}
</body>
</html>
```

### After (Vapor - No Change Needed!)

```blade
<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <!-- Vapor's asset() helper automatically serves from CloudFront -->
    <link href="{{ asset('vendor/forgepulse/css/forgepulse.css') }}" rel="stylesheet">
</head>
<body>
    {{ $slot }}
</body>
</html>
```

**The `asset()` helper works transparently in both environments!**

---

## Testing Configuration

### Local Testing

```bash
# .env.testing
APP_ENV=testing
QUEUE_CONNECTION=sync
FORGEPULSE_ASYNC=false
FORGEPULSE_TEMPLATE_DISK=local
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

### Vapor Staging Testing

```bash
# Test via Vapor CLI
vapor env:pull staging
vapor tinker staging

# Or use staging environment variables
vapor env staging
```

---

## Feature Availability Matrix

| Feature | Local | Vapor Production | Notes |
|---------|-------|------------------|-------|
| **Workflow Creation** | ✅ | ✅ | No changes |
| **Step Execution** | ✅ | ✅ | No changes |
| **Template Export** | ✅ Local disk | ✅ S3 | Change disk config |
| **Template Import** | ✅ Local disk | ✅ S3 | Change disk config |
| **Queue Jobs** | ✅ Sync/DB | ✅ SQS | Change queue driver |
| **PCNTL Timeouts** | ✅ Yes | ❌ No | Use job timeouts instead |
| **Delay Steps** | ✅ sleep() | ⚠️ Inefficient | Consider queue delays |
| **Livewire UI** | ✅ | ✅ | Ensure assets deployed |
| **API Endpoints** | ✅ | ✅ | No changes |
| **Events** | ✅ | ✅ | No changes |
| **Notifications** | ✅ | ✅ | No changes |
| **Versioning** | ✅ | ✅ | No changes |
| **File Storage** | ✅ Local | ✅ S3 | Auto-switches via config |
| **Caching** | ✅ File | ✅ DynamoDB | Auto-switches via config |
| **Sessions** | ✅ File | ✅ Database | Must change explicitly |

---

## Migration Checklist

Use this checklist when preparing for Vapor:

### Configuration

- [ ] Update `config/forgepulse.php` with fallback env() calls
- [ ] Add S3 disk to `config/filesystems.php`
- [ ] Add SQS connection to `config/queue.php`
- [ ] Add DynamoDB to `config/cache.php`
- [ ] Change session driver to `database`
- [ ] Create `vapor.yml` configuration

### Environment Variables

- [ ] Set `FORGEPULSE_TEMPLATE_DISK=s3`
- [ ] Set `FORGEPULSE_QUEUE_CONNECTION=sqs`
- [ ] Set `FORGEPULSE_CACHE_STORE=dynamodb`
- [ ] Set `FORGEPULSE_LOG_CHANNEL=stderr`
- [ ] Set `QUEUE_CONNECTION=sqs`
- [ ] Set `FILESYSTEM_DISK=s3`
- [ ] Set `CACHE_DRIVER=dynamodb`
- [ ] Set `SESSION_DRIVER=database`

### Vapor Resources

- [ ] Create database via `vapor database`
- [ ] Create cache via `vapor cache`
- [ ] Create storage bucket via `vapor storage`
- [ ] Configure queue (auto-created)
- [ ] Push environment variables via `vapor env:push`

### Assets

- [ ] Publish ForgePulse assets
- [ ] Verify assets load via CDN after deployment
- [ ] Test Livewire components render correctly

### Testing

- [ ] Test workflow creation on staging
- [ ] Test workflow execution on staging
- [ ] Test template export/import on staging
- [ ] Test queue processing on staging
- [ ] Verify logs in CloudWatch
- [ ] Monitor metrics via `vapor metrics`

### Production

- [ ] Deploy to production
- [ ] Verify all workflows execute
- [ ] Monitor queue processing
- [ ] Set up alerts for failures
- [ ] Document custom configurations

---

## Debugging Configuration Issues

### Check Current Configuration

```bash
# Via Vapor Tinker
vapor tinker production

>>> config('forgepulse.templates.disk');
>>> config('forgepulse.execution.queue_connection');
>>> config('forgepulse.cache.store');
>>> config('filesystems.default');
>>> config('queue.default');
>>> config('cache.default');
```

### Check Environment Variables

```bash
vapor env:pull production
cat .env.production | grep FORGEPULSE
```

### Test Storage

```bash
vapor tinker production

>>> use Illuminate\Support\Facades\Storage;
>>> Storage::disk('s3')->put('test.txt', 'Hello Vapor');
>>> Storage::disk('s3')->exists('test.txt');
>>> Storage::disk('s3')->get('test.txt');
>>> Storage::disk('s3')->delete('test.txt');
```

### Test Queue

```bash
vapor tinker production

>>> use Illuminate\Support\Facades\Queue;
>>> Queue::push(function() { info('Queue works!'); });

# Check logs
vapor logs production --filter="Queue works"
```

### Test Cache

```bash
vapor tinker production

>>> use Illuminate\Support\Facades\Cache;
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
>>> Cache::forget('test');
```

---

## Performance Tuning

### Lambda Memory Settings

| Workflow Complexity | Recommended Memory | CLI Memory |
|---------------------|-------------------|------------|
| **Simple** (< 5 steps) | 512 MB | 256 MB |
| **Medium** (5-20 steps) | 1024 MB | 512 MB |
| **Complex** (20+ steps) | 2048 MB | 1024 MB |
| **Heavy Processing** | 3008 MB | 1536 MB |

### Queue Worker Settings

| Load Level | Workers | Timeout |
|------------|---------|---------|
| **Low** (< 100 jobs/hour) | 1-2 | 120s |
| **Medium** (100-1000 jobs/hour) | 3-5 | 180s |
| **High** (1000+ jobs/hour) | 5-10 | 300s |

Update in `vapor.yml`:

```yaml
queues:
  - name: workflows
    workers: 5
    timeout: 300
```

---

## Cost Comparison

### Local Development

- **Cost:** $0 (use local resources)
- **Best for:** Development, testing, small-scale

### Vapor Production

**Estimated Monthly Costs:**

| Resource | Usage | Cost (USD) |
|----------|-------|------------|
| **Lambda** | 1M invocations | ~$0.20 |
| **Lambda** | 1GB memory, 3s avg | ~$30 |
| **RDS** | db.t3.small | ~$25 |
| **S3** | 10GB storage | ~$0.23 |
| **DynamoDB** | On-demand | ~$1-5 |
| **SQS** | 1M requests | ~$0.40 |
| **CloudWatch** | Logs | ~$5 |
| **Total** | | **~$60-70/month** |

*Costs vary based on actual usage*

---

**Need more help?** See the full deployment guide in `VAPOR_DEPLOYMENT_GUIDE.md`
