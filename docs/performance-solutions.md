# Performance Optimization Strategies

## Ways to Reduce/Eliminate Step-Per-Job Overhead

---

## Strategy 1: Synchronous Execution Mode (Easiest) ⚡

**Add a sync mode that executes all steps in one process.**

### Implementation

```php
// Add to WorkflowEngine.php
public function executeSync(WorkflowExecution $execution): void
{
    try {
        $execution->markAsStarted();
        
        $workflow = $execution->workflow;
        $context = $execution->context?->getArrayCopy() ?? [];
        
        // Get all steps in order
        $steps = $workflow->steps()
            ->enabled()
            ->orderBy('position')
            ->get();
        
        // Execute all steps sequentially in same process
        foreach ($steps as $step) {
            // Check conditions
            if ($step->hasConditions() && !$step->evaluateConditions($context)) {
                continue;
            }
            
            // Execute step
            $output = $this->stepExecutor->execute($step, $context);
            $context = array_merge($context, $output);
            
            // Update context
            $execution->update(['context' => $context]);
        }
        
        $execution->markAsCompleted($context);
    } catch (\Exception $e) {
        $execution->markAsFailed($e->getMessage());
        throw $e;
    }
}
```

### Usage

```php
// Workflow model - add mode parameter
public function execute(array $context = [], string $mode = 'async'): WorkflowExecution
{
    $execution = $this->executions()->create([...]);
    
    if ($mode === 'sync') {
        // Fast: No queue overhead
        app(WorkflowEngine::class)->executeSync($execution);
    } else {
        // Fault-tolerant: Step-per-job
        app(WorkflowEngine::class)->start($execution);
    }
    
    return $execution;
}

// Use it
$workflow->execute($context, 'sync');  // Fast!
```

### When to Use

✅ **Use sync mode when:**
- All steps are fast (< 1 second)
- No delays in workflow
- Fault tolerance not critical
- Testing/development

❌ **Don't use sync mode when:**
- Any step has delays
- Total workflow > 10 minutes
- Need resume capability
- Running on Vapor (for long workflows)

### Performance Gain

**10 fast steps:**
- Async: ~4 seconds (10 × 400ms)
- Sync: ~100ms (no queue overhead)
- **40x faster!** 🚀

---

## Strategy 2: Hybrid Auto-Detection (Smart) 🧠

**Automatically choose sync or async based on workflow characteristics.**

### Implementation

```php
// Add to Workflow model
public function shouldExecuteSync(): bool
{
    // Check if workflow has any delays
    if ($this->steps()->where('type', 'delay')->exists()) {
        return false;  // Must use async
    }
    
    // Check if any step has timeout > 5 minutes
    if ($this->steps()->where('timeout', '>', 300)->exists()) {
        return false;  // Too long for sync
    }
    
    // Check if workflow is marked as critical (needs fault tolerance)
    if ($this->configuration['critical'] ?? false) {
        return false;  // Use async for resume capability
    }
    
    // Check total estimated duration
    $estimatedDuration = $this->steps()->sum('timeout') ?? 300;
    if ($estimatedDuration > 600) {
        return false;  // Too long
    }
    
    return true;  // Safe for sync!
}

// Update execute method
public function execute(array $context = [], ?string $mode = null): WorkflowExecution
{
    $mode ??= $this->shouldExecuteSync() ? 'sync' : 'async';
    
    $execution = $this->executions()->create([...]);
    
    if ($mode === 'sync') {
        app(WorkflowEngine::class)->executeSync($execution);
    } else {
        app(WorkflowEngine::class)->start($execution);
    }
    
    return $execution;
}
```

### Usage

```php
// Automatic - workflow decides
$workflow->execute($context);  // Auto-detects best mode

// Manual override
$workflow->execute($context, 'sync');   // Force sync
$workflow->execute($context, 'async');  // Force async
```

---

## Strategy 3: Step Grouping (Medium Effort) 📦

**Execute multiple steps together in one job.**

### Implementation

```php
// Add to workflow_steps migration
Schema::table('workflow_steps', function (Blueprint $table) {
    $table->string('execution_group')->nullable();
});

// Create steps with groups
$workflow->steps()->create([
    'name' => 'Validate Email',
    'execution_group' => 'validations',  // Group identifier
    ...
]);

$workflow->steps()->create([
    'name' => 'Validate Phone',
    'execution_group' => 'validations',  // Same group
    ...
]);

// WorkflowEngine executes all steps in group together
```

### Performance Gain

**10 steps in 2 groups:**
- Without grouping: 10 × 400ms = 4s
- With grouping: 2 × 400ms = 800ms
- **5x faster!**

---

## Strategy 4: Redis Queue (Quick Win) 🔴

**Use Redis instead of SQS for much lower latency.**

### Configuration

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => 5,
    ],
],

// config/forgepulse.php
'execution' => [
    'queue_connection' => 'redis',  // Instead of 'sqs'
    'queue' => 'workflows',
],
```

### Performance Gain

**Queue overhead:**
- SQS: ~300-500ms per step
- Redis: ~10-50ms per step
- **10-30x faster queue operations!**

**Drawback**: Redis isn't fully managed on Vapor (need ElastiCache)

---

## Strategy 5: Database Optimization (Always Do) 💾

### 1. Connection Pooling

```php
// config/database.php (for RDS)
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,  // Reuse connections
    ],
],
```

### 2. Eager Loading

```php
// ExecuteStepJob - current
$execution = WorkflowExecution::find($this->executionId);
$step = WorkflowStep::find($this->stepId);
$workflow = $execution->workflow;

// Optimized
$execution = WorkflowExecution::with([
    'workflow.steps',
    'currentStep',
])->find($this->executionId);

// Save 2-3 queries per step
```

### 3. Cache Workflow Definitions

```php
// WorkflowEngine
protected function getWorkflow(int $id): Workflow
{
    return Cache::remember(
        "workflow:{$id}",
        3600,
        fn() => Workflow::with('steps')->find($id)
    );
}
```

### Performance Gain

- Reduces DB overhead from ~80ms to ~30ms
- **50% reduction in DB time**

---

## Strategy 6: Checkpointed Sync Execution (Advanced) 🎯

**Execute synchronously but save checkpoints for resume capability.**

### Implementation

```php
// Hybrid approach: Fast execution with fault tolerance
public function executeSyncWithCheckpoints(WorkflowExecution $execution): void
{
    $execution->markAsStarted();
    $context = $execution->context?->getArrayCopy() ?? [];
    
    $steps = $workflow->steps()->enabled()->orderBy('position')->get();
    
    foreach ($steps as $step) {
        try {
            // Execute step
            $output = $this->stepExecutor->execute($step, $context);
            $context = array_merge($context, $output);
            
            // Checkpoint after each step (fast DB write)
            $execution->update([
                'current_step_id' => $step->id,
                'completed_step_ids' => [...($execution->completed_step_ids ?? []), $step->id],
                'context' => $context,
            ]);
            
        } catch (\Exception $e) {
            // Failed at this step - can resume from here
            $execution->markAsFailed($e->getMessage());
            throw $e;
        }
    }
    
    $execution->markAsCompleted($context);
}
```

### Benefits

✅ Fast (no queue overhead)  
✅ Can resume from failure  
✅ Progress tracking  

### Performance

**10 steps:**
- Step-per-job: ~4 seconds
- Sync with checkpoints: ~150ms
- **26x faster with fault tolerance!**

---

## Strategy 7: Intelligent Queuing (Best of Both) 🎨

**Only use queue for steps that need it.**

### Implementation

```php
// WorkflowEngine::advance()
public function advance(WorkflowExecution $execution): void
{
    $nextSteps = $this->getNextSteps($execution);
    
    foreach ($nextSteps as $step) {
        if ($this->needsAsyncExecution($step)) {
            // Use queue for delays, long steps, etc.
            ExecuteStepJob::dispatch($execution->id, $step->id);
        } else {
            // Execute immediately in same process
            $this->executeStepSync($execution, $step);
            $this->advance($execution);  // Continue
        }
    }
}

protected function needsAsyncExecution(WorkflowStep $step): bool
{
    // Queue if it's a delay
    if ($step->type->value === 'delay') {
        return true;
    }
    
    // Queue if timeout > 5 seconds
    if (($step->timeout ?? 0) > 5) {
        return true;
    }
    
    // Queue if it's a webhook (external API)
    if ($step->type->value === 'webhook') {
        return true;
    }
    
    // Execute sync for fast steps
    return false;
}
```

### Performance

**Workflow: 5 fast steps + 1 delay + 2 fast steps:**
- All async: 8 × 400ms = 3.2s
- Intelligent: 2 × 400ms (only delay + step after) = 800ms
- **4x faster!**

---

## Strategy 8: Parallel Execution (v2.1 Feature) ⚡

**Execute independent steps simultaneously.**

```php
// When multiple steps don't depend on each other
$workflow->steps()->create([
    'name' => 'Send Email',
    'execution_mode' => 'parallel',
    'parallel_group' => 'notifications',
]);

$workflow->steps()->create([
    'name' => 'Send SMS',
    'execution_mode' => 'parallel',
    'parallel_group' => 'notifications',
]);

// Both dispatch at same time
// Total time = slowest step, not sum
```

### Performance Gain

**3 parallel steps (2s each):**
- Sequential: 3 × 2s = 6s
- Parallel: 2s (all at once)
- **3x faster!**

---

## Recommendation Matrix

| Use Case | Best Strategy | Performance | Complexity |
|----------|--------------|-------------|------------|
| Fast workflow (< 1s) | Sync mode | 40x faster | Easy |
| Mixed fast/slow steps | Intelligent queuing | 4-10x faster | Medium |
| Need fault tolerance | Checkpointed sync | 26x faster | Medium |
| High throughput | Redis queue | 10x faster | Easy |
| Complex workflows | Hybrid auto-detect | 2-5x faster | Easy |

---

## Implementation Priority

### Phase 1: Quick Wins (1-2 hours)
1. ✅ Add sync execution mode
2. ✅ Add hybrid auto-detection
3. ✅ Database eager loading

**Gain: 10-40x faster for fast workflows**

### Phase 2: Medium Effort (4-8 hours)
4. ✅ Intelligent queuing (only queue when needed)
5. ✅ Checkpointed sync execution
6. ✅ Step grouping

**Gain: Combines speed + fault tolerance**

### Phase 3: Infrastructure (depends)
7. ✅ Redis queue (if not on Vapor, or use ElastiCache)
8. ✅ Parallel execution (v2.1)

**Gain: Another 2-10x improvement**

---

## Practical Example: E-commerce Workflow

### Current (All Async)
```php
$workflow = Workflow::create(['name' => 'Order Processing']);

// 8 steps, each dispatched to queue
Step 1: Validate (10ms) → +400ms = 410ms
Step 2: Check stock (20ms) → +400ms = 420ms
Step 3: Calculate tax (15ms) → +400ms = 415ms
Step 4: Process payment (2s) → +400ms = 2.4s
Step 5: Update inventory (50ms) → +400ms = 450ms
Step 6: Generate invoice (100ms) → +400ms = 500ms
Step 7: Send email (500ms) → +400ms = 900ms
Step 8: Log analytics (10ms) → +400ms = 410ms

Total: 5.9 seconds
```

### Optimized (Intelligent Queuing)
```php
// Fast steps execute sync, only queue slow ones
Steps 1-3: Validate + stock + tax (45ms sync)
Step 4: Process payment (2.4s) → QUEUED (slow)
Steps 5-8: Inventory + invoice + email + log (660ms sync)

Total: 3.1 seconds (1.9x faster!)
```

### Optimized (Sync Mode)
```php
// All steps in one process
$workflow->execute($context, 'sync');

Total: 2.7 seconds (2.2x faster!)
```

---

## Bottom Line

**Yes, there are multiple ways around the overhead!**

**Easiest wins:**
1. Add sync mode for fast workflows (40x faster)
2. Auto-detect sync vs async (smart!)
3. Use Redis instead of SQS (10x faster queues)

**Best approach:** Intelligent hybrid that uses queue only when needed.

Would you like me to implement any of these strategies?
