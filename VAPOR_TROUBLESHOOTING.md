# ForgePulse Vapor Troubleshooting Guide

**Solutions to common issues when running ForgePulse on Laravel Vapor**

---

## Table of Contents

1. [Assets Not Loading](#assets-not-loading)
2. [Queue Jobs Not Processing](#queue-jobs-not-processing)
3. [Template Export/Import Failures](#template-exportimport-failures)
4. [Database Connection Issues](#database-connection-issues)
5. [Memory Exhausted Errors](#memory-exhausted-errors)
6. [Timeout Issues](#timeout-issues)
7. [Session Problems](#session-problems)
8. [Cache Not Working](#cache-not-working)
9. [Livewire Component Errors](#livewire-component-errors)
10. [Permission Denied Errors](#permission-denied-errors)

---

## Assets Not Loading

### Symptom

- CSS styles missing on workflow builder
- 404 errors for `/vendor/forgepulse/css/forgepulse.css`
- Livewire components look unstyled

### Diagnosis

```bash
# Check if assets were published
vapor ssh production
ls -la public/vendor/forgepulse/

# Check CloudFront distribution
vapor url production
# Visit: https://your-url.com/vendor/forgepulse/css/forgepulse.css
```

### Solution 1: Republish and Redeploy

```bash
# Locally, ensure assets are published
php artisan vendor:publish --tag=forgepulse-assets --force

# Add to vapor.yml if not present
# deploy:
#   - 'php artisan vendor:publish --tag=forgepulse-assets --force'

# Redeploy
vapor deploy production

# Clear CloudFront cache if needed
vapor asset:prune production
```

### Solution 2: Bundle with Vite (Recommended)

```bash
# Copy assets to resources
mkdir -p resources/css/vendor
cp vendor/alizharb/forgepulse/resources/css/forgepulse.css resources/css/vendor/

# Import in app.css
echo '@import "./vendor/forgepulse.css";' >> resources/css/app.css

# Build
npm run build

# Redeploy
vapor deploy production
```

### Solution 3: Verify Layout File

**`resources/views/layouts/app.blade.php`:**

```blade
<!-- Make sure you're using the asset() helper -->
<link href="{{ asset('vendor/forgepulse/css/forgepulse.css') }}" rel="stylesheet">

<!-- NOT this (won't work on Vapor) -->
<!-- <link href="/vendor/forgepulse/css/forgepulse.css" rel="stylesheet"> -->
```

### Verification

```bash
# Check asset URL
vapor tinker production

>>> asset('vendor/forgepulse/css/forgepulse.css');
# Should return CloudFront URL

# Visit the URL in browser
curl -I https://your-cloudfront-url.cloudfront.net/vendor/forgepulse/css/forgepulse.css
# Should return 200 OK
```

---

## Queue Jobs Not Processing

### Symptom

- Workflows stuck in "pending" status
- `WorkflowExecution` records created but never complete
- No queue activity in Vapor metrics

### Diagnosis

```bash
# Check queue metrics
vapor queue:monitor production

# Check SQS queue
aws sqs get-queue-attributes \
  --queue-url https://sqs.us-east-1.amazonaws.com/123456789/workflows \
  --attribute-names ApproximateNumberOfMessages

# Check logs for queue errors
vapor logs production --filter="SQS" --lines=50
```

### Solution 1: Verify Queue Configuration

```bash
# Check environment variables
vapor env:pull production
grep -E "(QUEUE|SQS|FORGEPULSE_QUEUE)" .env.production

# Should have:
# QUEUE_CONNECTION=sqs
# FORGEPULSE_QUEUE_CONNECTION=sqs
# SQS_QUEUE=workflows
```

### Solution 2: Verify Queue Exists

```bash
# Check vapor.yml
cat vapor.yml | grep -A 5 "queue:"

# Should have:
# queue: workflows

# Verify queue was created
vapor queue:list
```

### Solution 3: Scale Queue Workers

```bash
# Scale up workers
vapor queue:scale production --workers=3

# Check worker status
vapor queue:monitor production
```

### Solution 4: Check Job Timeout

If jobs are timing out:

**Update `vapor.yml`:**

```yaml
environments:
  production:
    timeout: 120  # Increase from 60
    cli-memory: 512
    
    queues:
      - name: workflows
        workers: 3
        timeout: 300  # 5 minutes
```

Redeploy:

```bash
vapor deploy production
```

### Solution 5: Test Queue Manually

```bash
vapor tinker production

>>> use AlizHarb\ForgePulse\Models\Workflow;
>>> use AlizHarb\ForgePulse\Jobs\ExecuteWorkflowJob;

>>> $workflow = Workflow::first();
>>> $execution = $workflow->executions()->create([
...     'status' => 'pending',
...     'context' => ['test' => 'data']
... ]);

>>> ExecuteWorkflowJob::dispatch($execution);
# Check logs immediately
```

Then check logs:

```bash
vapor logs production --filter="ExecuteWorkflowJob" --follow
```

### Verification

```bash
# Monitor queue processing
vapor queue:monitor production
# Should show jobs being processed

# Check execution status
vapor tinker production

>>> use AlizHarb\ForgePulse\Models\WorkflowExecution;
>>> WorkflowExecution::latest()->first()->status;
# Should be 'completed' or 'failed', not stuck on 'pending'
```

---

## Template Export/Import Failures

### Symptom

- "S3 Access Denied" errors
- Template exports fail silently
- Templates not found when importing

### Diagnosis

```bash
# Check S3 configuration
vapor tinker production

>>> config('forgepulse.templates.disk');
# Should return 's3'

>>> config('filesystems.disks.s3.bucket');
# Should return your bucket name

# Test S3 access
>>> use Illuminate\Support\Facades\Storage;
>>> Storage::disk('s3')->put('test.txt', 'test');
>>> Storage::disk('s3')->exists('test.txt');
```

### Solution 1: Verify Environment Variables

```bash
vapor env:pull production

# Check these variables exist:
grep -E "(AWS_BUCKET|AWS_DEFAULT_REGION|FORGEPULSE_TEMPLATE_DISK)" .env.production

# Should have:
# FORGEPULSE_TEMPLATE_DISK=s3
# AWS_BUCKET=your-vapor-bucket
# AWS_DEFAULT_REGION=us-east-1
```

If missing:

```bash
# Add to .env.production
echo "FORGEPULSE_TEMPLATE_DISK=s3" >> .env.production

# Push back
vapor env:push production
```

### Solution 2: Create Storage Bucket

```bash
# If bucket doesn't exist
vapor storage forgepulse-storage --region=us-east-1

# Update vapor.yml
# environments:
#   production:
#     storage: forgepulse-storage

# Redeploy
vapor deploy production
```

### Solution 3: Check IAM Permissions

The Lambda execution role needs S3 permissions. Check in AWS Console:

**IAM Role Policy:**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::your-bucket-name/*",
        "arn:aws:s3:::your-bucket-name"
      ]
    }
  ]
}
```

### Solution 4: Test Template Operations

```bash
vapor tinker production

>>> use AlizHarb\ForgePulse\Models\Workflow;
>>> use AlizHarb\ForgePulse\Services\TemplateManager;

>>> $workflow = Workflow::first();
>>> $manager = app(TemplateManager::class);

>>> // Test export
>>> try {
...     $path = $manager->export($workflow);
...     echo "Exported to: $path\n";
... } catch (\Exception $e) {
...     echo "Error: " . $e->getMessage() . "\n";
... }

>>> // Test import
>>> try {
...     $imported = $manager->import($path, 'Test Import');
...     echo "Imported: " . $imported->name . "\n";
... } catch (\Exception $e) {
...     echo "Error: " . $e->getMessage() . "\n";
... }
```

### Verification

```bash
# List templates
vapor tinker production

>>> $manager = app(\AlizHarb\ForgePulse\Services\TemplateManager::class);
>>> $templates = $manager->listTemplates();
>>> print_r($templates);
```

---

## Database Connection Issues

### Symptom

- "SQLSTATE[HY000] [2002] Connection refused"
- "Too many connections"
- Queries timing out

### Diagnosis

```bash
# Check database status
vapor database:show forgepulse-db

# Test connection
vapor tinker production

>>> DB::connection()->getPdo();
>>> DB::table('workflows')->count();
```

### Solution 1: Verify Database Configuration

```bash
vapor env:pull production

# Check database credentials
grep -E "^DB_" .env.production

# Should have valid values from Vapor
```

### Solution 2: Check RDS Instance Status

```bash
# Via Vapor
vapor database:show forgepulse-db

# Via AWS CLI
aws rds describe-db-instances \
  --db-instance-identifier forgepulse-db \
  --query 'DBInstances[0].DBInstanceStatus'
```

If stopped:

```bash
# Start database
aws rds start-db-instance --db-instance-identifier forgepulse-db
```

### Solution 3: Increase Connection Pool

**`config/database.php`:**

```php
'mysql' => [
    'driver' => 'mysql',
    // ... other settings
    'options' => [
        PDO::ATTR_PERSISTENT => true,  // Reuse connections
    ],
    'sticky' => true,
],
```

### Solution 4: Use RDS Proxy

For better connection management:

```bash
# Create RDS Proxy via AWS Console
# Then update database endpoint

vapor env:pull production
# Update DB_HOST to proxy endpoint
vapor env:push production
```

### Solution 5: Scale Database

If "too many connections":

```bash
# Scale up RDS instance
# Via Vapor dashboard: Databases > forgepulse-db > Scale

# Or via AWS CLI
aws rds modify-db-instance \
  --db-instance-identifier forgepulse-db \
  --db-instance-class db.t3.medium \
  --apply-immediately
```

### Verification

```bash
vapor tinker production

>>> // Test multiple queries
>>> for ($i = 0; $i < 10; $i++) {
...     DB::table('workflows')->count();
...     echo "Query $i successful\n";
... }
```

---

## Memory Exhausted Errors

### Symptom

- "PHP Fatal error: Allowed memory size exhausted"
- Lambda errors: "Task timed out after 60.00 seconds"
- Workflows with many steps fail

### Diagnosis

```bash
# Check current memory allocation
cat vapor.yml | grep -A 5 "production:"

# Check CloudWatch metrics
vapor metrics production
```

### Solution 1: Increase Lambda Memory

**Update `vapor.yml`:**

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

### Solution 2: Optimize Workflow Steps

For workflows with 20+ steps, consider:

```php
// Break into smaller workflows
$workflow1 = Workflow::create(['name' => 'Part 1']);
$workflow2 = Workflow::create(['name' => 'Part 2']);

// Chain them
$workflow1->steps()->create([
    'type' => 'action',
    'configuration' => [
        'action_class' => TriggerNextWorkflow::class,
        'parameters' => ['workflow_id' => $workflow2->id]
    ]
]);
```

### Solution 3: Enable Chunking for Large Data

If processing large datasets in steps:

```php
// Instead of loading all at once
$users = User::all(); // Bad on Vapor

// Use chunking
User::chunk(100, function ($users) {
    // Process in batches
});
```

### Solution 4: Increase PHP Memory Limit

While Lambda memory is separate, you can increase PHP memory:

**Create `php.ini` in project root:**

```ini
memory_limit = 512M
```

**Update `vapor.yml`:**

```yaml
environments:
  production:
    runtime: php-8.3:al2
    php: /var/task/php.ini
```

### Verification

```bash
# Check memory usage
vapor metrics production

# Test large workflow
vapor tinker production

>>> $workflow = Workflow::with('steps')->first();
>>> memory_get_usage(true);
>>> $execution = $workflow->execute(['test' => 'data']);
>>> memory_get_usage(true);
```

---

## Timeout Issues

### Symptom

- "Task timed out after 60.00 seconds"
- Workflows fail mid-execution
- Long-running steps fail

### Diagnosis

```bash
# Check timeout settings
cat vapor.yml | grep timeout

# Check execution logs
vapor logs production --filter="timed out"
```

### Solution 1: Increase Lambda Timeout

**Update `vapor.yml`:**

```yaml
environments:
  production:
    timeout: 120  # Increase from 60 (max 900 seconds)
```

Redeploy:

```bash
vapor deploy production
```

### Solution 2: Increase Queue Job Timeout

**Update `vapor.yml`:**

```yaml
environments:
  production:
    queues:
      - name: workflows
        workers: 3
        timeout: 600  # 10 minutes
```

### Solution 3: Break Long Steps into Smaller Jobs

For steps that take > 5 minutes:

```php
// Instead of one long step
$workflow->steps()->create([
    'name' => 'Process All Data',
    'type' => 'action',
    // This might timeout...
]);

// Break into multiple steps
$workflow->steps()->create(['name' => 'Process Batch 1']);
$workflow->steps()->create(['name' => 'Process Batch 2']);
$workflow->steps()->create(['name' => 'Process Batch 3']);
```

### Solution 4: Use Async Processing

For very long operations, use delayed jobs:

```php
use AlizHarb\ForgePulse\Services\StepHandlers\ActionHandler;

class LongProcessHandler
{
    public function handle($step, $context)
    {
        // Dispatch separate job instead of blocking
        ProcessLongOperation::dispatch($context)->onQueue('long-running');
        
        return ['status' => 'dispatched'];
    }
}
```

### Solution 5: Monitor PCNTL Warnings

Remember: PCNTL timeouts don't work on Vapor. Update config:

```bash
vapor env:pull production
echo "FORGEPULSE_TIMEOUT=300" >> .env.production
vapor env:push production
```

The job-level timeout will handle it instead.

### Verification

```bash
# Test timeout
vapor tinker production

>>> use AlizHarb\ForgePulse\Models\Workflow;
>>> $workflow = Workflow::first();
>>> $start = microtime(true);
>>> $execution = $workflow->execute(['test' => 'data']);
>>> $duration = microtime(true) - $start;
>>> echo "Execution took: " . round($duration, 2) . " seconds\n";
```

---

## Session Problems

### Symptom

- Users logged out randomly
- Session data disappears
- "Session expired" errors

### Diagnosis

```bash
# Check session driver
vapor tinker production

>>> config('session.driver');
# Should NOT be 'file'
```

### Solution 1: Switch to Database Sessions

```bash
# Locally, create sessions table
php artisan session:table
php artisan migrate

# Update environment
vapor env:pull production
grep "SESSION_DRIVER" .env.production || echo "SESSION_DRIVER=database" >> .env.production
vapor env:push production

# Redeploy
vapor deploy production
```

### Solution 2: Use DynamoDB Sessions

**Update `config/session.php`:**

```php
'driver' => env('SESSION_DRIVER', 'dynamodb'),

'stores' => [
    'dynamodb' => [
        'driver' => 'dynamodb',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'table' => 'sessions',
    ],
],
```

Create DynamoDB table:

```bash
# Via Vapor
vapor cache sessions --table=sessions

# Update .env
vapor env:pull production
echo "SESSION_DRIVER=dynamodb" >> .env.production
vapor env:push production
```

### Verification

```bash
vapor tinker production

>>> session()->put('test', 'value');
>>> session()->save();
>>> session()->get('test');
# Should return 'value'

# Check database
>>> DB::table('sessions')->count();
# Should show session records
```

---

## Cache Not Working

### Symptom

- Cache values disappear
- Cache misses on every request
- Performance degradation

### Diagnosis

```bash
vapor tinker production

>>> config('cache.default');
>>> config('forgepulse.cache.store');

>>> use Illuminate\Support\Facades\Cache;
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
# Should return 'value'
```

### Solution 1: Switch to DynamoDB Cache

```bash
# Create cache table
vapor cache forgepulse-cache

# Update environment
vapor env:pull production
echo "CACHE_DRIVER=dynamodb" >> .env.production
echo "FORGEPULSE_CACHE_STORE=dynamodb" >> .env.production
vapor env:push production

# Redeploy
vapor deploy production
```

### Solution 2: Verify DynamoDB Table

```bash
# Check table exists
aws dynamodb describe-table --table-name cache

# Check table has items
aws dynamodb scan --table-name cache --max-items 5
```

### Verification

```bash
vapor tinker production

>>> use Illuminate\Support\Facades\Cache;
>>> Cache::put('test-' . time(), 'value', 60);
>>> Cache::remember('computed', 60, fn() => expensive_operation());
```

---

## Livewire Component Errors

### Symptom

- "Livewire component not found"
- Components don't update
- JavaScript errors in console

### Diagnosis

```bash
# Check Livewire is installed
vapor tinker production

>>> class_exists('Livewire\Livewire');
# Should return true

# Check components are registered
>>> app('livewire')->getComponentNames();
```

### Solution 1: Verify Component Registration

The package should auto-register components. Check:

```bash
vapor tinker production

>>> app('livewire')->getComponentNames();
# Should include 'forgepulse.workflow-builder', etc.
```

### Solution 2: Clear Livewire Cache

```bash
vapor tinker production

>>> \Livewire\Livewire::clearCache();
```

Or add to deploy commands:

**`vapor.yml`:**

```yaml
deploy:
  - 'php artisan migrate --force'
  - 'php artisan livewire:publish --assets'
```

### Solution 3: Verify Assets Are Loaded

Check browser console for:

```
GET /livewire/livewire.js 404 (Not Found)
```

If missing, ensure Livewire assets are published:

```bash
php artisan livewire:publish --assets
vapor deploy production
```

### Solution 4: Check JavaScript Errors

Open browser dev tools and check for:

- CSP (Content Security Policy) violations
- CORS errors
- Asset loading failures

May need to update CSP headers in `vapor.yml`:

```yaml
environments:
  production:
    headers:
      Content-Security-Policy: "default-src 'self' 'unsafe-inline' 'unsafe-eval' *.cloudfront.net"
```

### Verification

Visit workflow builder page and check:

1. No JavaScript errors in console
2. Livewire components load
3. Interactions work (drag-drop, buttons)

---

## Permission Denied Errors

### Symptom

- "Access denied" when creating workflows
- Policy errors in logs
- 403 Forbidden responses

### Diagnosis

```bash
vapor tinker production

>>> config('forgepulse.permissions.enabled');
# Check if RBAC is enabled

>>> auth()->user()->role;
# Check current user role
```

### Solution 1: Disable Permissions for Testing

```bash
vapor env:pull production
echo "FORGEPULSE_RBAC_ENABLED=false" >> .env.production
vapor env:push production
```

### Solution 2: Configure Roles Properly

**Update `config/forgepulse.php`:**

```php
'permissions' => [
    'enabled' => true,
    'can_create' => ['admin', 'workflow-manager', 'user'],  // Add your roles
    'can_execute' => ['admin', 'workflow-manager', 'user'],
],
```

### Solution 3: Check User Roles

```bash
vapor tinker production

>>> $user = auth()->user();
>>> $user->roles()->pluck('name');
# Should include roles from config
```

### Solution 4: Bypass Policy for Specific Users

**`app/Providers/AuthServiceProvider.php`:**

```php
use AlizHarb\ForgePulse\Models\Workflow;

public function boot()
{
    Gate::before(function ($user, $ability) {
        if ($user->isAdmin()) {
            return true;  // Admins can do everything
        }
    });
}
```

### Verification

```bash
vapor tinker production

>>> use AlizHarb\ForgePulse\Models\Workflow;
>>> auth()->loginUsingId(1);
>>> auth()->user()->can('create', Workflow::class);
# Should return true
```

---

## General Debugging Tips

### Enable Verbose Logging

```bash
vapor env:pull production
echo "APP_DEBUG=true" >> .env.production
echo "LOG_LEVEL=debug" >> .env.production
echo "FORGEPULSE_LOGGING_ENABLED=true" >> .env.production
vapor env:push production
```

### Monitor in Real-Time

```bash
# Follow logs
vapor logs production --follow

# Filter by keyword
vapor logs production --filter="forgepulse" --follow
vapor logs production --filter="ERROR" --follow

# Check metrics
vapor metrics production
```

### Test Individual Components

```bash
vapor tinker production

# Test database
>>> DB::connection()->getPdo();

# Test cache
>>> Cache::put('test', 'value');

# Test storage
>>> Storage::disk('s3')->put('test.txt', 'test');

# Test queue
>>> Queue::push(fn() => info('Queue test'));

# Test workflow
>>> $workflow = Workflow::first();
>>> $workflow->execute(['test' => 'data']);
```

### Check CloudWatch Logs

For detailed Lambda logs:

```bash
# Via AWS Console
# CloudWatch > Log Groups > /aws/lambda/vapor-[environment]-[function]

# Via AWS CLI
aws logs tail /aws/lambda/vapor-production-function --follow
```

---

## Getting Help

If you're still stuck:

1. **Check Vapor Documentation:** https://docs.vapor.build
2. **Check ForgePulse Issues:** https://github.com/alizharb/forgepulse/issues
3. **Check Laravel Documentation:** https://laravel.com/docs
4. **Vapor Support:** support@vapor.build
5. **Create Issue:** Include logs, configuration, and steps to reproduce

### Information to Include When Reporting Issues

```bash
# Collect diagnostic info
vapor env:pull production

echo "=== Vapor Configuration ==="
cat vapor.yml

echo "=== Environment ==="
cat .env.production | grep -v PASSWORD | grep -v SECRET

echo "=== Recent Logs ==="
vapor logs production --lines=50

echo "=== Queue Status ==="
vapor queue:monitor production

echo "=== Metrics ==="
vapor metrics production
```

---

**Last Updated:** December 2025
