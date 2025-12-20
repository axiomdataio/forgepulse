# Internal Action Schema in Database - Visual Guide

## Complete Database Record Structure

```
┌─────────────────────────────────────────────────────────────────────┐
│ action_schemas Table                                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│ IDENTIFICATION                                                        │
│ ├─ id: 1                                                             │
│ ├─ action_class: "App\\Actions\\ProcessOrderService"                │
│ ├─ action_type: "internal"                                           │
│ ├─ method: "handle"                                                  │
│ ├─ is_external: false                                                │
│ └─ is_active: true                                                   │
│                                                                       │
│ METADATA                                                              │
│ ├─ name: "Process Customer Order"                                    │
│ ├─ description: "Validates order items and creates order"            │
│ ├─ category: "orders"                                                │
│ └─ tags: ["orders", "checkout", "payment"]                           │
│                                                                       │
│ SOURCE TRACKING                                                       │
│ ├─ source: "code"  (code|manual|imported|generated)                 │
│ └─ version: 1                                                        │
│                                                                       │
│ OPENAPI SCHEMA (JSON field) ─────────────────────────────────┐      │
│ │                                                              │      │
│ │  {                                                           │      │
│ │    "summary": "Process Customer Order",                     │      │
│ │    "description": "Full description...",                    │      │
│ │    "operationId": "processOrder",                           │      │
│ │    "tags": ["orders"],                                      │      │
│ │                                                              │      │
│ │    "parameters": {                                          │      │
│ │      "customerId": {                                        │      │
│ │        "type": "integer",                                   │      │
│ │        "description": "Customer ID",                        │      │
│ │        "required": true,                                    │      │
│ │        "minimum": 1,                                        │      │
│ │        "example": 12345,                                    │      │
│ │        "x-source": "context",                               │      │
│ │        "x-variable": "{{customer.id}}"                      │      │
│ │      },                                                      │      │
│ │      "items": {                                             │      │
│ │        "type": "array",                                     │      │
│ │        "required": true,                                    │      │
│ │        "minItems": 1,                                       │      │
│ │        "items": {                                           │      │
│ │          "type": "object",                                  │      │
│ │          "properties": {                                    │      │
│ │            "product_id": {                                  │      │
│ │              "type": "integer",                             │      │
│ │              "minimum": 1                                   │      │
│ │            },                                                │      │
│ │            "quantity": {                                    │      │
│ │              "type": "integer",                             │      │
│ │              "minimum": 1,                                  │      │
│ │              "maximum": 999                                 │      │
│ │            },                                                │      │
│ │            "price": {                                       │      │
│ │              "type": "number",                              │      │
│ │              "minimum": 0                                   │      │
│ │            }                                                 │      │
│ │          }                                                   │      │
│ │        },                                                    │      │
│ │        "example": [                                         │      │
│ │          {"product_id": 101, "quantity": 2, "price": 29.99}│      │
│ │        ]                                                     │      │
│ │      },                                                      │      │
│ │      "taxRate": {                                           │      │
│ │        "type": "number",                                    │      │
│ │        "default": 0.0,                                      │      │
│ │        "minimum": 0,                                        │      │
│ │        "maximum": 1,                                        │      │
│ │        "example": 0.08                                      │      │
│ │      }                                                       │      │
│ │    },                                                        │      │
│ │                                                              │      │
│ │    "responses": {                                           │      │
│ │      "200": {                                               │      │
│ │        "description": "Order processed successfully",       │      │
│ │        "content": {                                         │      │
│ │          "application/json": {                              │      │
│ │            "schema": {                                      │      │
│ │              "type": "object",                              │      │
│ │              "properties": {                                │      │
│ │                "order_id": {                                │      │
│ │                  "type": "integer",                         │      │
│ │                  "description": "Created order ID",         │      │
│ │                  "example": 5678                            │      │
│ │                },                                            │      │
│ │                "subtotal": {                                │      │
│ │                  "type": "number",                          │      │
│ │                  "format": "float",                         │      │
│ │                  "example": 75.48                           │      │
│ │                },                                            │      │
│ │                "total": {                                   │      │
│ │                  "type": "number",                          │      │
│ │                  "example": 81.52                           │      │
│ │                },                                            │      │
│ │                "status": {                                  │      │
│ │                  "type": "string",                          │      │
│ │                  "enum": ["pending", "confirmed"],          │      │
│ │                  "example": "pending"                       │      │
│ │                }                                             │      │
│ │              },                                              │      │
│ │              "required": ["order_id", "total", "status"]    │      │
│ │            }                                                 │      │
│ │          }                                                   │      │
│ │        }                                                     │      │
│ │      }                                                       │      │
│ │    },                                                        │      │
│ │                                                              │      │
│ │    "x-timeout": 30,                                         │      │
│ │    "x-retries": 3,                                          │      │
│ │    "x-category": "orders",                                  │      │
│ │    "x-php-class": "App\\Actions\\ProcessOrderService",     │      │
│ │    "x-php-method": "handle"                                 │      │
│ │  }                                                           │      │
│ └──────────────────────────────────────────────────────────────┘      │
│                                                                       │
│ CONFIG (JSON field)                                                   │
│ └─ {                                                                  │
│      "timeout": 30,                                                   │
│      "max_retries": 3,                                                │
│      "queue": "orders"                                                │
│    }                                                                  │
│                                                                       │
│ TIMESTAMPS                                                            │
│ ├─ created_at: "2025-12-20 10:00:00"                                 │
│ ├─ updated_at: "2025-12-20 10:00:00"                                 │
│ └─ deleted_at: null                                                   │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

## Schema Priority Flow

```
Workflow needs schema for "App\Actions\ProcessOrderService"
│
├─ 1. Check Database
│  │
│  ├─ Query: WHERE action_class = "App\Actions\ProcessOrderService"
│  │          AND is_active = true
│  │
│  ├─ Found? ✅
│  │  └─ Return schema from DB (priority!)
│  │
│  └─ Not found? ❌
│     └─ Continue to step 2...
│
├─ 2. Check Code (ProvidesSchema interface)
│  │
│  ├─ Does class have schema() method? ✅
│  │  └─ Return ProcessOrderService::schema()
│  │
│  └─ No schema() method? ❌
│     └─ Continue to step 3...
│
└─ 3. Use Reflection
   │
   └─ Analyze method signature
      └─ Generate basic schema from types
```

## Field Explanations

### Top-Level Fields
- **action_class**: PHP class name (e.g., `App\\Actions\\ProcessOrderService`)
- **action_type**: `internal`, `external`, `webhook`, or `api`
- **method**: PHP method name (`handle`, `__invoke`, `execute`, etc.)
- **is_external**: `false` for internal PHP actions
- **is_active**: Whether this schema is currently available
- **source**: How schema was created:
  - `code`: Synced from ProvidesSchema interface
  - `manual`: Created via API/UI
  - `imported`: Imported from external OpenAPI spec
  - `generated`: Auto-generated from reflection

### OpenAPI Schema Field

The `schema` JSON field contains standard OpenAPI 3.0 format:

**Standard OpenAPI Fields:**
- `summary`: Short description
- `description`: Full description
- `operationId`: Unique identifier
- `tags`: Array of tags for grouping
- `parameters`: Input parameters (JSON Schema)
- `responses`: Output responses (OpenAPI response objects)

**Custom Extensions (x-* fields):**
- `x-source`: Where parameter value comes from (`context` or `config`)
- `x-variable`: Suggested variable name (e.g., `{{customer.id}}`)
- `x-timeout`: Recommended timeout in seconds
- `x-retries`: Recommended retry count
- `x-category`: Action category
- `x-php-class`: Original PHP class
- `x-php-method`: Original PHP method

## JSON Schema Validation Rules

### Number Constraints
```json
{
  "type": "number",
  "minimum": 0,
  "maximum": 1000,
  "exclusiveMinimum": 0,
  "exclusiveMaximum": 1000,
  "multipleOf": 0.01
}
```

### String Constraints
```json
{
  "type": "string",
  "minLength": 1,
  "maxLength": 255,
  "pattern": "^[A-Z]{2}\\d{4}$",
  "format": "email|uri|date|date-time|uuid"
}
```

### Array Constraints
```json
{
  "type": "array",
  "minItems": 1,
  "maxItems": 100,
  "uniqueItems": true,
  "items": { "type": "string" }
}
```

### Enum Values
```json
{
  "type": "string",
  "enum": ["pending", "confirmed", "shipped"]
}
```

## Comparison: Internal vs External

```
┌────────────────────┬─────────────────────┬──────────────────────┐
│ Field              │ Internal Action     │ External API         │
├────────────────────┼─────────────────────┼──────────────────────┤
│ action_class       │ App\Actions\Process │ Stripe::Charge       │
│ action_type        │ internal            │ external             │
│ method             │ handle              │ null                 │
│ is_external        │ false               │ true                 │
│ source             │ code                │ manual/imported      │
│                    │                     │                      │
│ config             │ {                   │ {                    │
│                    │   "timeout": 30,    │   "endpoint": "..."  │
│                    │   "queue": "..."    │   "method": "POST",  │
│                    │ }                   │   "auth": "bearer"   │
│                    │                     │ }                    │
└────────────────────┴─────────────────────┴──────────────────────┘
```

## Benefits of This Structure

✅ **Standard OpenAPI** - Use industry standard format
✅ **Rich Validation** - JSON Schema with constraints
✅ **Custom Metadata** - x-* fields for workflow-specific data
✅ **Queryable** - Index by category, type, tags
✅ **Versionable** - Track changes over time
✅ **Exportable** - Generate full OpenAPI spec
✅ **Tooling** - Compatible with Swagger UI, Postman, etc.
