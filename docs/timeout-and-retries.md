# Configurable Timeouts and Retries

## Overview

ForgePulse v2.0+ allows you to configure timeouts and retry attempts at **three levels** with intelligent fallback priority:

```
Step Level → Workflow Level → Global Config
(Most specific)              (Least specific)
```

This gives you maximum flexibility: set sensible defaults globally, override per workflow, and fine-tune critical steps.

---

## Configuration Levels

### Level 1: Global Config (Defaults)

Set in `config/forgepulse.php`:

```php
'execution' => [
    'step_timeout' => 300,      // 5 minutes default
    'max_retries' => 3,         // 3 retry attempts default
],
```

**Use for**: Organization-wide defaults

### Level 2: Workflow Level

Set when creating or updating workflows:

```php
$workflow = Workflow::create([
    'name' => 'Payment Processing',
    'timeout' => 180,          // 3 minutes for all steps
    'max_retries' => 5,        // 5 retries for all steps
    'status' => 'active',
]);
```

**Use for**: Workflow-specific requirements (e.g., high-priority workflows need more retries)

### Level 3: Step Level (Most Specific)

Set per individual step:

```php
$workflow->steps()->create([
    'name' => 'Process Credit Card',
    'type' => 'action',
    'timeout' => 60,           // 1 minute just for this step
    'max_retries' => 8,        // 8 retries just for this step
    'configuration' => [...],
]);
```

**Use for**: Critical steps that need special handling

---

## Priority Hierarchy Examples

### Example 1: All Defaults

```php
// Global config: timeout=300, max_retries=3
// Workflow: (not set)
// Step: (not set)

Result: timeout=300, max_retries=3
```

### Example 2: Workflow Override

```php
// Global config: timeout=300, max_retries=3
// Workflow: timeout=600, max_retries=5
// Step: (not set)

Result: timeout=600, max_retries=5
```

### Example 3: Step Override

```php
// Global config: timeout=300, max_retries=3
// Workflow: timeout=600, max_retries=5
// Step: timeout=120, max_retries=10

Result: timeout=120, max_retries=10  ← Most specific wins
```

### Example 4: Partial Override

```php
// Global config: timeout=300, max_retries=3
// Workflow: (not set)
// Step: timeout=120  (only timeout set)

Result: timeout=120, max_retries=3  ← Mix of step and global
```

---

## API Examples

### Create Workflow with Custom Settings

```http
POST /api/forgepulse/workflows
Content-Type: application/json

{
  "name": "Critical Payment Processing",
  "status": "active",
  "timeout": 180,
  "max_retries": 5,
  "steps": [
    {
      "name": "Validate Payment Method",
      "type": "action",
      "position": 1,
      "timeout": 30,
      "max_retries": 2,
      "configuration": {
        "class": "App\\Services\\PaymentService",
        "method": "validate"
      }
    },
    {
      "name": "Process Payment",
      "type": "action",
      "position": 2,
      "timeout": 120,
      "max_retries": 8,
      "configuration": {
        "class": "App\\Services\\PaymentService",
        "method": "process"
      }
    }
  ]
}
```

### Update Workflow Timeout

```http
PUT /api/forgepulse/workflows/123
Content-Type: application/json

{
  "timeout": 600,
  "max_retries": 10
}
```

---

## Eloquent Examples

### Create Workflow with Defaults

```php
use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Enums\StepType;

$workflow = Workflow::create([
    'name' => 'Standard Order Processing',
    'status' => 'active',
    'timeout' => 300,          // 5 minutes for all steps
    'max_retries' => 5,        // 5 retries for all steps
]);
```

### Create Steps with Different Settings

```php
// Fast validation step (30 seconds, 2 retries)
$validateStep = $workflow->steps()->create([
    'name' => 'Validate Order',
    'type' => StepType::ACTION,
    'position' => 1,
    'timeout' => 30,
    'max_retries' => 2,
    'configuration' => [
        'class' => 'App\Services\OrderService',
        'method' => 'validate',
    ],
]);

// Critical payment step (2 minutes, 10 retries)
$paymentStep = $workflow->steps()->create([
    'name' => 'Process Payment',
    'type' => StepType::ACTION,
    'position' => 2,
    'timeout' => 120,
    'max_retries' => 10,
    'configuration' => [
        'class' => 'App\Services\PaymentService',
        'method' => 'processPayment',
    ],
]);

// Standard notification (uses workflow defaults)
$notifyStep = $workflow->steps()->create([
    'name' => 'Send Confirmation',
    'type' => StepType::NOTIFICATION,
    'position' => 3,
    // No timeout or max_retries - uses workflow defaults (300s, 5 retries)
    'configuration' => [
        'notification_class' => 'App\Notifications\OrderConfirmed',
    ],
]);
```

---

## Use Case Patterns

### Pattern 1: High-Priority Workflow

For critical workflows that need more attempts:

```php
$workflow = Workflow::create([
    'name' => 'VIP Customer Workflow',
    'timeout' => 600,          // 10 minutes
    'max_retries' => 10,       // More retries
    'status' => 'active',
]);
```

### Pattern 2: Fast-Fail Workflow

For workflows that should fail quickly:

```php
$workflow = Workflow::create([
    'name' => 'Health Check',
    'timeout' => 30,           // 30 seconds
    'max_retries' => 1,        // Fail fast
    'status' => 'active',
]);
```

### Pattern 3: Mixed Criticality

Different timeouts per step criticality:

```php
$workflow = Workflow::create([
    'name' => 'E-commerce Order',
    'timeout' => 300,          // Default 5 minutes
    'max_retries' => 5,
]);

// Fast pre-checks (30 seconds, 2 retries)
$workflow->steps()->create([
    'name' => 'Validate Stock',
    'timeout' => 30,
    'max_retries' => 2,
    ...
]);

// Critical payment (10 minutes, 15 retries)
$workflow->steps()->create([
    'name' => 'Process Payment',
    'timeout' => 600,
    'max_retries' => 15,
    ...
]);

// Standard notification (uses workflow defaults)
$workflow->steps()->create([
    'name' => 'Send Email',
    // Inherits: timeout=300, max_retries=5
    ...
]);
```

---

## Vapor/Lambda Considerations

### Stay Under Lambda Limits

```php
// BAD - Lambda max is 900 seconds (15 minutes)
'timeout' => 1800,  // ❌ Way too long for Lambda

// GOOD - Keep steps under 10 minutes
'timeout' => 300,   // ✅ 5 minutes (safe)
'timeout' => 600,   // ✅ 10 minutes (max allowed)
```

### Workflow-Level for Lambda

```php
$workflow = Workflow::create([
    'name' => 'Vapor-Optimized Workflow',
    'timeout' => 300,          // Max 5 minutes per step
    'max_retries' => 3,
]);
```

### Critical Steps on Vapor

```php
// For steps that occasionally timeout
$workflow->steps()->create([
    'name' => 'External API Call',
    'timeout' => 120,          // 2 minutes
    'max_retries' => 5,        // More retries for flaky APIs
    'configuration' => [
        'class' => 'App\Services\ExternalApiService',
        'method' => 'fetchData',
    ],
]);
```

---

## Monitoring and Debugging

### Check Effective Settings

The `ExecuteStepJob` logs the effective timeout and retries:

```php
// In Horizon, you'll see:
Job: ExecuteStepJob
Timeout: 120 seconds
Tries: 5 attempts
Tags: workflow:123, execution:456, step:789
```

### API Response Includes Settings

```json
GET /api/forgepulse/workflows/123

{
  "id": 123,
  "name": "Payment Processing",
  "timeout": 180,
  "max_retries": 5,
  "steps": [
    {
      "id": 1,
      "name": "Process Payment",
      "timeout": 120,
      "max_retries": 10,
      ...
    }
  ]
}
```

---

## Best Practices

### 1. Start with Sensible Defaults

```php
// config/forgepulse.php
'execution' => [
    'step_timeout' => 300,      // 5 minutes
    'max_retries' => 3,         // 3 attempts
],
```

### 2. Override at Workflow Level for Domains

```php
// High-value workflows
$paymentWorkflow->update(['max_retries' => 10]);

// Background jobs
$reportWorkflow->update(['timeout' => 1800]);
```

### 3. Override at Step Level for Exceptions

```php
// Only for truly critical steps
$criticalStep->update([
    'timeout' => 600,
    'max_retries' => 15,
]);
```

### 4. Monitor and Adjust

```php
// Check failure rates
$failedSteps = WorkflowExecutionLog::where('status', 'failed')
    ->with('step')
    ->get()
    ->groupBy('step.name');

// Increase retries for frequently failing steps
```

---

## Validation Rules

**Timeout:**
- Minimum: 1 second
- Maximum: 600 seconds (10 minutes)
- Must be integer

**Max Retries:**
- Minimum: 0 (no retries)
- Maximum: 10
- Must be integer

---

## Migration

When upgrading to v2.0+:

1. **Run migrations:**
   ```bash
   php artisan migrate
   ```

2. **Existing workflows:**
   - Continue using global config defaults
   - No changes required

3. **New workflows:**
   - Can optionally set `timeout` and `max_retries`
   - Falls back to global config if not set

---

## Summary

**Configuration Priority:**
```
Step → Workflow → Config
```

**Flexibility:**
- Set defaults globally
- Override per workflow
- Fine-tune critical steps

**Vapor-Safe:**
- Keep timeouts under 15 minutes
- Recommended: 5 minutes or less

**Best Practice:**
- Use global defaults
- Override exceptions only
- Monitor and adjust based on data

This three-level system gives you complete control while maintaining simplicity for common cases.
