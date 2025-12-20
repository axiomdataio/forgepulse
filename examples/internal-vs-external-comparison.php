<?php

/**
 * ===============================================
 * Internal vs External Schemas - Side by Side
 * ===============================================
 */

use AlizHarb\ForgePulse\Models\ActionSchema;

// ============================================
// INTERNAL ACTION: ProcessOrderService
// ============================================

// Your PHP code
namespace App\Actions;

class ProcessOrderService
{
    public function handle(int $customerId, array $items, float $taxRate = 0.0): array
    {
        $subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $tax = $subtotal * $taxRate;

        return [
            'order_id' => 5678,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'status' => 'pending',
        ];
    }
}

// Database schema
$internalSchema = [
    // Metadata
    'action_class' => 'App\\Actions\\ProcessOrderService',
    'action_type' => 'internal',
    'method' => 'handle',
    'is_external' => false, // ← THIS IS KEY
    'is_active' => true,
    'source' => 'code',
    'name' => 'Process Customer Order',
    'category' => 'orders',

    // OpenAPI Schema
    'schema' => [
        'summary' => 'Process Customer Order',
        'operationId' => 'processOrder',
        'parameters' => [
            'customerId' => [
                'type' => 'integer',
                'required' => true,
                'x-source' => 'context', // From workflow context
            ],
            'items' => [
                'type' => 'array',
                'required' => true,
                'x-source' => 'config', // From step config
            ],
            'taxRate' => [
                'type' => 'number',
                'default' => 0.0,
                'x-source' => 'config',
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
        // Custom: PHP execution metadata
        'x-php-class' => 'App\\Actions\\ProcessOrderService',
        'x-php-method' => 'handle',
        'x-timeout' => 30,
    ],

    // Internal action config
    'config' => [
        'timeout' => 30,
        'queue' => 'orders',
        'max_retries' => 3,
    ],
];

// ============================================
// EXTERNAL API: Stripe Charge
// ============================================

// No PHP code - external service!

// Database schema
$externalSchema = [
    // Metadata
    'action_class' => 'Stripe::Charge', // Not a real PHP class!
    'action_type' => 'external',
    'method' => null, // No PHP method
    'is_external' => true, // ← THIS IS KEY
    'is_active' => true,
    'source' => 'manual', // or 'imported'
    'name' => 'Stripe: Create Charge',
    'category' => 'payments',

    // OpenAPI Schema (same format!)
    'schema' => [
        'summary' => 'Create Stripe Charge',
        'operationId' => 'createStripeCharge',
        'parameters' => [
            'amount' => [
                'type' => 'integer',
                'required' => true,
                'minimum' => 50,
                'description' => 'Amount in cents',
                'x-source' => 'config',
            ],
            'payment_method' => [
                'type' => 'string',
                'required' => true,
                'pattern' => '^pm_[a-zA-Z0-9]+$',
                'x-source' => 'context',
            ],
            'customer' => [
                'type' => 'string',
                'pattern' => '^cus_[a-zA-Z0-9]+$',
                'x-source' => 'context',
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
                                'id' => ['type' => 'string', 'example' => 'ch_123'],
                                'status' => ['type' => 'string', 'enum' => ['succeeded', 'pending']],
                                'amount' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        // Custom: HTTP call metadata
        'x-http-endpoint' => 'https://api.stripe.com/v1/charges',
        'x-http-method' => 'POST',
        'x-auth-type' => 'bearer',
    ],

    // External API config
    'config' => [
        'endpoint' => 'https://api.stripe.com/v1/charges',
        'method' => 'POST',
        'auth' => 'bearer',
        'timeout' => 30,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ],
];

// ============================================
// SIDE-BY-SIDE COMPARISON
// ============================================

/*
┌─────────────────────┬──────────────────────────┬──────────────────────────┐
│ Field               │ Internal Action          │ External API             │
├─────────────────────┼──────────────────────────┼──────────────────────────┤
│ action_class        │ App\Actions\ProcessOrder │ Stripe::Charge           │
│                     │ (real PHP class)         │ (just an identifier)     │
├─────────────────────┼──────────────────────────┼──────────────────────────┤
│ action_type         │ "internal"               │ "external"               │
├─────────────────────┼──────────────────────────┼──────────────────────────┤
│ method              │ "handle"                 │ null                     │
├─────────────────────┼──────────────────────────┼──────────────────────────┤
│ is_external         │ false ✅                 │ true ✅                  │
├─────────────────────┼──────────────────────────┼──────────────────────────┤
│ source              │ "code" (synced)          │ "manual" or "imported"   │
├─────────────────────┼──────────────────────────┼──────────────────────────┤
│                     │                          │                          │
│ schema.parameters   │ Same OpenAPI format ✅   │ Same OpenAPI format ✅   │
│ schema.responses    │ Same OpenAPI format ✅   │ Same OpenAPI format ✅   │
│                     │                          │                          │
├─────────────────────┼──────────────────────────┼──────────────────────────┤
│ schema.x-*          │ x-php-class              │ x-http-endpoint          │
│ (custom extensions) │ x-php-method             │ x-http-method            │
│                     │ x-timeout                │ x-auth-type              │
├─────────────────────┼──────────────────────────┼──────────────────────────┤
│                     │                          │                          │
│ config.timeout      │ PHP execution timeout    │ HTTP request timeout     │
│ config.queue        │ Laravel queue name       │ N/A                      │
│ config.endpoint     │ N/A                      │ API URL ✅               │
│ config.method       │ N/A                      │ HTTP method (POST) ✅    │
│ config.auth         │ N/A                      │ Auth type (bearer) ✅    │
│ config.headers      │ N/A                      │ HTTP headers ✅          │
│                     │                          │                          │
├─────────────────────┼──────────────────────────┼──────────────────────────┤
│ Execution           │ Call PHP class/method    │ Make HTTP request        │
│                     │ app(class)->method()     │ Http::post(endpoint)     │
└─────────────────────┴──────────────────────────┴──────────────────────────┘
*/

// ============================================
// HOW THEY'RE USED IN WORKFLOWS
// ============================================

// Internal action step
$workflow->steps()->create([
    'name' => 'Process Order',
    'type' => 'action',
    'configuration' => [
        'class' => 'App\\Actions\\ProcessOrderService', // ← Internal
        'mode' => 'sync',
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
    'configuration' => [
        'class' => 'Stripe::Charge', // ← External
        'mode' => 'sync',
        'parameters' => [
            'amount' => 2000,
            'payment_method' => '{{payment.method_id}}',
            'customer' => '{{customer.stripe_id}}',
        ],
    ],
]);

// ============================================
// EXECUTION FLOW COMPARISON
// ============================================

// INTERNAL ACTION EXECUTION:
/*
1. Get schema from DB (action_class = "App\Actions\ProcessOrderService")
2. Validate parameters against schema
3. Instantiate PHP class: app("App\Actions\ProcessOrderService")
4. Call method: $instance->handle($customerId, $items, $taxRate)
5. Return result
*/

// EXTERNAL API EXECUTION:
/*
1. Get schema from DB (action_class = "Stripe::Charge")
2. Validate parameters against schema
3. Get config (endpoint, auth, headers)
4. Make HTTP request:
   Http::withToken($apiKey)
       ->post("https://api.stripe.com/v1/charges", [
           'amount' => 2000,
           'payment_method' => 'pm_123',
       ])
5. Return response
*/

// ============================================
// HANDLER IMPLEMENTATION
// ============================================

class StepExecutor
{
    public function executeStep(WorkflowStep $step, array $context): array
    {
        $config = $step->configuration;
        $actionClass = $config['class'];

        // Get schema from database
        $schema = ActionSchema::where('action_class', $actionClass)
            ->where('is_active', true)
            ->first();

        if (! $schema) {
            throw new \Exception("Schema not found for {$actionClass}");
        }

        // Route based on is_external flag
        if ($schema->is_external) {
            return $this->executeExternalAction($schema, $config, $context);
        } else {
            return $this->executeInternalAction($schema, $config, $context);
        }
    }

    protected function executeInternalAction($schema, $config, $context): array
    {
        // Call PHP code
        $class = $schema->action_class;
        $method = $schema->method ?? 'handle';

        $instance = app($class);
        $params = $config['parameters'];

        return $instance->$method(...array_values($params));
    }

    protected function executeExternalAction($schema, $config, $context): array
    {
        // Make HTTP request
        $endpoint = $schema->config['endpoint'];
        $method = $schema->config['method'] ?? 'POST';
        $auth = $schema->config['auth'] ?? 'none';

        $http = Http::timeout($schema->config['timeout'] ?? 30);

        // Add authentication
        if ($auth === 'bearer') {
            $http = $http->withToken($this->getApiKey($schema));
        }

        // Make request
        $response = $http->send($method, $endpoint, [
            'json' => $config['parameters'],
        ]);

        return $response->json();
    }

    protected function getApiKey($schema): string
    {
        // Get API key from config based on service
        return match ($schema->category) {
            'payments' => config('services.stripe.secret'),
            'notifications' => config('services.twilio.token'),
            default => throw new \Exception('No API key configured'),
        };
    }
}

// ============================================
// QUERY EXAMPLES
// ============================================

// Get all internal actions
$internal = ActionSchema::where('is_external', false)
    ->where('is_active', true)
    ->get();

// Get all external APIs
$external = ActionSchema::where('is_external', true)
    ->where('is_active', true)
    ->get();

// Get payment-related actions (both internal and external)
$payments = ActionSchema::where('category', 'payments')
    ->where('is_active', true)
    ->get();
// Returns: ProcessOrderService (internal) + Stripe::Charge (external)

// Group by internal/external for UI
$actions = ActionSchema::active()
    ->get()
    ->groupBy('is_external');

// $actions[false] = internal actions (your PHP code)
// $actions[true] = external APIs (Stripe, Twilio, etc.)

// ============================================
// UI BUILDER: DROPDOWN STRUCTURE
// ============================================

/*
Action Selector Dropdown:

📦 Internal Actions
  ├─ Orders
  │  ├─ Process Customer Order
  │  └─ Cancel Order
  ├─ Inventory
  │  ├─ Update Stock
  │  └─ Reorder Product
  └─ Notifications
     └─ Send Email Notification

🌐 External Services
  ├─ Payments
  │  ├─ Stripe: Create Charge
  │  ├─ Stripe: Create Customer
  │  └─ PayPal: Process Payment
  ├─ Notifications
  │  ├─ Twilio: Send SMS
  │  ├─ SendGrid: Send Email
  │  └─ Slack: Post Message
  └─ Data
     ├─ Algolia: Index Document
     └─ Elasticsearch: Search
*/

// ============================================
// KEY TAKEAWAYS
// ============================================

/*
SAME FORMAT, DIFFERENT EXECUTION:

✅ BOTH use OpenAPI 3.0 schema format
✅ BOTH stored in same database table
✅ BOTH validated the same way
✅ BOTH discoverable through API
✅ BOTH usable in workflows

DIFFERENCES:

🔸 Internal = PHP code execution
🔸 External = HTTP API calls

🔸 Internal has x-php-* extensions
🔸 External has x-http-* extensions

🔸 Internal config has queue, retries
🔸 External config has endpoint, auth, headers

BENEFITS:

✅ Unified schema management
✅ Same tooling (Swagger UI, validation)
✅ Seamless integration in workflows
✅ One API to manage both types
✅ Consistent developer experience
*/
