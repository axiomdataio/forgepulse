# ForgePulse Action Schema System - Complete Implementation Summary

**Date:** December 20, 2025  
**Feature:** OpenAPI-based Action Schema Discovery & Management  
**Status:** ✅ Complete

---

## Table of Contents

1. [Problem Statement](#problem-statement)
2. [Solution Overview](#solution-overview)
3. [Architecture](#architecture)
4. [Components Created](#components-created)
5. [Database Schema](#database-schema)
6. [API Endpoints](#api-endpoints)
7. [Schema Priority System](#schema-priority-system)
8. [Usage Examples](#usage-examples)
9. [Benefits](#benefits)
10. [Migration Path](#migration-path)

---

## Problem Statement

### Original Question
> "Is it easy to connect in the action node to existing Laravel action and for a schema to be returned in the API response so the UI can provide the correct info?"

### Core Problems
1. **No schema discovery** - How to know what parameters an action needs?
2. **No output documentation** - What does an action return?
3. **External services** - How to manage Stripe, Twilio, SendGrid schemas?
4. **Runtime flexibility** - Can't update schemas without code deploys
5. **UI builder needs** - Dynamic form generation requires schema information

---

## Solution Overview

### Three-Layered Approach

```
┌─────────────────────────────────────────────────────────────────┐
│                     ACTION SCHEMA SYSTEM                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Priority 1: DATABASE                                            │
│  ├─ Runtime management (no deploys)                             │
│  ├─ External APIs (Stripe, Twilio, etc.)                        │
│  ├─ Override code-based schemas                                 │
│  └─ Versioning & audit trail                                    │
│                                                                   │
│  Priority 2: CODE (ProvidesSchema interface)                    │
│  ├─ Self-describing actions                                     │
│  ├─ OpenAPI 3.0 format                                          │
│  └─ Version controlled with code                                │
│                                                                   │
│  Priority 3: REFLECTION                                          │
│  ├─ Auto-detect from method signature                           │
│  ├─ Zero-setup fallback                                         │
│  └─ Works with any existing code                                │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Why OpenAPI?

**Instead of custom schema format, we use OpenAPI 3.0 because:**

| Benefit | What You Get |
|---------|-------------|
| 🌍 Industry Standard | Everyone knows it, no learning curve |
| 🛠️ Rich Tooling | Swagger UI, Postman, generators, validators |
| ✅ Built-in Validation | JSON Schema with min/max, patterns, enums |
| 🔄 Code Generation | Generate TypeScript/Python/Java clients |
| 📚 Interactive Docs | Swagger UI for live testing |
| 🔗 Interoperability | Works with API gateways, monitoring tools |

---

## Architecture

### System Flow

```
┌──────────────┐      ┌──────────────┐      ┌──────────────┐
│   Workflow   │──1───│ ActionSchema │──2───│   Database   │
│   Executor   │      │   Service    │      │  (schemas)   │
└──────────────┘      └──────────────┘      └──────────────┘
        │                     │                      │
        │                     │                      │
        └─────3───────────────┴──────────4───────────┘
                              │
                              5
                              ↓
                    ┌──────────────────┐
                    │   PHP Action     │
                    │      or          │
                    │  External API    │
                    └──────────────────┘

Flow:
1. Workflow needs to execute step
2. Request schema for action
3. Check database first
4. Fall back to code/reflection
5. Execute action with validated params
```

---

## Components Created

### 1. Core Service Classes

#### **ActionSchema Service** (`src/Services/ActionSchema.php`)
**Purpose:** Discovers and provides action schemas

**Key Methods:**
- `getSchema(string $class, ?string $method): array` - Get schema with priority
- `getBulkSchemas(array $classes): array` - Get multiple schemas
- `exportAsOpenAPI(array $classes): array` - Export as OpenAPI spec

**Features:**
- PHP reflection analysis
- Reads PHP 8 attributes
- Parses docblock comments
- Database-first priority
- OpenAPI format conversion

#### **SchemaValidator Service** (`src/Services/SchemaValidator.php`)
**Purpose:** Validates parameters against JSON Schema

**Validation Rules:**
- Type checking (int, float, string, array, bool)
- Numeric constraints (min, max)
- String constraints (minLength, maxLength, pattern)
- Array constraints (minItems, maxItems)
- Enum validation
- Required field checking

### 2. Database Components

#### **Migration** (`database/migrations/2025_12_20_000004_create_action_schemas_table.php`)

```sql
CREATE TABLE action_schemas (
    id BIGINT PRIMARY KEY,
    action_class VARCHAR(255),      -- PHP class or identifier
    action_type VARCHAR(255),       -- internal|external|webhook|api
    method VARCHAR(255) NULL,       -- PHP method name
    schema JSON,                    -- OpenAPI 3.0 schema
    name VARCHAR(255),              -- Human-readable name
    description TEXT,
    category VARCHAR(255),          -- For grouping
    tags JSON,                      -- For filtering
    is_active BOOLEAN,              -- Enable/disable
    is_external BOOLEAN,            -- PHP vs HTTP
    source VARCHAR(255),            -- code|manual|imported
    version INT,                    -- Schema version
    config JSON,                    -- Additional config
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(action_class, method)
);
```

#### **Model** (`src/Models/ActionSchema.php`)

**Key Features:**
- Scopes: `active()`, `internal()`, `external()`, `category()`, `type()`
- Attributes: `full_identifier`, `parameters`, `response_schema`
- Methods: `isCodeBased()`, `isEditable()`, `createVersion()`

#### **Factory** (`database/factories/ActionSchemaFactory.php`)
For testing and seeding

### 3. PHP 8 Attributes (Optional Enhancement)

#### **WorkflowAction** (`src/Attributes/WorkflowAction.php`)
```php
#[WorkflowAction(
    name: 'Process Order',
    description: 'Process customer order',
    category: 'orders',
    tags: ['payment', 'checkout'],
    timeout: 30
)]
```

#### **WorkflowParameter** (`src/Attributes/WorkflowParameter.php`)
```php
public function handle(
    #[WorkflowParameter(
        description: 'Customer ID',
        example: 12345,
        source: 'context'
    )]
    int $customerId
)
```

#### **WorkflowOutput** (`src/Attributes/WorkflowOutput.php`)
```php
#[WorkflowOutput(fields: [
    'order_id' => ['type' => 'int', 'description' => 'Created order ID'],
    'total' => ['type' => 'float', 'description' => 'Order total']
])]
```

### 4. Interface

#### **ProvidesSchema** (`src/Contracts/ProvidesSchema.php`)

```php
interface ProvidesSchema
{
    /**
     * Get the action schema in OpenAPI 3.0 format.
     */
    public static function schema(): array;
}
```

### 5. API Controllers

#### **ActionSchemaController** (`src/Http/Controllers/Api/ActionSchemaController.php`)
- Schema discovery endpoints
- Validation endpoint
- OpenAPI export endpoint

#### **ActionSchemaManagementController** (`src/Http/Controllers/Api/ActionSchemaManagementController.php`)
- CRUD operations for schemas
- Sync code-based schemas to DB
- Import from external OpenAPI specs

---

## API Endpoints

### Schema Discovery

```http
# Get single action schema
GET /api/forgepulse/actions/schema
  ?class=App\Actions\ProcessOrder
  ?method=handle

# Get multiple schemas
POST /api/forgepulse/actions/schema/bulk
Body: { "classes": ["App\\Actions\\ProcessOrder", ...] }

# Validate parameters
POST /api/forgepulse/actions/validate
Body: {
  "class": "App\\Actions\\ProcessOrder",
  "parameters": { "customerId": 123, ... }
}

# Export as OpenAPI spec
POST /api/forgepulse/actions/openapi
Body: { "classes": ["App\\Actions\\ProcessOrder", ...] }
```

### Schema Management (CRUD)

```http
# List all schemas (with filters)
GET /api/forgepulse/action-schemas
  ?type=external
  ?category=payments
  ?is_active=true

# Get single schema
GET /api/forgepulse/action-schemas/{id}

# Create new schema
POST /api/forgepulse/action-schemas
Body: {
  "action_class": "Stripe::Charge",
  "action_type": "external",
  "is_external": true,
  "name": "Stripe: Create Charge",
  "category": "payments",
  "schema": { /* OpenAPI schema */ }
}

# Update schema
PUT /api/forgepulse/action-schemas/{id}
Body: { "schema": { /* updated OpenAPI schema */ } }

# Delete schema
DELETE /api/forgepulse/action-schemas/{id}

# Sync code-based schemas to DB
POST /api/forgepulse/action-schemas/sync
Body: { "classes": ["App\\Actions\\ProcessOrder"] }

# Import from external OpenAPI spec
POST /api/forgepulse/action-schemas/import
Body: {
  "source_url": "https://api.stripe.com/openapi",
  "name": "Stripe: Create Customer",
  "operation_id": "PostCustomers"
}
```

---

## Schema Priority System

### How It Works

When you request a schema for `App\Actions\ProcessOrder`:

```
1. Check DATABASE first
   ├─ Query: WHERE action_class = "App\Actions\ProcessOrder"
   │         AND is_active = true
   ├─ Found? → Return DB schema ✅
   └─ Not found? → Continue to step 2

2. Check CODE (ProvidesSchema interface)
   ├─ Does class have schema() method?
   ├─ Found? → Return code-based schema
   └─ Not found? → Continue to step 3

3. Use REFLECTION
   ├─ Analyze method signature
   ├─ Extract parameter types
   └─ Generate basic schema
```

### Priority Benefits

✅ **Database First** - Runtime updates without deploys
✅ **Code Fallback** - Version control for schemas
✅ **Reflection Safety Net** - Works with any existing code
✅ **Override Anything** - DB beats code beats reflection

---

## Database Schema

### action_schemas Table Structure

```
┌─────────────────────────────────────────────────────────────┐
│ IDENTIFICATION                                               │
├─────────────────────────────────────────────────────────────┤
│ id                  BIGINT                                   │
│ action_class        VARCHAR     "App\\Actions\\ProcessOrder"│
│ action_type         VARCHAR     "internal" | "external"     │
│ method              VARCHAR     "handle" | "__invoke" | null│
│ is_external         BOOLEAN     false (PHP) | true (HTTP)   │
│ is_active           BOOLEAN     Enable/disable              │
├─────────────────────────────────────────────────────────────┤
│ METADATA                                                     │
├─────────────────────────────────────────────────────────────┤
│ name                VARCHAR     "Process Customer Order"     │
│ description         TEXT        Full description             │
│ category            VARCHAR     "orders" | "payments"        │
│ tags                JSON        ["checkout", "payment"]      │
├─────────────────────────────────────────────────────────────┤
│ SOURCE TRACKING                                              │
├─────────────────────────────────────────────────────────────┤
│ source              VARCHAR     "code" | "manual" | "..."   │
│ version             INT         Schema version number        │
├─────────────────────────────────────────────────────────────┤
│ OPENAPI SCHEMA (JSON FIELD)                                 │
├─────────────────────────────────────────────────────────────┤
│ schema              JSON        Complete OpenAPI 3.0 schema  │
│   ├─ summary                    Short description            │
│   ├─ description                Full description             │
│   ├─ operationId                Unique identifier            │
│   ├─ parameters                 Input schema (JSON Schema)   │
│   │   ├─ customerId                                          │
│   │   │   ├─ type: "integer"                                │
│   │   │   ├─ required: true                                 │
│   │   │   ├─ minimum: 1                                     │
│   │   │   └─ x-source: "context"                            │
│   │   └─ items                                               │
│   │       ├─ type: "array"                                  │
│   │       └─ minItems: 1                                    │
│   ├─ responses                  Output schema                │
│   │   └─ 200                                                 │
│   │       └─ content                                         │
│   │           └─ application/json                            │
│   │               └─ schema                                  │
│   │                   └─ properties                          │
│   │                       ├─ order_id: { type: "integer" }  │
│   │                       └─ total: { type: "number" }      │
│   └─ x-* (custom)               Workflow-specific metadata  │
│       ├─ x-timeout: 30                                       │
│       ├─ x-php-class                                         │
│       └─ x-http-endpoint                                     │
├─────────────────────────────────────────────────────────────┤
│ ADDITIONAL CONFIG (JSON FIELD)                              │
├─────────────────────────────────────────────────────────────┤
│ config              JSON        Execution configuration      │
│   ├─ timeout                    Execution timeout            │
│   ├─ queue                      Laravel queue name           │
│   ├─ max_retries                Retry count                  │
│   ├─ endpoint                   API URL (external only)      │
│   ├─ method                     HTTP method (external only)  │
│   └─ auth                       Auth type (external only)    │
├─────────────────────────────────────────────────────────────┤
│ TIMESTAMPS                                                   │
├─────────────────────────────────────────────────────────────┤
│ created_at          TIMESTAMP                                │
│ updated_at          TIMESTAMP                                │
│ deleted_at          TIMESTAMP   Soft delete                  │
└─────────────────────────────────────────────────────────────┘
```

---

## Usage Examples

### 1. Self-Describing Action (Code-Based)

```php
namespace App\Actions;

use AlizHarb\ForgePulse\Contracts\ProvidesSchema;

class ProcessOrderService implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'summary' => 'Process Customer Order',
            'description' => 'Validates and processes orders',
            'operationId' => 'processOrder',
            'tags' => ['orders'],
            
            'parameters' => [
                'customerId' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                    'x-source' => 'context',
                ],
                'items' => [
                    'type' => 'array',
                    'required' => true,
                    'minItems' => 1,
                ],
                'taxRate' => [
                    'type' => 'number',
                    'default' => 0.0,
                    'minimum' => 0,
                    'maximum' => 1,
                ],
            ],
            
            'responses' => [
                '200' => [
                    'description' => 'Order processed',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'order_id' => ['type' => 'integer'],
                                    'total' => ['type' => 'number'],
                                    'status' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            
            'x-timeout' => 30,
        ];
    }
    
    public function handle(int $customerId, array $items, float $taxRate = 0.0): array
    {
        // Implementation
        return ['order_id' => 123, 'total' => 99.99, 'status' => 'pending'];
    }
}
```

### 2. External Service (Database-Stored)

```php
use AlizHarb\ForgePulse\Models\ActionSchema;

// Create Stripe charge schema
ActionSchema::create([
    'action_class' => 'Stripe::Charge',
    'action_type' => 'external',
    'is_external' => true,
    'name' => 'Stripe: Create Charge',
    'category' => 'payments',
    'tags' => ['stripe', 'payment'],
    
    'schema' => [
        'summary' => 'Create Stripe Charge',
        'operationId' => 'createStripeCharge',
        
        'parameters' => [
            'amount' => [
                'type' => 'integer',
                'required' => true,
                'minimum' => 50,
                'description' => 'Amount in cents',
            ],
            'payment_method' => [
                'type' => 'string',
                'required' => true,
                'pattern' => '^pm_[a-zA-Z0-9]+$',
            ],
        ],
        
        'responses' => [
            '200' => [
                'description' => 'Charge created',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    
    'config' => [
        'endpoint' => 'https://api.stripe.com/v1/charges',
        'method' => 'POST',
        'auth' => 'bearer',
        'timeout' => 30,
    ],
]);
```

### 3. Using in Workflow

```php
use AlizHarb\ForgePulse\Models\Workflow;

$workflow = Workflow::create([
    'name' => 'Order Processing',
    'status' => 'active',
]);

// Internal action step
$workflow->steps()->create([
    'name' => 'Process Order',
    'type' => 'action',
    'order' => 1,
    'configuration' => [
        'class' => 'App\\Actions\\ProcessOrderService',
        'parameters' => [
            'customerId' => '{{customer.id}}',
            'items' => [['product_id' => 1, 'quantity' => 2, 'price' => 29.99]],
            'taxRate' => 0.08,
        ],
    ],
]);

// External API step
$workflow->steps()->create([
    'name' => 'Charge Payment',
    'type' => 'action',
    'order' => 2,
    'configuration' => [
        'class' => 'Stripe::Charge',
        'parameters' => [
            'amount' => 2000,
            'payment_method' => '{{payment.method_id}}',
        ],
    ],
]);

// Execute
$workflow->execute(['customer' => ['id' => 123], ...]);
```

### 4. Sync Code to Database

```php
// Sync internal actions to database
Http::post('/api/forgepulse/action-schemas/sync', [
    'classes' => [
        'App\\Actions\\ProcessOrderService',
        'App\\Actions\\SendEmailNotification',
    ],
]);
```

### 5. Import External OpenAPI Spec

```php
// Import from Stripe's public OpenAPI spec
Http::post('/api/forgepulse/action-schemas/import', [
    'source_url' => 'https://raw.githubusercontent.com/stripe/openapi/master/openapi/spec3.json',
    'name' => 'Stripe: Create Customer',
    'category' => 'payments',
    'operation_id' => 'PostCustomers',
]);
```

### 6. Validate Parameters

```php
// Validate before execution
$result = Http::post('/api/forgepulse/actions/validate', [
    'class' => 'App\\Actions\\ProcessOrderService',
    'parameters' => [
        'customerId' => 123,
        'items' => [['product_id' => 1, 'quantity' => 2, 'price' => 29.99]],
        'taxRate' => 0.08,
    ],
])->json();

if (!$result['valid']) {
    // $result['errors'] contains validation errors
}
```

### 7. Get Schema for UI

```php
// Get schema to build dynamic form
$schema = Http::get('/api/forgepulse/actions/schema', [
    'class' => 'App\\Actions\\ProcessOrderService',
])->json()['data'];

// Build form fields from schema
foreach ($schema['parameters'] as $param) {
    renderFormField([
        'name' => $param['name'],
        'type' => $param['type'],
        'required' => $param['required'],
        'description' => $param['description'],
        'example' => $param['example'],
    ]);
}
```

### 8. Export OpenAPI Spec

```php
// Generate complete OpenAPI 3.0 spec
$spec = Http::post('/api/forgepulse/actions/openapi', [
    'classes' => [
        'App\\Actions\\ProcessOrderService',
        'App\\Actions\\SendEmailNotification',
    ],
])->json();

// Save to file
file_put_contents('openapi.json', json_encode($spec, JSON_PRETTY_PRINT));

// Import to Postman, Swagger UI, generate TypeScript types, etc.
```

---

## Benefits

### For Developers

✅ **Zero Setup** - Existing code works via reflection
✅ **Standard Format** - OpenAPI (everyone knows it)
✅ **Type Safety** - JSON Schema validation
✅ **Version Control** - Schemas in code or DB
✅ **Testing** - Validate schemas in tests

### For Product Teams

✅ **Runtime Updates** - Change schemas without deploys
✅ **External Services** - Document Stripe, Twilio, etc.
✅ **Visibility** - See all available actions
✅ **Governance** - Audit trail of changes
✅ **Discovery** - Search by category/tag

### For Frontend Teams

✅ **Dynamic Forms** - Generate UI from schemas
✅ **Type Generation** - Create TypeScript types
✅ **API Client** - Generate client SDKs
✅ **Documentation** - Swagger UI for testing
✅ **Validation** - Validate before API calls

### For Operations

✅ **No Deploys** - Update schemas at runtime
✅ **Monitoring** - Track schema usage
✅ **A/B Testing** - Different schemas per tenant
✅ **Rollback** - Instant schema versioning
✅ **Compliance** - Audit who changed what

---

## Migration Path

### Phase 1: Keep Existing Code ✅ **Zero Risk**

**Nothing breaks. All existing code continues to work.**

```php
// Existing actions work unchanged
class ProcessOrderService {
    public function handle(int $customerId, array $items): array {
        // Implementation
    }
}

// Schema auto-detected via reflection
```

### Phase 2: Add OpenAPI Schemas (Optional)

**Enhance specific actions with rich metadata.**

```php
class ProcessOrderService implements ProvidesSchema {
    public static function schema(): array {
        return [
            'summary' => 'Process Order',
            'parameters' => [ /* detailed schema */ ],
            'responses' => [ /* response schema */ ],
        ];
    }
    
    public function handle(int $customerId, array $items): array {
        // Implementation unchanged
    }
}
```

### Phase 3: Sync to Database

**Populate database for runtime management.**

```bash
POST /api/forgepulse/action-schemas/sync
Body: { "classes": ["App\\Actions\\ProcessOrderService"] }
```

### Phase 4: Add External Services

**Document third-party APIs.**

```php
ActionSchema::create([
    'action_class' => 'Stripe::Charge',
    'is_external' => true,
    'schema' => [ /* Stripe API schema */ ],
]);
```

### Phase 5: Runtime Management

**Override schemas in database as needed.**

```php
$schema = ActionSchema::find(1);
$schema->update(['schema' => $updatedSchema]);
// Takes effect immediately, no deploy!
```

---

## Comparison: Before vs After

### Before This Implementation

```
┌─────────────────────────────────────────────────────────────┐
│ PROBLEMS                                                     │
├─────────────────────────────────────────────────────────────┤
│ ❌ No way to know what parameters an action needs           │
│ ❌ No documentation for action outputs                      │
│ ❌ Can't manage external API schemas (Stripe, Twilio)       │
│ ❌ UI can't generate forms dynamically                      │
│ ❌ Need code deploys to change schemas                      │
│ ❌ No validation before execution                           │
│ ❌ No discovery mechanism                                   │
│ ❌ Custom format (reinventing the wheel)                    │
└─────────────────────────────────────────────────────────────┘
```

### After This Implementation

```
┌─────────────────────────────────────────────────────────────┐
│ SOLUTIONS                                                    │
├─────────────────────────────────────────────────────────────┤
│ ✅ Three-tier discovery (DB → Code → Reflection)           │
│ ✅ OpenAPI 3.0 format (industry standard)                  │
│ ✅ Database storage for runtime flexibility                │
│ ✅ Internal + external actions unified                     │
│ ✅ JSON Schema validation built-in                         │
│ ✅ Dynamic form generation                                 │
│ ✅ Swagger UI integration                                  │
│ ✅ TypeScript type generation                              │
│ ✅ Import from external OpenAPI specs                      │
│ ✅ Version control & rollback                              │
│ ✅ Complete CRUD API                                        │
│ ✅ Zero setup (reflection fallback)                        │
└─────────────────────────────────────────────────────────────┘
```

---

## Files Created/Modified

### New Files Created

**Services:**
- `/src/Services/ActionSchema.php` - Schema discovery service
- `/src/Services/SchemaValidator.php` - JSON Schema validator

**Models:**
- `/src/Models/ActionSchema.php` - Database model

**Controllers:**
- `/src/Http/Controllers/Api/ActionSchemaController.php` - Discovery endpoints
- `/src/Http/Controllers/Api/ActionSchemaManagementController.php` - CRUD endpoints

**Contracts:**
- `/src/Contracts/ProvidesSchema.php` - Interface for self-describing actions

**Attributes (Optional):**
- `/src/Attributes/WorkflowAction.php` - Class/method metadata
- `/src/Attributes/WorkflowParameter.php` - Parameter metadata
- `/src/Attributes/WorkflowOutput.php` - Output field metadata

**Database:**
- `/database/migrations/2025_12_20_000004_create_action_schemas_table.php`
- `/database/factories/ActionSchemaFactory.php`

**Examples:**
- `/examples/openapi-action-examples.php` - OpenAPI examples
- `/examples/openapi-complete-guide.php` - Complete guide
- `/examples/action-schema-usage.php` - Usage examples
- `/examples/external-service-schemas.php` - External API examples
- `/examples/internal-schema-in-database.php` - Internal schema examples
- `/examples/internal-vs-external-comparison.php` - Comparison
- `/examples/swagger-ui-integration.php` - Swagger UI setup
- `/examples/database-schemas-complete-guide.php` - DB guide
- `/examples/internal-schema-visual-guide.md` - Visual guide

### Files Modified

- `/routes/api.php` - Added new API endpoints
- `/src/Services/StepHandlers/ActionHandler.php` - Already supported flexible actions

---

## Quick Start

### 1. Run Migration

```bash
php artisan migrate
```

### 2. Create Your First Action

```php
namespace App\Actions;

use AlizHarb\ForgePulse\Contracts\ProvidesSchema;

class ProcessOrderService implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'summary' => 'Process Order',
            'parameters' => [
                'customerId' => ['type' => 'integer', 'required' => true],
                'items' => ['type' => 'array', 'required' => true],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Order processed',
                    'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'properties' => [
                            'order_id' => ['type' => 'integer'],
                        ],
                    ]]],
                ],
            ],
        ];
    }
    
    public function handle(int $customerId, array $items): array
    {
        return ['order_id' => 123];
    }
}
```

### 3. Use in Workflow

```php
$workflow = Workflow::create(['name' => 'Test', 'status' => 'active']);

$workflow->steps()->create([
    'name' => 'Process Order',
    'type' => 'action',
    'configuration' => [
        'class' => 'App\\Actions\\ProcessOrderService',
        'parameters' => [
            'customerId' => 123,
            'items' => [['product_id' => 1, 'quantity' => 2]],
        ],
    ],
]);

$workflow->execute();
```

### 4. Get Schema via API

```bash
curl http://localhost/api/forgepulse/actions/schema?class=App\\Actions\\ProcessOrderService
```

---

## Testing

### Example Test

```php
test('ProcessOrderService has correct schema', function () {
    $schemaService = app(\AlizHarb\ForgePulse\Services\ActionSchema::class);
    $schema = $schemaService->getSchema(\App\Actions\ProcessOrderService::class);
    
    expect($schema)
        ->toHaveKey('parameters')
        ->and($schema['parameters'])
        ->toHaveCount(2)
        ->and($schema['parameters'][0]['name'])
        ->toBe('customerId')
        ->and($schema['parameters'][0]['required'])
        ->toBeTrue();
});

test('validates parameters correctly', function () {
    $validator = app(\AlizHarb\ForgePulse\Services\SchemaValidator::class);
    $schema = app(\AlizHarb\ForgePulse\Services\ActionSchema::class)
        ->getSchema(\App\Actions\ProcessOrderService::class);
    
    $result = $validator->validate(
        ['customerId' => 123, 'items' => []],
        $schema['parameters']
    );
    
    expect($result['valid'])->toBeTrue();
});
```

---

## Summary

### What Was Built

A **complete action schema system** that:

1. ✅ Uses **OpenAPI 3.0** (industry standard)
2. ✅ Stores schemas in **database** (runtime flexibility)
3. ✅ Falls back to **code** and **reflection** (zero setup)
4. ✅ Manages **internal PHP actions**
5. ✅ Manages **external APIs** (Stripe, Twilio, etc.)
6. ✅ Provides **JSON Schema validation**
7. ✅ Exports **complete OpenAPI specs**
8. ✅ Supports **Swagger UI** integration
9. ✅ Generates **TypeScript types**
10. ✅ Enables **dynamic UI forms**

### Key Innovations

🎯 **Three-Tier Priority** - DB → Code → Reflection
🎯 **Unified Format** - Same OpenAPI for internal & external
🎯 **Runtime Management** - Update without deploys
🎯 **Zero Setup** - Works with existing code
🎯 **Future-Proof** - Standard format with rich tooling

### Result

Your workflow engine can now:
- ✅ Document any action (internal or external)
- ✅ Validate parameters before execution
- ✅ Generate UI forms dynamically
- ✅ Export to Swagger/Postman/TypeScript
- ✅ Manage schemas at runtime
- ✅ Track changes with versioning

**All with industry-standard OpenAPI 3.0 format!** 🚀

---

**End of Implementation Summary**
