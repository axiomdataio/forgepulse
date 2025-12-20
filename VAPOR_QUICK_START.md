# ForgePulse Vapor Quick Start

**Get ForgePulse running on Laravel Vapor in under 30 minutes**

---

## 1. Install ForgePulse

```bash
composer require alizharb/forgepulse
php artisan vendor:publish --tag=forgepulse-config
php artisan vendor:publish --tag=forgepulse-migrations
php artisan migrate
```

---

## 2. Update Configuration

**`config/forgepulse.php`** - Change these lines:

```php
'execution' => [
    'queue_connection' => env('FORGEPULSE_QUEUE_CONNECTION', 'sqs'), // Changed
    // ...
],

'templates' => [
    'disk' => env('FORGEPULSE_TEMPLATE_DISK', 's3'), // Changed
    // ...
],

'cache' => [
    'store' => env('FORGEPULSE_CACHE_STORE', 'dynamodb'), // Changed
    // ...
],

'logging' => [
    'channel' => env('FORGEPULSE_LOG_CHANNEL', 'stderr'), // Changed
    // ...
],
```

---

## 3. Set Environment Variables

**Via Vapor Dashboard or CLI:**

```bash
vapor env:pull production
```

**Add to `.env.production`:**

```bash
# ForgePulse on Vapor
FORGEPULSE_TEMPLATE_DISK=s3
FORGEPULSE_QUEUE_CONNECTION=sqs
FORGEPULSE_CACHE_STORE=dynamodb
FORGEPULSE_LOG_CHANNEL=stderr
FORGEPULSE_ASYNC=true
FORGEPULSE_TIMEOUT=300

# Laravel Vapor Defaults
QUEUE_CONNECTION=sqs
FILESYSTEM_DISK=s3
SESSION_DRIVER=database
CACHE_DRIVER=dynamodb
LOG_CHANNEL=stderr
```

```bash
vapor env:push production
```

---

## 4. Create vapor.yml

```yaml
id: 1
name: your-app

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
      - 'php artisan vendor:publish --tag=forgepulse-assets --force'
```

---

## 5. Create Vapor Resources

```bash
# Create database
vapor database forgepulse-db

# Create cache
vapor cache forgepulse-cache

# Create storage
vapor storage forgepulse-storage

# Create queue
# (Done automatically via vapor.yml queue: workflows)
```

---

## 6. Handle Assets

**Option A: Vapor Auto-Sync (Recommended)**

Update your layout to use `asset()` helper:

```blade
<!-- resources/views/layouts/app.blade.php -->
<link href="{{ asset('vendor/forgepulse/css/forgepulse.css') }}" rel="stylesheet">
```

Vapor automatically syncs `public/` to S3 on deployment.

**Option B: Bundle with Vite**

```bash
cp vendor/alizharb/forgepulse/resources/css/forgepulse.css resources/css/vendor/
```

```css
/* resources/css/app.css */
@import './vendor/forgepulse.css';
```

---

## 7. Deploy

```bash
# Deploy to production
vapor deploy production

# Monitor deployment
vapor logs production --follow

# Check status
vapor url production
```

---

## 8. Verify Deployment

```bash
# Test via Tinker
vapor tinker production

>>> use AlizHarb\ForgePulse\Models\Workflow;
>>> $workflow = Workflow::create(['name' => 'Test', 'status' => 'active']);
>>> $workflow->execute(['test' => 'data']);
>>> exit

# Check queue
vapor queue:monitor production

# View logs
vapor logs production --lines=50
```

---

## Common Issues & Fixes

### Assets Not Loading

```bash
php artisan vendor:publish --tag=forgepulse-assets --force
vapor deploy production
```

### Queue Not Processing

```bash
# Check queue workers
vapor queue:monitor production

# Scale if needed
vapor queue:scale production --workers=3
```

### S3 Access Denied

```bash
# Check environment
vapor env:pull production
# Verify AWS_BUCKET is set

# Test S3 access
vapor tinker production
>>> Storage::disk('s3')->put('test.txt', 'test');
```

### Database Connection Error

```bash
# Check database status
vapor database:show forgepulse-db

# Verify credentials in environment
vapor env:pull production
```

---

## Key Differences from Local

| Feature | Local | Vapor |
|---------|-------|-------|
| **Storage** | `local` disk | `s3` disk |
| **Queue** | `sync`/`database` | `sqs` |
| **Cache** | `file` | `dynamodb` |
| **Sessions** | `file` | `database` |
| **Logs** | `stack` | `stderr` (CloudWatch) |
| **Assets** | `public/` | S3 + CloudFront |

---

## What Doesn't Work on Vapor

- ❌ **PCNTL timeouts** - Use job-level timeouts instead
- ❌ **Local file storage** - Use S3 for everything
- ⚠️ **Blocking sleep()** - Inefficient but works (consider queue delays)

---

## Monitoring

```bash
# View logs
vapor logs production --lines=100 --follow

# Check metrics
vapor metrics production

# Monitor queue
vapor queue:monitor production

# View costs
vapor billing
```

---

## Rollback

```bash
# View deployments
vapor deployments production

# Rollback to previous
vapor rollback production

# Or specific deployment
vapor rollback production 123
```

---

## Cost Optimization

1. Start with **1024MB memory**, adjust based on metrics
2. Right-size queue workers (don't over-provision)
3. Enable CloudFront caching for assets
4. Use S3 Intelligent Tiering for templates
5. Clean up old logs regularly

**Monitor costs:**

```bash
vapor billing
vapor metrics production --period=7
```

---

## Next Steps

1. ✅ Deploy to staging first
2. ✅ Test all workflow types
3. ✅ Set up monitoring alerts
4. ✅ Configure backups
5. ✅ Document your custom step handlers
6. ✅ Set up CI/CD pipeline

---

## Resources

- **Full Guide:** See `VAPOR_DEPLOYMENT_GUIDE.md`
- **Vapor Docs:** https://docs.vapor.build
- **ForgePulse Docs:** https://alizharb.github.io/forgepulse/
- **Support:** https://github.com/alizharb/forgepulse/issues

---

**Need help?** Check the full guide or open an issue on GitHub.
