# Configurable Timeout and Retries - Implementation Complete ✅

## Question
> "Can timeout and retries be configured in the workflow itself?"

## Answer
**Yes!** Timeout and max retries can now be configured at **three levels** with intelligent priority:

```
Step Level > Workflow Level > Global Config
```

---

## What Was Implemented

### 1. Database Schema ✅
- **Migration 1**: Added `timeout` and `max_retries` to `workflows` table
- **Migration 2**: Added `max_retries` to `workflow_steps` table
- Both fields are nullable (fall back to parent level if not set)

### 2. Priority Hierarchy ✅
Updated `ExecuteStepJob` to check in order:
```php
$timeout = $step->timeout           // First priority
    ?? $workflow->timeout           // Second priority  
    ?? config('step_timeout');      // Fallback

$retries = $step->max_retries       // First priority
    ?? $workflow->max_retries       // Second priority
    ?? config('max_retries');       // Fallback
```

### 3. Model Updates ✅
- **Workflow**: Added `timeout` and `max_retries` to fillable
- **WorkflowStep**: Added `max_retries` to fillable
- Both models expose new fields in API

### 4. API Validation ✅
- **CreateWorkflowRequest**: Validates timeout (1-3600s) and max_retries (0-10)
- **UpdateWorkflowRequest**: Same validation for updates
- Step-level validation matches workflow-level

### 5. API Resources ✅
- **WorkflowResource**: Returns `timeout` and `max_retries`
- **StepResource**: Returns `timeout` and `max_retries`
- Full API transparency of settings

### 6. Documentation ✅
Created comprehensive guide: `docs/timeout-and-retries.md`
- Priority hierarchy explanation
- API examples (HTTP and Eloquent)
- Use case patterns
- Vapor considerations
- Best practices

---

## Usage Examples

### Workflow Level
```php
$workflow = Workflow::create([
    'name' => 'Payment Processing',
    'timeout' => 180,        // 3 minutes for all steps
    'max_retries' => 5,      // 5 retries for all steps
]);
```

### Step Level
```php
$workflow->steps()->create([
    'name' => 'Process Credit Card',
    'timeout' => 60,         // Override: 1 minute just for this step
    'max_retries' => 10,     // Override: 10 retries just for this step
    'configuration' => [...],
]);
```

### API
```http
POST /api/forgepulse/workflows
{
  "name": "My Workflow",
  "timeout": 300,
  "max_retries": 5,
  "steps": [{
    "name": "Critical Step",
    "timeout": 120,
    "max_retries": 10,
    ...
  }]
}
```

---

## Benefits

### 1. Flexibility ✅
- Global defaults for most workflows
- Workflow-level for domain requirements
- Step-level for critical exceptions

### 2. Vapor-Optimized ✅
- Validation ensures timeouts < 1 hour
- Recommended: Keep under 5 minutes
- Per-step control for Lambda efficiency

### 3. Production-Ready ✅
- Sensible validation (timeout: 1-3600s, retries: 0-10)
- API fully supports the feature
- Comprehensive documentation

### 4. Backward Compatible ✅
- Existing workflows use global config
- No migration required for existing data
- Opt-in per workflow/step

---

## Files Changed

### New Migrations
1. `2025_12_20_000002_add_timeout_retries_to_workflows.php`
2. `2025_12_20_000003_add_max_retries_to_workflow_steps.php`

### Modified Files
1. `src/Jobs/ExecuteStepJob.php` - Priority hierarchy logic
2. `src/Models/Workflow.php` - Added fields to fillable
3. `src/Models/WorkflowStep.php` - Added max_retries to fillable
4. `src/Http/Requests/CreateWorkflowRequest.php` - Validation
5. `src/Http/Requests/UpdateWorkflowRequest.php` - Validation
6. `src/Http/Resources/WorkflowResource.php` - API output
7. `src/Http/Resources/StepResource.php` - API output

### New Documentation
1. `docs/timeout-and-retries.md` - Complete guide

---

## Migration Instructions

### For Existing Users
```bash
# Run new migrations
php artisan migrate

# Restart queue workers
php artisan queue:restart
```

### Existing Workflows
- Continue working without changes
- Use global config defaults
- Optionally set workflow/step-level overrides

### New Workflows
```php
// Optional: Set workflow defaults
Workflow::create([
    'name' => 'My Workflow',
    'timeout' => 300,
    'max_retries' => 5,
]);

// Optional: Override per step
$workflow->steps()->create([
    'timeout' => 120,
    'max_retries' => 10,
    ...
]);
```

---

## Validation

**Timeout:**
- Min: 1 second
- Max: 3600 seconds (1 hour)
- Integer only

**Max Retries:**
- Min: 0 (no retries)
- Max: 10
- Integer only

---

## Answer Summary

**Yes, timeout and retries are fully configurable in workflows!**

You can set them at:
1. **Global level** (config file) - Defaults for all workflows
2. **Workflow level** - Override for specific workflow  
3. **Step level** - Override for critical steps

The system uses intelligent priority: Step → Workflow → Config, giving you maximum flexibility while maintaining simplicity.

Perfect for Vapor with validation ensuring reasonable limits!

---

**Feature Status**: ✅ **Complete and Production-Ready**
