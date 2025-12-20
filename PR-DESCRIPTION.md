# Pull Request: Workflow Engine Refactor + Action Schema System

## Description

This PR introduces two major enhancements to ForgePulse:

### 1. Step-Per-Job Architecture Refactor
A fundamental architectural refactor of the workflow execution engine, transitioning from a monolithic single-job model to a step-per-job architecture. This change significantly enhances fault tolerance, scalability, and compatibility with serverless environments like Laravel Vapor.

**Key changes include:**

- **ExecuteStepJob**: A new queued job responsible for executing a single workflow step.
- **WorkflowEngine Refactor**: Rewritten to orchestrate workflows by dispatching individual ExecuteStepJob instances for each step.
- **DelayHandler Fix**: Replaced blocking `sleep()` calls with proper job scheduling using `->delay()`.
- **ActionHandler Enhancement**: Expanded to support direct calls to existing Laravel services, invokable classes, and jobs (synchronously or asynchronously), with auto-detection of common method names (`__invoke`, `handle`, `execute`, `asAction`).
- **WorkflowExecution Model**: Enhanced with `current_step_id` and `completed_step_ids` for state persistence and a `resumeExecution()` method for fault recovery.
- **Database Migration**: Adds necessary columns to `workflow_executions` for step tracking.
- **Synchronous Execution Mode**: Added intelligent auto-detection for fast workflows that can run without queue overhead.
- **Timeout & Retries Configuration**: Three-tier configuration (Step > Workflow > Global) with max timeout of 600 seconds for Vapor compatibility.

### 2. Action Schema Discovery & Management System
A complete OpenAPI 3.0-based schema system that enables runtime discovery, validation, and management of workflow actions. This addresses the fundamental question: "How do I know what parameters my actions need and what they return?"

**Key changes include:**

- **ActionSchema Service**: Discovers action schemas using three-tier priority (Database → Code → Reflection) for maximum flexibility.
- **SchemaValidator Service**: Validates parameters against JSON Schema rules (types, min/max, patterns, enums, etc.).
- **Database Storage**: New `action_schemas` table stores OpenAPI schemas for both internal PHP actions and external APIs (Stripe, Twilio, etc.).
- **ProvidesSchema Interface**: Optional interface for actions to self-describe using standard OpenAPI 3.0 format.
- **PHP 8 Attributes**: Optional `WorkflowAction`, `WorkflowParameter`, and `WorkflowOutput` attributes for rich metadata.
- **Schema Discovery API**: Endpoints to get schemas, validate parameters, and export complete OpenAPI specifications.
- **Schema Management API**: Full CRUD endpoints to manage schemas in database, sync code-based schemas, and import external OpenAPI specs.
- **External Service Support**: Actions can now reference external APIs stored in database (e.g., `Stripe::Charge`, `Twilio::SendSMS`) alongside internal PHP actions.
- **ActionSchema Model**: With scopes, versioning support, and query helpers for discovering available actions.

---

## Motivation and Context

### Step-Per-Job Architecture
The previous architecture, which executed an entire workflow within a single long-running job, presented several critical limitations:

- **Vapor/Lambda Incompatibility**: Long-running workflows frequently hit the 15-minute Lambda timeout, and `sleep()` calls blocked serverless functions, leading to wasted resources and execution failures.
- **Lack of Fault Tolerance**: A failure at any point in the workflow meant losing all progress, with no mechanism to resume from the last successful step.
- **Inefficient Delays**: DelayHandler blocked queue workers for the entire delay duration, consuming resources unnecessarily.
- **Poor Developer Experience**: Required custom `execute()` methods or wrapper classes to integrate existing Laravel services and jobs into workflow actions.

This refactor addresses these issues by adopting an industry-standard, durable workflow pattern. It makes ForgePulse production-ready for complex, long-running, and fault-tolerant workflows, especially on modern cloud infrastructure, while preserving the existing UI and API.

### Action Schema System
The workflow engine lacked a standardized way to discover and document action requirements, creating several pain points:

- **No Schema Discovery**: Developers and UIs had no way to know what parameters an action requires or what it returns.
- **External API Management**: No mechanism to document and manage third-party service integrations (Stripe, Twilio, SendGrid, etc.).
- **Manual Documentation**: Actions required separate documentation that could drift from actual implementation.
- **No Runtime Flexibility**: Changing action parameters or validation rules required code deploys.
- **Poor UI Integration**: Dynamic form generation was impossible without schema information.
- **No Validation**: Parameters weren't validated before execution, causing runtime errors.

The OpenAPI-based schema system solves these issues by:

1. **Using Industry Standards**: OpenAPI 3.0 format (everyone knows it, vast tooling ecosystem)
2. **Database-First Priority**: Schemas stored in DB for runtime updates without deploys
3. **Code Fallback**: Optional `ProvidesSchema` interface for version-controlled schemas
4. **Reflection Safety Net**: Auto-generates schemas from method signatures (zero setup)
5. **Unified Management**: Internal PHP actions and external APIs managed the same way
6. **Built-in Validation**: JSON Schema validation with comprehensive rule support
7. **Export Capabilities**: Generate complete OpenAPI specs for Swagger UI, Postman, TypeScript types

---

## Benefits

### Step-Per-Job Architecture
✅ **Vapor Compatible** - Each step respects Lambda timeout limits  
✅ **Fault Tolerant** - Resume from any failed step  
✅ **Scalable** - Steps can run in parallel (future feature)  
✅ **Resource Efficient** - No blocking delays  
✅ **Better DX** - Use existing services/jobs directly  
✅ **Granular Control** - Per-step timeout and retry configuration  

### Action Schema System
✅ **Schema Discovery** - Know what any action needs via API  
✅ **Dynamic UI Forms** - Generate forms from schemas automatically  
✅ **External Services** - Document Stripe, Twilio, etc. in database  
✅ **Runtime Updates** - Modify schemas without code deploys  
✅ **Parameter Validation** - Validate before execution to catch errors early  
✅ **Type Generation** - Export to TypeScript, Python, Java clients  
✅ **Interactive Docs** - Swagger UI integration ready  
✅ **Versioning** - Track schema changes with built-in version control  
✅ **Zero Setup** - Works with existing code via reflection  
✅ **Backward Compatible** - No breaking changes  

---

## API Changes

### New Endpoints (Schema System)

**Schema Discovery:**
```http
GET  /api/forgepulse/actions/schema          # Get single action schema
POST /api/forgepulse/actions/schema/bulk     # Get multiple schemas
POST /api/forgepulse/actions/validate        # Validate parameters
POST /api/forgepulse/actions/openapi         # Export OpenAPI spec
```

**Schema Management:**
```http
GET    /api/forgepulse/action-schemas        # List all schemas (with filters)
POST   /api/forgepulse/action-schemas        # Create schema
GET    /api/forgepulse/action-schemas/{id}   # Get single schema
PUT    /api/forgepulse/action-schemas/{id}   # Update schema
DELETE /api/forgepulse/action-schemas/{id}   # Delete schema
POST   /api/forgepulse/action-schemas/sync   # Sync code schemas to DB
POST   /api/forgepulse/action-schemas/import # Import from OpenAPI URL
```

---

## Database Changes

### New Tables

**action_schemas** - Stores OpenAPI schemas for actions
```sql
- id, action_class, action_type, method
- schema (JSON - OpenAPI 3.0 format)
- name, description, category, tags
- is_external (false=PHP, true=HTTP API)
- is_active, source, version
- config (JSON - timeout, endpoint, auth, etc.)
- timestamps, soft deletes
```

### Modified Tables

**workflow_executions** - Step tracking for fault tolerance
```sql
+ current_step_id (FK to workflow_steps)
+ completed_step_ids (JSON array)
```

**workflows** - Timeout and retry configuration
```sql
+ timeout (max 600s for Vapor)
+ max_retries
```

**workflow_steps** - Per-step retry configuration
```sql
+ max_retries
```

---

## Usage Examples

### 1. Self-Describing Action (Code-Based)

```php
use AlizHarb\ForgePulse\Contracts\ProvidesSchema;

class ProcessOrderService implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'summary' => 'Process Customer Order',
            'parameters' => [
                'customerId' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                ],
                'items' => [
                    'type' => 'array',
                    'required' => true,
                    'minItems' => 1,
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Order processed',
                    'content' => ['application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'order_id' => ['type' => 'integer'],
                                'total' => ['type' => 'number'],
                            ],
                        ],
                    ]],
                ],
            ],
        ];
    }
    
    public function handle(int $customerId, array $items): array
    {
        // Implementation
        return ['order_id' => 123, 'total' => 99.99];
    }
}
```

### 2. External Service (Database-Stored)

```php
use AlizHarb\ForgePulse\Models\ActionSchema;

ActionSchema::create([
    'action_class' => 'Stripe::Charge',
    'action_type' => 'external',
    'is_external' => true,
    'name' => 'Stripe: Create Charge',
    'category' => 'payments',
    'schema' => [
        'summary' => 'Create Stripe Charge',
        'parameters' => [
            'amount' => ['type' => 'integer', 'minimum' => 50],
            'payment_method' => ['type' => 'string', 'required' => true],
        ],
    ],
    'config' => [
        'endpoint' => 'https://api.stripe.com/v1/charges',
        'method' => 'POST',
        'auth' => 'bearer',
    ],
]);
```

### 3. Using in Workflow

```php
$workflow->steps()->create([
    'name' => 'Process Order',
    'type' => 'action',
    'configuration' => [
        'class' => 'App\\Actions\\ProcessOrderService', // Internal
        'parameters' => [
            'customerId' => '{{customer.id}}',
            'items' => [['product_id' => 1, 'quantity' => 2]],
        ],
    ],
]);

$workflow->steps()->create([
    'name' => 'Charge Payment',
    'type' => 'action',
    'configuration' => [
        'class' => 'Stripe::Charge', // External (from DB)
        'parameters' => [
            'amount' => 2000,
            'payment_method' => '{{payment.method_id}}',
        ],
    ],
]);
```

### 4. Get Schema for UI

```javascript
// Get schema to build dynamic form
const response = await fetch('/api/forgepulse/actions/schema?class=App\\Actions\\ProcessOrderService');
const { data: schema } = await response.json();

// Generate form fields
schema.parameters.forEach(param => {
    renderField({
        name: param.name,
        type: param.type,
        required: param.required,
        description: param.description,
        validation: param.validation,
    });
});
```

### 5. Validate Before Execution

```php
$result = Http::post('/api/forgepulse/actions/validate', [
    'class' => 'App\\Actions\\ProcessOrderService',
    'parameters' => ['customerId' => 123, 'items' => [...]],
]);

if (!$result['valid']) {
    // Handle validation errors
    foreach ($result['errors'] as $error) {
        Log::error($error);
    }
}
```

---

## Breaking Changes

**None** - Both systems are fully backward compatible:

### Step-Per-Job Architecture
- Existing workflows continue to execute
- No changes required to action implementations
- Old `ExecuteWorkflowJob` deprecated but still functional

### Action Schema System
- All existing actions work via reflection fallback
- Optional `ProvidesSchema` interface (not required)
- Database schemas are optional (code/reflection still work)

---

## Migration Guide

### Step-Per-Job Architecture
No migration needed - new architecture is automatic for new workflow executions.

**Optional**: Update workflows to use new timeout/retry configuration:
```php
$workflow->update(['timeout' => 300, 'max_retries' => 3]);
$step->update(['timeout' => 60, 'max_retries' => 5]);
```

### Action Schema System

**Phase 1: Keep Existing Code** (Zero Changes)
- Nothing to do - reflection works automatically

**Phase 2: Add Schemas to Code** (Optional)
```php
class MyAction implements ProvidesSchema {
    public static function schema(): array {
        return [ /* OpenAPI schema */ ];
    }
}
```

**Phase 3: Sync to Database** (Optional)
```bash
POST /api/forgepulse/action-schemas/sync
Body: { "classes": ["App\\Actions\\MyAction"] }
```

**Phase 4: Add External Services** (As Needed)
```php
ActionSchema::create([
    'action_class' => 'Stripe::Charge',
    'is_external' => true,
    // ...
]);
```

---

## Testing

### Step-Per-Job Architecture
- ✅ All existing tests pass
- ✅ 7 new tests for step-per-job execution
- ✅ Tests for sync/async mode detection
- ✅ Tests for fault tolerance and resume

### Action Schema System
- ✅ Schema discovery from code, attributes, reflection
- ✅ Database priority system
- ✅ Parameter validation (types, ranges, patterns, enums)
- ✅ OpenAPI export
- ✅ CRUD operations

---

## Documentation

### Included in PR
- `IMPLEMENTATION-SUMMARY.md` - Complete 50+ page guide covering both systems
- `QUICK-REFERENCE.md` - Quick start guide
- `PR-SUMMARY.md` - 10-point bullet summary

### Migration Guides
- Step-per-job architecture migration
- Schema system adoption path (zero-risk phases)

---

## Future Enhancements

### Step-Per-Job Architecture
- Parallel step execution (groundwork laid)
- Advanced scheduling options
- Step-level queue configuration

### Action Schema System
- Swagger UI view at `/docs/actions`
- Artisan command to generate OpenAPI specs
- Auto-import popular service schemas (Stripe, Twilio, AWS)
- Schema diff/changelog tracking
- Multi-tenant schema isolation

---

## Performance Impact

### Step-Per-Job Architecture
- **Fast workflows (<5s)**: Minimal overhead with auto-sync mode
- **Long workflows (>5s)**: Better resource utilization (no blocking)
- **Failed workflows**: Instant resume vs. complete restart

### Action Schema System
- **Schema lookup**: Cached at application level (negligible overhead)
- **Validation**: Optional pre-execution check (~1-5ms per action)
- **Database queries**: Indexed lookups on `action_class` + `is_active`

---

## Checklist

- [x] Database migrations created and tested
- [x] All existing tests pass
- [x] New tests added for new functionality
- [x] Documentation updated
- [x] Backward compatibility maintained
- [x] API endpoints documented
- [x] Examples provided
- [x] No breaking changes
