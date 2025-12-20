# ForgePulse Action Schema - Quick Reference

## 🎯 Problem Solved
"How do I know what parameters my actions need and what they return?"

## ✅ Solution
OpenAPI 3.0 schemas stored in database with code/reflection fallback

---

## 📋 Quick Start (3 Steps)

### 1. Run Migration
```bash
php artisan migrate
```

### 2. Create Action with Schema
```php
use AlizHarb\ForgePulse\Contracts\ProvidesSchema;

class ProcessOrder implements ProvidesSchema {
    public static function schema(): array {
        return [
            'summary' => 'Process Order',
            'parameters' => [
                'customerId' => ['type' => 'integer', 'required' => true],
            ],
            'responses' => [
                '200' => ['description' => 'Success'],
            ],
        ];
    }
    
    public function handle(int $customerId): array {
        return ['order_id' => 123];
    }
}
```

### 3. Use in Workflow
```php
$workflow->steps()->create([
    'type' => 'action',
    'configuration' => [
        'class' => 'App\\Actions\\ProcessOrder',
        'parameters' => ['customerId' => 123],
    ],
]);
```

---

## 🔑 Key Concepts

### Schema Priority
```
1. DATABASE (runtime updates) ✅
   ↓ not found?
2. CODE (version controlled) ✅
   ↓ not found?
3. REFLECTION (auto-detect) ✅
```

### Action Types
- **Internal**: Your PHP code
- **External**: Stripe, Twilio, etc.

---

## 🌐 API Endpoints

```bash
# Get schema
GET /api/forgepulse/actions/schema?class=App\Actions\ProcessOrder

# Validate parameters
POST /api/forgepulse/actions/validate
{"class": "App\\Actions\\ProcessOrder", "parameters": {...}}

# Export OpenAPI
POST /api/forgepulse/actions/openapi
{"classes": ["App\\Actions\\ProcessOrder"]}

# List all schemas
GET /api/forgepulse/action-schemas

# Create schema (for external APIs)
POST /api/forgepulse/action-schemas
{"action_class": "Stripe::Charge", "is_external": true, ...}

# Sync code to DB
POST /api/forgepulse/action-schemas/sync
{"classes": ["App\\Actions\\ProcessOrder"]}
```

---

## 💾 Database Schema

```sql
action_schemas
├─ action_class      "App\\Actions\\ProcessOrder"
├─ action_type       "internal" | "external"
├─ is_external       false (PHP) | true (HTTP)
├─ schema            JSON (OpenAPI 3.0)
├─ name              "Process Order"
├─ category          "orders"
└─ config            JSON (timeout, queue, endpoint, etc.)
```

---

## 📝 OpenAPI Format

```json
{
  "summary": "Process Order",
  "parameters": {
    "customerId": {
      "type": "integer",
      "required": true,
      "minimum": 1,
      "x-source": "context"
    }
  },
  "responses": {
    "200": {
      "description": "Success",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "properties": {
              "order_id": {"type": "integer"}
            }
          }
        }
      }
    }
  },
  "x-timeout": 30
}
```

---

## 🎨 Three Ways to Define Schemas

### 1. Pure Reflection (Zero Setup)
```php
class UpdateInventory {
    public function handle(int $productId, int $quantity): array {
        return ['success' => true];
    }
}
// Schema auto-generated from signature ✅
```

### 2. Code-Based (Recommended)
```php
class ProcessOrder implements ProvidesSchema {
    public static function schema(): array {
        return [ /* OpenAPI schema */ ];
    }
    
    public function handle(...) { }
}
// Version controlled, self-documented ✅
```

### 3. Database-Stored (Runtime Management)
```php
ActionSchema::create([
    'action_class' => 'Stripe::Charge',
    'is_external' => true,
    'schema' => [ /* OpenAPI schema */ ],
]);
// Runtime updates, external services ✅
```

---

## 🔥 Common Use Cases

### External API (Stripe)
```php
ActionSchema::create([
    'action_class' => 'Stripe::Charge',
    'is_external' => true,
    'schema' => [
        'parameters' => [
            'amount' => ['type' => 'integer', 'minimum' => 50],
            'payment_method' => ['type' => 'string'],
        ],
    ],
    'config' => [
        'endpoint' => 'https://api.stripe.com/v1/charges',
        'method' => 'POST',
        'auth' => 'bearer',
    ],
]);
```

### Validate Before Execution
```php
$result = Http::post('/api/forgepulse/actions/validate', [
    'class' => 'App\\Actions\\ProcessOrder',
    'parameters' => ['customerId' => 123],
]);

if (!$result['valid']) {
    // Handle errors: $result['errors']
}
```

### Dynamic UI Form
```php
$schema = Http::get('/api/forgepulse/actions/schema', [
    'class' => 'App\\Actions\\ProcessOrder',
])->json()['data'];

foreach ($schema['parameters'] as $param) {
    renderField($param['name'], $param['type'], $param['required']);
}
```

---

## 🛠️ Validation Rules

```php
'parameters' => [
    'email' => [
        'type' => 'string',
        'format' => 'email',
        'required' => true,
    ],
    'age' => [
        'type' => 'integer',
        'minimum' => 18,
        'maximum' => 120,
    ],
    'status' => [
        'type' => 'string',
        'enum' => ['active', 'inactive'],
    ],
    'tags' => [
        'type' => 'array',
        'minItems' => 1,
        'maxItems' => 10,
    ],
]
```

---

## 🎁 Benefits

| Stakeholder | Benefits |
|-------------|----------|
| **Developers** | Zero setup, standard format, type safety |
| **Product** | Runtime updates, no deploys, versioning |
| **Frontend** | Dynamic forms, TypeScript types, validation |
| **Ops** | Monitoring, A/B testing, instant rollback |

---

## 📊 Comparison

### Before
- ❌ No schema discovery
- ❌ Manual documentation
- ❌ Hard-coded forms
- ❌ Code deploys for changes
- ❌ Custom format

### After
- ✅ Automatic discovery
- ✅ OpenAPI standard
- ✅ Dynamic forms
- ✅ Runtime updates
- ✅ Rich tooling (Swagger, Postman)

---

## 🚀 Advanced Features

### Swagger UI
```php
// Visit /docs/actions for interactive documentation
```

### Export OpenAPI
```bash
POST /api/forgepulse/actions/openapi
# Import to Postman, generate TypeScript types
```

### Version Management
```php
$schema->createVersion($newSchema);
// Keep multiple versions, gradual migration
```

### Import External Specs
```php
POST /api/forgepulse/action-schemas/import
{
  "source_url": "https://api.stripe.com/openapi",
  "operation_id": "PostCustomers"
}
```

---

## 📚 Learn More

- Full implementation: `/workspace/IMPLEMENTATION-SUMMARY.md`
- OpenAPI guide: `/workspace/examples/openapi-complete-guide.php`
- External APIs: `/workspace/examples/external-service-schemas.php`
- Internal vs External: `/workspace/examples/internal-vs-external-comparison.php`

---

## 💡 Remember

**Three-tier priority:**
1. **DB** - Runtime flexibility
2. **Code** - Version control
3. **Reflection** - Zero setup

**Best of all worlds!** 🎉
