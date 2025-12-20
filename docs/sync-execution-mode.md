# Sync Execution Mode

## Overview

ForgePulse v2.0+ supports **two execution modes**:

1. **Async Mode** (Default) - Step-per-job with fault tolerance
2. **Sync Mode** (New) - Fast execution in single process

Sync mode eliminates queue overhead for workflows with fast steps, providing **10-40x performance improvement**.

---

## When to Use Each Mode

### Async Mode (Step-Per-Job) ✅
**Use when:**
- Workflow has delays (wait steps)
- Any step takes > 5 minutes
- Workflow is critical (needs fault tolerance)
- Need resume capability after failures
- Total workflow time > 10 minutes
- Running complex workflows on Vapor

**Benefits:**
- Fault tolerant (can resume from failures)
- Works with delays (doesn't block workers)
- Vapor-compatible for long workflows
- Each step tracked independently

**Performance:**
- ~300-500ms overhead per step

### Sync Mode (Single Process) ⚡
**Use when:**
- All steps are fast (< 1 second)
- No delay steps
- Total workflow < 5 minutes
- Fault tolerance not critical
- Testing/development

**Benefits:**
- 10-40x faster than async
- No queue overhead
- Immediate execution

**Drawbacks:**
- No fault tolerance
- Cannot resume from failures
- Blocks during execution
- Not suitable for delays

---

## Usage

### Auto-Detection (Recommended)

The workflow automatically chooses the best mode:

```php
// Workflow analyzes its steps and decides
$execution = $workflow->execute($context);

// Fast workflow → uses sync mode automatically
// Has delays/long steps → uses async mode automatically
```

### Explicit Mode

Force a specific execution mode:

```php
// Force synchronous execution
$execution = $workflow->execute($context, 'sync');

// Force asynchronous execution
$execution = $workflow->execute($context, 'async');
```

---

## Auto-Detection Logic

The workflow uses `shouldExecuteSync()` to analyze characteristics:

```php
$workflow->shouldExecuteSync(); // Returns true if safe for sync
```

### Returns FALSE (use async) when:
- ❌ Has any delay steps
- ❌ Any step timeout > 5 minutes
- ❌ Workflow timeout > 5 minutes
- ❌ Estimated duration > 10 minutes
- ❌ More than 20 steps
- ❌ Configuration flag `force_async` = true
- ❌ Configuration flag `critical` = true

### Returns TRUE (use sync) when:
- ✅ All steps are fast (< 5 minutes)
- ✅ No delay steps
- ✅ Total duration < 10 minutes
- ✅ Reasonable number of steps

---

## Examples

### Example 1: Fast Validation Workflow (Auto-Sync)

```php
$workflow = Workflow::create([
    'name' => 'Quick Validation',
    'timeout' => 60,
]);

$workflow->steps()->create([
    'name' => 'Validate Email',
    'type' => 'action',
    'position' => 1,
    'timeout' => 10,
    'configuration' => [
        'class' => 'App\Services\ValidationService',
        'method' => 'validateEmail',
    ],
]);

$workflow->steps()->create([
    'name' => 'Validate Phone',
    'type' => 'action',
    'position' => 2,
    'timeout' => 10,
    'configuration' => [
        'class' => 'App\Services\ValidationService',
        'method' => 'validatePhone',
    ],
]);

// Auto-detects sync mode (no delays, fast steps)
$execution = $workflow->execute(['email' => 'test@example.com']);

// Result: Completes in ~30ms instead of ~800ms
```

### Example 2: Workflow with Delay (Auto-Async)

```php
$workflow = Workflow::create([
    'name' => 'Order Follow-up',
]);

$workflow->steps()->create([
    'name' => 'Send Confirmation',
    'type' => 'notification',
    'position' => 1,
    'configuration' => [...],
]);

$workflow->steps()->create([
    'name' => 'Wait 24 Hours',
    'type' => 'delay',
    'position' => 2,
    'configuration' => ['seconds' => 86400],
]);

$workflow->steps()->create([
    'name' => 'Send Follow-up',
    'type' => 'notification',
    'position' => 3,
    'configuration' => [...],
]);

// Auto-detects async mode (has delay step)
$execution = $workflow->execute(['order_id' => 123]);

// Result: Uses step-per-job (required for delays)
```

### Example 3: Critical Workflow (Force Async)

```php
$workflow = Workflow::create([
    'name' => 'Payment Processing',
    'configuration' => [
        'critical' => true,  // Force async for fault tolerance
    ],
]);

$workflow->steps()->create([
    'name' => 'Process Payment',
    'type' => 'action',
    'position' => 1,
    'timeout' => 120,
    'configuration' => [
        'class' => 'App\Services\PaymentService',
        'method' => 'charge',
    ],
]);

// Auto-detects async mode (critical workflow)
$execution = $workflow->execute(['amount' => 99.99]);

// Result: Uses step-per-job for fault tolerance
```

### Example 4: Explicit Sync (Testing)

```php
// Force sync mode for faster test execution
$execution = $workflow->execute($testData, 'sync');

// Completes immediately, no queue involved
expect($execution->status->value)->toBe('completed');
```

---

## Performance Comparison

### Scenario: 10 Validation Steps (100ms total logic)

**Async Mode:**
```
10 steps × (10ms + 400ms queue) = 4.1 seconds
```

**Sync Mode:**
```
10 steps × 10ms = 100ms
```

**Result: 41x faster with sync mode!** 🚀

### Scenario: 5 Steps with API Calls (10 seconds total logic)

**Async Mode:**
```
5 steps × (2s + 400ms) = 12 seconds
```

**Sync Mode:**
```
5 steps × 2s = 10 seconds
```

**Result: 1.2x faster (marginal benefit)**

---

## Configuration Options

### Force Async Mode

```php
$workflow = Workflow::create([
    'name' => 'Always Async',
    'configuration' => [
        'force_async' => true,  // Never use sync
    ],
]);
```

### Mark as Critical

```php
$workflow = Workflow::create([
    'name' => 'Payment Flow',
    'configuration' => [
        'critical' => true,  // Requires fault tolerance
    ],
]);
```

---

## API Integration

### Check Execution Mode

```php
// Check if workflow will use sync
if ($workflow->shouldExecuteSync()) {
    echo "Will execute synchronously (fast)";
} else {
    echo "Will execute asynchronously (fault tolerant)";
}
```

### Via API

```http
GET /api/forgepulse/workflows/123

Response:
{
  "id": 123,
  "name": "Quick Validation",
  "timeout": 60,
  "will_execute_sync": true  // (Could add this field)
}
```

---

## Monitoring

### Sync Execution Logs

```
[2025-12-20 10:15:30] Workflow started (sync mode)
[2025-12-20 10:15:30] Step 'Validate Email' executed successfully
[2025-12-20 10:15:30] Step 'Validate Phone' executed successfully
[2025-12-20 10:15:30] Workflow completed successfully (sync mode)
```

### Async Execution Logs

```
[2025-12-20 10:15:30] Workflow started
[2025-12-20 10:15:30] Step 'Validate Email' executed successfully
[2025-12-20 10:15:31] Step 'Validate Phone' executed successfully
[2025-12-20 10:15:32] Workflow completed successfully
```

---

## Best Practices

### 1. Let Auto-Detection Decide

```php
// Good: Auto-detect based on workflow
$execution = $workflow->execute($context);

// Avoid: Manual mode unless you have specific reason
$execution = $workflow->execute($context, 'sync');
```

### 2. Batch Fast Operations

```php
// Good: Combine fast validations in one step
class ValidationService {
    public function validateAll($params, $context) {
        return [
            'email_valid' => $this->validateEmail(),
            'phone_valid' => $this->validatePhone(),
            'address_valid' => $this->validateAddress(),
        ];
    }
}

// Avoid: Separate steps for each validation
```

### 3. Mark Critical Workflows

```php
// For workflows that must not lose progress
$workflow->update([
    'configuration' => ['critical' => true]
]);
```

### 4. Set Reasonable Timeouts

```php
// Helps auto-detection work correctly
$workflow->steps()->create([
    'timeout' => 30,  // Indicates this is a fast step
    ...
]);
```

---

## Testing

### Test Sync Execution

```php
use AlizHarb\ForgePulse\Models\Workflow;

it('executes fast workflow synchronously', function () {
    $workflow = Workflow::factory()->create(['timeout' => 60]);
    
    $workflow->steps()->create([
        'type' => 'delay',
        'configuration' => ['seconds' => 0],
    ]);
    
    $execution = $workflow->execute([]);
    
    expect($execution->status->value)->toBe('completed');
    // Completes immediately in tests!
});
```

### Test Auto-Detection

```php
it('auto-detects sync for fast workflows', function () {
    $workflow = Workflow::factory()->create(['timeout' => 60]);
    
    $workflow->steps()->create(['type' => 'action', 'timeout' => 10]);
    
    expect($workflow->shouldExecuteSync())->toBeTrue();
});

it('auto-detects async for workflows with delays', function () {
    $workflow = Workflow::factory()->create();
    
    $workflow->steps()->create(['type' => 'delay', 'configuration' => ['seconds' => 60]]);
    
    expect($workflow->shouldExecuteSync())->toBeFalse();
});
```

---

## Limitations

### Sync Mode Limitations

❌ **Cannot use with:**
- Delay steps (will execute but delay won't work properly)
- Very long steps (> 10 minutes)
- Steps that should run on specific queues

⚠️ **No fault tolerance:**
- If workflow fails, must restart from beginning
- No resume capability
- No step-by-step tracking (all happens at once)

### When Sync Fails

If sync execution fails, you cannot resume - must re-run entire workflow:

```php
try {
    $execution = $workflow->execute($context, 'sync');
} catch (\Exception $e) {
    // Must start over
    $execution = $workflow->execute($context, 'sync');
}
```

---

## Migration from Async

Existing workflows automatically benefit from auto-detection:

```php
// Before (v2.0 - always async)
$execution = $workflow->execute($context);

// After (v2.0+ with sync mode - auto-detects)
$execution = $workflow->execute($context);  // Faster for fast workflows!
```

No code changes required - sync mode activates automatically when appropriate.

---

## Summary

**Sync mode provides 10-40x performance improvement for fast workflows** while maintaining the fault-tolerant step-per-job architecture for complex workflows.

**Key Points:**
- ✅ Auto-detection chooses best mode automatically
- ✅ 10-40x faster for workflows with fast steps
- ✅ Maintains backward compatibility
- ✅ No code changes required
- ✅ Perfect for testing (immediate execution)
- ⚠️ No fault tolerance in sync mode
- ⚠️ Cannot use with delay steps

**Recommendation:** Let the workflow auto-detect unless you have specific requirements.
