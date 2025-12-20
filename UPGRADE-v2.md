# Upgrade Guide: v1.x to v2.0

## Overview

ForgePulse v2.0 introduces a major architectural improvement: **step-per-job execution**. This change provides:

- ✅ **Fault tolerance** - Resume workflows from failed steps
- ✅ **Proper delays** - No more blocked queue workers
- ✅ **Vapor compatibility** - Works perfectly with AWS Lambda
- ✅ **Better scalability** - Steps run independently
- ✅ **Service integration** - Call existing services and jobs directly

## Breaking Changes

### 1. Execution Model Change

**v1.x (Old):**
```php
// Entire workflow ran in one job
ExecuteWorkflowJob dispatched → all steps executed sequentially
```

**v2.0 (New):**
```php
// Each step runs as its own job
ExecuteStepJob dispatched → step executes → next step(s) dispatched
```

### 2. WorkflowEngine API Changes

**Removed Methods:**
- `WorkflowEngine::execute(WorkflowExecution $execution)` - No longer synchronous

**New Methods:**
- `WorkflowEngine::start(WorkflowExecution $execution)` - Start workflow
- `WorkflowEngine::executeStep(WorkflowExecution $execution, WorkflowStep $step)` - Execute single step
- `WorkflowEngine::advance(WorkflowExecution $execution)` - Determine and dispatch next steps

### 3. Delay Handler Behavior

**v1.x:**
```php
// Blocked queue worker
sleep($seconds);
```

**v2.0:**
```php
// Schedules next step with delay
ExecuteStepJob::dispatch($execution, $step)->delay($seconds);
```

### 4. Configuration Changes

**Updated keys in `config/forgepulse.php`:**

```php
'execution' => [
    // NEW: Per-step timeout
    'step_timeout' => 300,
    
    // DEPRECATED: Workflow-level timeout (kept for compatibility)
    'timeout' => 300,
    
    // RENAMED: queue_name → queue
    'queue' => 'workflows',
],
```

## Migration Steps

### Step 1: Update Composer

```bash
composer update alizharb/forgepulse
```

### Step 2: Run Migration

```bash
php artisan migrate
```

This adds:
- `current_step_id` - Tracks currently executing step
- `completed_step_ids` - Array of completed steps for resume capability

### Step 3: Update Configuration

Publish the new config (optional):

```bash
php artisan vendor:publish --tag=forgepulse-config --force
```

Or manually update:

```php
// config/forgepulse.php
'execution' => [
    'step_timeout' => env('FORGEPULSE_STEP_TIMEOUT', 300),
    'queue' => env('FORGEPULSE_QUEUE_NAME', 'workflows'),
    // ...
],
```

### Step 4: Update Custom Code

**If you call WorkflowEngine directly:**

```php
// OLD - v1.x
$engine->execute($execution);

// NEW - v2.0
$engine->start($execution);
```

**If you use Workflow::execute():**

No changes needed! The method automatically uses the new architecture.

### Step 5: Restart Queue Workers

```bash
php artisan queue:restart
```

## New Features

### 1. Enhanced Action Handler

The ActionHandler now supports multiple Laravel conventions:

```php
// Service with custom method
[
    'type' => 'action',
    'configuration' => [
        'class' => 'App\Services\OrderService',
        'method' => 'processOrder',  // Specify method
        'parameters' => ['order_id' => '{{order_id}}']
    ]
]

// Invokable class
[
    'type' => 'action',
    'configuration' => [
        'class' => 'App\Actions\ProcessPayment',
        // Auto-detects __invoke()
        'parameters' => [...]
    ]
]

// Job handle() method (sync)
[
    'type' => 'action',
    'configuration' => [
        'class' => 'App\Jobs\GenerateReport',
        'mode' => 'sync',  // Calls handle() directly
        'parameters' => [...]
    ]
]

// Dispatch job (async)
[
    'type' => 'action',
    'configuration' => [
        'class' => 'App\Jobs\SendEmail',
        'mode' => 'dispatch',  // Fire and forget
        'queue' => 'emails',
        'delay' => 60,
        'parameters' => [...]
    ]
]
```

**Method Detection Order:**
1. Explicitly specified `method`
2. `__invoke()` (invokable)
3. `handle()` (job-like, Spatie Actions)
4. `execute()` (backward compatibility)
5. `asAction()` (Spatie Laravel Actions)

### 2. Resume Failed Executions

```php
$execution = WorkflowExecution::find($id);

// Resume from last completed step
$execution->resumeExecution();
```

### 3. Better Delay Handling

```php
// Delays no longer block workers
$workflow->steps()->create([
    'type' => 'delay',
    'configuration' => ['seconds' => 3600],  // 1 hour delay
]);

// Next steps will be scheduled 1 hour later
// Queue worker is free immediately
```

## Testing Your Upgrade

### 1. Test Simple Workflow

```php
use Illuminate\Support\Facades\Queue;

Queue::fake();

$workflow = Workflow::find(1);
$execution = $workflow->execute(['test' => true]);

// Verify first step job was dispatched
Queue::assertPushed(ExecuteStepJob::class);
```

### 2. Test Resume Capability

```php
$execution = WorkflowExecution::create([
    'workflow_id' => $workflow->id,
    'status' => 'failed',
    'current_step_id' => $step->id,
    'error_message' => 'Temporary failure',
]);

$execution->resumeExecution();

expect($execution->fresh()->status->value)->toBe('running');
```

### 3. Test Service Integration

```php
// Create a test service
class TestService {
    public function process(array $parameters, array $context): array {
        return ['result' => 'success'];
    }
}

// Use in workflow
$workflow->steps()->create([
    'type' => 'action',
    'configuration' => [
        'class' => TestService::class,
        'method' => 'process',
        'parameters' => ['test' => true],
    ],
]);
```

## Vapor Considerations

### Lambda Timeout Configuration

Set appropriate timeouts for your Lambda functions:

```php
// config/forgepulse.php
'execution' => [
    // Keep under Lambda timeout (max 15 minutes)
    'step_timeout' => 300,  // 5 minutes is safe
],
```

### Queue Configuration

Ensure your queue is configured for SQS:

```env
QUEUE_CONNECTION=sqs
FORGEPULSE_QUEUE_NAME=workflows
```

### Long-Running Steps

For steps that might run long:

```php
$workflow->steps()->create([
    'type' => 'action',
    'timeout' => 600,  // 10 minutes
    'configuration' => [...],
]);
```

## Rollback

If you need to rollback to v1.x:

```bash
# 1. Rollback code
composer require alizharb/forgepulse:^1.0

# 2. Rollback migration
php artisan migrate:rollback

# 3. Restart workers
php artisan queue:restart
```

**Note:** Executions in progress may be lost during rollback.

## Support

- **Documentation:** [Full docs](./docs/README.md)
- **Issues:** [GitHub Issues](https://github.com/alizharb/forgepulse/issues)
- **Discussions:** [GitHub Discussions](https://github.com/alizharb/forgepulse/discussions)

## What's Next?

v2.1 will introduce:
- ✨ Parallel execution groups
- ✨ Step-level rate limiting
- ✨ Advanced error handling strategies
- ✨ Workflow analytics dashboard
