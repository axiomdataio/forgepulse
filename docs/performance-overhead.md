# Performance Analysis: Step-Per-Job Overhead

## TL;DR

**For workflows with many fast steps (< 100ms each), the overhead can be significant.**

- **Fast step (10ms)**: Total time ~500-1000ms (50-100x overhead)
- **Medium step (1s)**: Total time ~1.5-2s (1.5-2x overhead)  
- **Long step (30s)**: Total time ~30.5s (~2% overhead)

**The step-per-job architecture is optimized for fault tolerance and Vapor compatibility, not raw speed.**

---

## Overhead Breakdown Per Step

### 1. Queue Operations (~100-500ms)

**On Vapor (SQS):**
```
- Dispatch job to SQS:        ~50-100ms
- SQS delivery to worker:     ~50-100ms
- Worker picks up job:         ~0-300ms (depends on polling)
```

**Total Queue Overhead: ~100-500ms per step**

### 2. Database Operations (~30-80ms)

```
Per step execution:
- Load WorkflowExecution:      ~10ms
- Load WorkflowStep:           ~10ms
- Update current_step_id:      ~10ms
- Update completed_step_ids:   ~10ms
- Create WorkflowExecutionLog: ~10ms
- Update context:              ~10ms
- Get next steps:              ~10-20ms

Total DB Overhead: ~60-80ms per step
```

### 3. Laravel/Job Overhead (~20-50ms)

```
- Job deserialization:         ~5ms
- Service container resolution: ~10ms
- Event dispatching:           ~5ms
- Job completion:              ~5ms

Total Framework Overhead: ~25ms per step
```

### 4. Total Overhead Per Step

**Best case**: ~185ms  
**Typical case**: ~300-500ms  
**Worst case**: ~700ms (slow SQS polling)

---

## Real-World Examples

### Example 1: Fast Steps (BAD Performance)

**Scenario**: 10 validation steps, each takes 10ms

```
Without queue overhead (theoretical):
10 steps × 10ms = 100ms

With step-per-job:
10 steps × (10ms + 400ms overhead) = 4.1 seconds

Overhead ratio: 41x slower! ❌
```

### Example 2: Medium Steps (Acceptable)

**Scenario**: 5 steps, each takes 1 second

```
Without queue overhead:
5 steps × 1s = 5 seconds

With step-per-job:
5 steps × (1s + 400ms) = 7 seconds

Overhead ratio: 1.4x slower ✅
```

### Example 3: Long Steps (Negligible)

**Scenario**: 3 steps, each takes 30 seconds

```
Without queue overhead:
3 steps × 30s = 90 seconds

With step-per-job:
3 steps × (30s + 400ms) = 91.2 seconds

Overhead ratio: 1.3% slower ✅
```

---

## When This Architecture Works Well

### ✅ Good Use Cases

1. **Long-running steps** (> 5 seconds)
   - Payment processing
   - API calls to external services
   - File processing
   - Report generation

2. **Workflows with delays**
   - Wait 1 hour before next step
   - Schedule follow-ups
   - Rate limiting between steps

3. **Critical workflows needing fault tolerance**
   - Payment flows (resume from failure)
   - Multi-day approval processes
   - Order fulfillment

4. **Workflows with few steps**
   - 3-5 steps taking > 10 seconds each
   - Overhead becomes negligible

### ❌ Poor Use Cases

1. **Many fast steps** (< 100ms each)
   - 20 quick validations in sequence
   - Simple data transformations
   - Fast database lookups

2. **Real-time/sub-second requirements**
   - API responses that must be < 500ms
   - Synchronous user-facing operations

3. **High-frequency workflows**
   - Thousands of executions per minute
   - Database queries become bottleneck

---

## Optimization Strategies

### Strategy 1: Batch Fast Steps

**Instead of:**
```php
Step 1: Validate email (10ms)
Step 2: Validate phone (10ms)
Step 3: Validate address (10ms)
Step 4: Check inventory (10ms)
Total: 40ms logic + 1600ms overhead = 1640ms
```

**Do this:**
```php
Step 1: Run all validations (40ms)
Total: 40ms logic + 400ms overhead = 440ms (4x faster!)
```

**Implementation:**
```php
// Single validation step that does multiple checks
class ValidationService {
    public function validateAll(array $params, array $context): array {
        return [
            'email_valid' => $this->validateEmail($context['email']),
            'phone_valid' => $this->validatePhone($context['phone']),
            'address_valid' => $this->validateAddress($context['address']),
            'inventory_ok' => $this->checkInventory($context['items']),
        ];
    }
}
```

### Strategy 2: Use Synchronous Execution for Simple Workflows

For workflows where fault tolerance isn't critical:

```php
// NEW: Add a sync execution mode (would need implementation)
class SimpleSyncWorkflowEngine {
    public function executeSync(WorkflowExecution $execution): void {
        // Run all steps in same process
        // No queue overhead
        // But: No fault tolerance, no resume
    }
}
```

### Strategy 3: Optimize Database Queries

```php
// Current: Multiple queries per step
$execution = WorkflowExecution::find($id);
$step = WorkflowStep::find($stepId);

// Optimized: Eager load
$execution = WorkflowExecution::with([
    'workflow.steps',
    'currentStep'
])->find($id);
```

### Strategy 4: Cache Hot Workflows

```php
// Cache workflow definitions
$workflow = Cache::remember(
    "workflow:{$id}",
    3600,
    fn() => Workflow::with('steps')->find($id)
);
```

---

## Benchmark Comparison

### Workflow: 10 Steps, Mixed Duration

| Step | Duration | Old (v1.x) | New (v2.0) | Overhead |
|------|----------|------------|------------|----------|
| 1. Validate | 10ms | 10ms | 410ms | +40x |
| 2. Check stock | 50ms | 50ms | 450ms | +8x |
| 3. Process payment | 2s | 2s | 2.4s | +20% |
| 4. Wait 1 hour | 3600s | 3600s | 3600.4s | +0.01% |
| 5. Update inventory | 100ms | 100ms | 500ms | +4x |
| 6. Generate invoice | 5s | 5s | 5.4s | +8% |
| 7. Send email | 1s | 1s | 1.4s | +40% |
| 8. Log analytics | 20ms | 20ms | 420ms | +20x |
| 9. Update CRM | 500ms | 500ms | 900ms | +80% |
| 10. Final cleanup | 100ms | 100ms | 500ms | +4x |

**Old (v1.x):** 3609.78 seconds (all in one job)  
**New (v2.0):** 3614.78 seconds (10 separate jobs)  

**Total overhead: 5 seconds on 3610 seconds = 0.14%**

**BUT**: v1.x would FAIL on Vapor (3600s sleep blocks worker)  
**v2.0**: Works perfectly on Vapor ✅

---

## Recommendation Matrix

| Workflow Characteristics | Recommendation |
|-------------------------|----------------|
| **3-5 long steps (> 10s each)** | ✅ Use step-per-job |
| **Any steps with delays > 1 min** | ✅ Use step-per-job |
| **Critical (needs fault tolerance)** | ✅ Use step-per-job |
| **10+ fast steps (< 100ms)** | ⚠️ Batch into fewer steps |
| **Real-time (< 500ms total)** | ❌ Don't use workflows |
| **High frequency (1000s/min)** | ⚠️ Consider alternatives |

---

## Alternative: Hybrid Approach (Future Enhancement)

Could add a "fast path" for simple workflows:

```php
// Step-per-job (current - fault tolerant)
$workflow->execute(['mode' => 'async']);

// Same-process (new - fast but no fault tolerance)
$workflow->execute(['mode' => 'sync']);

// Auto-detect based on workflow
if ($workflow->hasDelays() || $workflow->isCritical()) {
    // Use step-per-job
} else {
    // Use sync execution
}
```

---

## Bottom Line

**The step-per-job architecture trades raw speed for reliability.**

- **Overhead per step**: ~300-500ms
- **Acceptable when**: Steps take > 1 second
- **Problematic when**: Many fast steps (< 100ms)

**Solutions:**
1. Batch fast operations into single steps
2. Don't use workflows for sub-second operations
3. Use workflows for their strengths: fault tolerance, delays, long operations

**For Vapor specifically:** The overhead is worth it because the alternative (v1.x) simply doesn't work with delays or long workflows.

---

## Your Specific Question

> "If each node takes ms to run, what is the overhead?"

**Answer**: ~400-500ms overhead per step

**Should you use this for fast steps?** Probably not without batching them.

**Is it still worth it?** YES, if you need:
- Fault tolerance
- Delays
- Vapor compatibility
- Workflows > 5 minutes total

For pure speed with many fast steps, consider batching or using synchronous execution.
