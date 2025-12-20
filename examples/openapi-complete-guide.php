<?php

/**
 * ===============================================
 * OpenAPI Schema Discovery - Complete Guide
 * ===============================================
 *
 * Why OpenAPI instead of custom format?
 */

// ============================================
// WHY OPENAPI? ✅
// ============================================

/*
1. INDUSTRY STANDARD
   - Everyone knows OpenAPI/Swagger
   - No proprietary format to learn
   - Compatible with existing tools

2. RICH ECOSYSTEM
   - Swagger UI (interactive docs)
   - Postman (API testing)
   - OpenAPI Generator (client SDKs)
   - JSON Schema validators
   - API gateways support it

3. VALIDATION BUILT-IN
   - JSON Schema validation
   - Type checking
   - Enum validation
   - Pattern matching
   - Range constraints

4. TOOLING
   - Generate TypeScript types
   - Generate API clients
   - Generate test fixtures
   - Lint API specs
   - Track breaking changes

5. INTEROPERABILITY
   - Share with frontend teams
   - Import into API tools
   - Use with AWS API Gateway
   - Integrate with monitoring
*/

// ============================================
// QUICK START
// ============================================

use AlizHarb\ForgePulse\Contracts\ProvidesSchema;

// 1. Define action with OpenAPI schema
class ProcessOrderService implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'summary' => 'Process Customer Order',
            'description' => 'Validates and processes customer orders',
            'tags' => ['orders'],

            // Input parameters (JSON Schema)
            'parameters' => [
                'customerId' => [
                    'type' => 'integer',
                    'description' => 'Customer ID',
                    'required' => true,
                    'minimum' => 1,
                    'x-source' => 'context', // Custom extension
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

            // Output response (OpenAPI response schema)
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
                                    'status' => ['type' => 'string', 'enum' => ['pending', 'confirmed']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function handle(int $customerId, array $items, float $taxRate = 0.0): array
    {
        // Implementation
        return ['order_id' => 123, 'total' => 99.99, 'status' => 'pending'];
    }
}

// 2. Get schema via API
$response = Http::get('/api/forgepulse/actions/schema', [
    'class' => 'App\Actions\ProcessOrderService',
]);

// 3. Validate parameters
$response = Http::post('/api/forgepulse/actions/validate', [
    'class' => 'App\Actions\ProcessOrderService',
    'parameters' => [
        'customerId' => 123,
        'items' => [['product_id' => 1, 'quantity' => 2]],
        'taxRate' => 0.08,
    ],
]);

// Returns: { "valid": true, "errors": [] }

// 4. Export as OpenAPI spec
$response = Http::post('/api/forgepulse/actions/openapi', [
    'classes' => [
        'App\Actions\ProcessOrderService',
        'App\Actions\SendEmailNotification',
    ],
]);

// Returns complete OpenAPI 3.0 spec

// 5. View in Swagger UI
// Visit: /docs/actions

// ============================================
// API ENDPOINTS
// ============================================

/*
GET /api/forgepulse/actions/schema
  ?class=App\Actions\ProcessOrder
  ?method=handle
→ Returns schema for single action

POST /api/forgepulse/actions/schema/bulk
  Body: { "classes": ["App\\Actions\\ProcessOrder", ...] }
→ Returns schemas for multiple actions

POST /api/forgepulse/actions/openapi
  Body: { "classes": ["App\\Actions\\ProcessOrder", ...] }
→ Returns complete OpenAPI 3.0 spec

POST /api/forgepulse/actions/validate
  Body: { "class": "App\\Actions\\ProcessOrder", "parameters": {...} }
→ Validates parameters against schema
*/

// ============================================
// THREE APPROACHES
// ============================================

// 1. Pure Reflection (no setup)
class SimpleService
{
    public function handle(int $id, string $name): array
    {
        return ['success' => true];
    }
}
// Schema auto-generated from signature

// 2. Self-describing with OpenAPI (recommended)
class DocumentedService implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'summary' => 'My Service',
            'parameters' => [
                'id' => ['type' => 'integer', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Success',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => ['success' => ['type' => 'boolean']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function handle(int $id, string $name): array
    {
        return ['success' => true];
    }
}

// 3. Attributes (optional, for metadata)
use AlizHarb\ForgePulse\Attributes\WorkflowAction;

#[WorkflowAction(name: 'My Service', category: 'general')]
class AttributeService
{
    public function handle(int $id): array
    {
        return ['success' => true];
    }
}

// ============================================
// VALIDATION EXAMPLES
// ============================================

// JSON Schema validation rules supported:
return [
    'parameters' => [
        'email' => [
            'type' => 'string',
            'format' => 'email', // Built-in format
            'required' => true,
        ],
        'age' => [
            'type' => 'integer',
            'minimum' => 18,
            'maximum' => 120,
        ],
        'password' => [
            'type' => 'string',
            'minLength' => 8,
            'maxLength' => 100,
            'pattern' => '^(?=.*[A-Z])(?=.*[0-9])', // Regex
        ],
        'tags' => [
            'type' => 'array',
            'minItems' => 1,
            'maxItems' => 10,
        ],
        'status' => [
            'type' => 'string',
            'enum' => ['draft', 'published', 'archived'], // Allowed values
        ],
    ],
];

// ============================================
// SWAGGER UI INTEGRATION
// ============================================

// Step 1: Create route (routes/web.php)
Route::get('/docs/actions', function () {
    return view('forgepulse::swagger-ui');
});

// Step 2: Create view with Swagger UI (resources/views/swagger-ui.blade.php)
// See examples/swagger-ui-integration.php

// Step 3: Configure action classes (config/forgepulse.php)
return [
    'actions' => [
        'classes' => [
            \App\Actions\ProcessOrderService::class,
            \App\Actions\SendEmailNotification::class,
        ],
    ],
];

// Step 4: Visit /docs/actions in browser

// ============================================
// GENERATE TYPESCRIPT TYPES
// ============================================

// 1. Export OpenAPI spec
Http::post('/api/forgepulse/actions/openapi', [
    'classes' => $allActionClasses,
])->saveAs('openapi.json');

// 2. Generate TypeScript
// npm install -g @openapitools/openapi-generator-cli
// openapi-generator-cli generate -i openapi.json -g typescript-axios -o ./src/api

// 3. Use in frontend
/*
import { ProcessOrderService } from './api';

const api = new ProcessOrderService();
const result = await api.processOrder({
  customerId: 123,
  items: [{ product_id: 1, quantity: 2 }],
  taxRate: 0.08
});
// TypeScript knows the structure!
*/

// ============================================
// COMPARISON: CUSTOM vs OPENAPI
// ============================================

/*
┌─────────────────────┬────────────────┬──────────────┐
│ Feature             │ Custom Format  │ OpenAPI      │
├─────────────────────┼────────────────┼──────────────┤
│ Learning Curve      │ Must learn new │ Already know │
│ Tooling             │ Build yourself │ Vast ecoystem│
│ Validation          │ Manual         │ Built-in     │
│ Type Generation     │ Build yourself │ 30+ langs    │
│ Industry Support    │ None           │ Everyone     │
│ Documentation       │ Build yourself │ Swagger UI   │
│ Interoperability    │ Limited        │ Universal    │
│ Future-proof        │ Risky          │ Standard     │
└─────────────────────┴────────────────┴──────────────┘

VERDICT: OpenAPI wins decisively
*/

// ============================================
// BEST PRACTICES
// ============================================

/*
1. START SIMPLE
   - Use pure reflection for simple services
   - Add OpenAPI schema when you need docs

2. BE SPECIFIC
   - Add validation rules (min, max, pattern)
   - Use enums for fixed values
   - Add examples for clarity

3. DOCUMENT OUTPUTS
   - Define response schemas
   - List all possible fields
   - Include descriptions

4. USE EXTENSIONS
   - x-source for context vs config
   - x-timeout for recommendations
   - x-category for grouping

5. AUTOMATE
   - Generate OpenAPI specs in CI/CD
   - Validate schemas in tests
   - Track breaking changes
*/

// ============================================
// SUMMARY
// ============================================

/*
✅ OPENAPI IS THE RIGHT CHOICE BECAUSE:

1. Everyone knows it
2. Vast tooling ecosystem
3. Built-in validation
4. Generate clients automatically
5. Swagger UI for interactive docs
6. Works with Postman, gateways, etc.
7. Future-proof standard
8. No vendor lock-in

THE IMPLEMENTATION PROVIDES:

1. ActionSchema service (reads OpenAPI)
2. SchemaValidator (JSON Schema validation)
3. API endpoints (schema, validate, export)
4. Swagger UI integration
5. OpenAPI export
6. Backward compatible (attributes still work)
7. Reflection fallback (zero setup)

YOU CAN NOW:

✅ Document actions with industry standard
✅ Validate parameters automatically
✅ Generate TypeScript types
✅ Import into Postman
✅ Use Swagger UI
✅ Share specs with teams
✅ Build with confidence
*/
