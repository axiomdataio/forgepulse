# Architecture Overview

## v2.0 Architecture: Step-Per-Job Model

ForgePulse v2.0 uses a step-per-job execution model for maximum reliability, scalability, and Vapor compatibility.

## How It Works

### Workflow Execution Flow

```
1. User calls: $workflow->execute($context)
   ↓
2. WorkflowExecution created (status: pending)
   ↓
3. WorkflowEngine::start() called
   ↓
4. Marks execution as 'running'
   ↓
5. Dispatches ExecuteStepJob for first step(s)
   ↓
6. [Job 1] ExecuteStepJob runs
   - Executes step
   - Saves output to execution context
   - Marks step as completed
   - Calls WorkflowEngine::advance()
   ↓
7. WorkflowEngine::advance()
   - Gets next step(s) based on completed steps
   - If delay step: schedules with delay
   - If regular step: dispatches immediately
   - If no more steps: marks workflow complete
   ↓
8. [Job 2] ExecuteStepJob runs for next step
   (repeat until workflow complete)
```

### State Persistence

Each execution tracks its progress in the database:

```php
workflow_executions:
  - current_step_id       // Currently executing step
  - completed_step_ids    // Array of completed step IDs
  - context               // Accumulated output from all steps
  - status                // pending, running, completed, failed
```

This enables:
- **Fault tolerance** - If a job fails, we know exactly where to resume
- **Progress tracking** - UI can show current step
- **Resume capability** - Can restart from failed step

## Key Components

### ExecuteStepJob

**Purpose:** Execute a single workflow step

**Lifecycle:**
1. Loads execution and step from database
2. Checks if execution is paused
3. Calls `WorkflowEngine::executeStep()`
4. Calls `WorkflowEngine::advance()` to dispatch next step(s)

**Properties:**
- `$executionId` - Workflow execution ID
- `$stepId` - Step to execute
- `$tries` - Retry attempts (configurable)
- `$timeout` - Step timeout (configurable)
- `$queue` - Queue name (configurable)

**Uniqueness:**
- Implements `ShouldBeUnique`
- Key: `workflow-execution-{executionId}-step-{stepId}`
- Prevents duplicate execution of same step

### WorkflowEngine

**Purpose:** Orchestrate workflow execution

**Methods:**

#### `start(WorkflowExecution $execution)`
Starts a workflow by:
1. Marking execution as started
2. Dispatching first step(s)

#### `executeStep(WorkflowExecution $execution, WorkflowStep $step)`
Executes a single step:
1. Updates `current_step_id`
2. Creates execution log
3. Checks step conditions
4. Executes step via StepExecutor
5. Merges output into context
6. Marks step as completed
7. Handles errors

#### `advance(WorkflowExecution $execution)`
Determines and dispatches next steps:
1. Gets next steps to execute
2. Calculates delay (if previous step was delay)
3. Dispatches ExecuteStepJob for each next step
4. Or completes workflow if no more steps

### StepExecutor

**Purpose:** Execute individual steps based on type

**Flow:**
1. Gets handler class from step type
2. Resolves handler from container (DI)
3. Calls handler's `handle()` method
4. Returns output array

### Step Handlers

Each step type has a handler:

- **ActionHandler** - Calls services, jobs, actions
- **ConditionHandler** - Evaluates conditional logic
- **DelayHandler** - Schedules delayed execution
- **NotificationHandler** - Sends notifications
- **WebhookHandler** - Makes HTTP requests
- **EventHandler** - Dispatches Laravel events
- **JobHandler** - Dispatches jobs to queue

## Advantages Over v1.x

### v1.x Architecture (Single Long Job)

```
❌ Entire workflow in one job
❌ Delays block worker with sleep()
❌ Failure loses all progress
❌ No parallelization possible
❌ Lambda timeouts on long workflows
❌ Wasted resources during delays
```

### v2.0 Architecture (Step-Per-Job)

```
✅ Each step is independent job
✅ Delays use job scheduling
✅ Can resume from failed step
✅ Parallel execution ready (v2.1)
✅ Each step under Lambda timeout
✅ Workers freed immediately
```

## Vapor/Lambda Compatibility

### Why It Works

**v1.x Issues:**
- 15-minute Lambda max timeout
- Workflow with 1-hour delay would fail
- Blocked Lambda for entire duration

**v2.0 Solutions:**
- Each step completes quickly (< 5 min typical)
- Delays schedule next job, don't block
- Lambda freed after each step
- Natural fit for serverless

### Best Practices

**1. Set Appropriate Timeouts**
```php
// config/forgepulse.php
'execution' => [
    'step_timeout' => 300,  // 5 minutes (safe for Lambda)
],
```

**2. Use Delays Properly**
```php
// Good - Schedules next step
['type' => 'delay', 'configuration' => ['seconds' => 3600]]

// Bad - Would block in v1.x (fixed in v2.0!)
```

**3. Break Long Operations Into Steps**
```php
// Instead of one 10-minute processing step
// Break into multiple 2-minute steps with checkpoints
```

**4. Queue Configuration**
```php
// Use SQS on Vapor
QUEUE_CONNECTION=sqs
FORGEPULSE_QUEUE_NAME=workflows
```

## Fault Tolerance

### How Resume Works

**Scenario:** Step 3 of 5 fails

```
Steps: [1] ✅ [2] ✅ [3] ❌ [4] ⏸️ [5] ⏸️

Database state:
  completed_step_ids: [1, 2]
  current_step_id: 3
  status: failed
  error_message: "..."
```

**Resume:**
```php
$execution->resumeExecution();

// Resets status to 'running'
// Clears error message
// Dispatches ExecuteStepJob for step 3 again
```

**Result:**
- Steps 1 & 2 output already saved in context
- Step 3 retries with full context
- If successful, continues to steps 4 & 5

### Retry Strategies

**Job-Level Retries (Automatic):**
```php
// config/forgepulse.php
'execution' => [
    'max_retries' => 3,
    'retry_delay' => 5,
],
```

**Step-Level Timeout:**
```php
$workflow->steps()->create([
    'timeout' => 600,  // 10 minutes max for this step
    'configuration' => [...],
]);
```

**Manual Resume:**
```php
// From failed execution
$execution->resumeExecution();

// Or dispatch specific step
ExecuteStepJob::dispatch($execution->id, $step->id);
```

## Performance Characteristics

### Database Queries Per Step

- Load execution: 1 query
- Load step: 1 query
- Create log: 1 query
- Update context: 1 query
- Mark completed: 1 query
- Get next steps: 1-2 queries
- **Total: ~6-7 queries per step**

### Queue Load

**v1.x:**
- 1 job per workflow
- Long-running jobs block workers

**v2.0:**
- N jobs per workflow (N = number of steps)
- Each job completes quickly
- Better worker utilization

### Scalability

- ✅ Steps can run on different workers
- ✅ Steps can run on different queues
- ✅ Ready for parallel execution (v2.1)
- ✅ Horizontal scaling friendly

## Monitoring

### Horizon Dashboard

Monitor workflow execution:

```php
// Job tags for filtering
ExecuteStepJob tags:
  - workflow:{workflow_id}
  - execution:{execution_id}
  - step:{step_id}
```

### Execution Logs

Track progress in real-time:

```php
$execution = WorkflowExecution::with('logs.step')->find($id);

foreach ($execution->logs as $log) {
    echo "{$log->step->name}: {$log->status}\n";
}
```

### API Monitoring

Use the Execution API:

```php
GET /api/forgepulse/executions/{id}

{
  "status": "running",
  "current_step_id": 3,
  "completed_step_ids": [1, 2],
  "logs": [...]
}
```

## Future Enhancements (v2.1+)

### Parallel Execution

Execute multiple steps simultaneously:

```php
$workflow->steps()->create([
    'execution_mode' => 'parallel',
    'parallel_group' => 'notifications',
]);
```

### Workflow Branches

Conditional branching with join points:

```php
// Branch based on condition
// All branches merge at join step
```

### Rate Limiting

Prevent overwhelming external services:

```php
$workflow->steps()->create([
    'rate_limit' => '10 per minute',
]);
```

### Circuit Breakers

Automatically fail fast on repeated errors:

```php
$workflow->steps()->create([
    'circuit_breaker' => [
        'threshold' => 5,
        'timeout' => 300,
    ],
]);
```

## Summary

ForgePulse v2.0's step-per-job architecture provides:

- **Production-ready** fault tolerance
- **Vapor-compatible** execution model
- **Scalable** design for high-volume workflows
- **Future-proof** foundation for parallel execution
- **Developer-friendly** service integration

The architecture is designed for real-world Laravel applications running on modern infrastructure like Vapor, while maintaining simplicity and ease of use.
