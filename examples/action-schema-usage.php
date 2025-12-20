<?php

/**
 * ===============================================
 * Action Schema Discovery - Usage Examples
 * ===============================================
 *
 * This file demonstrates how to use the ActionSchema service
 * and API endpoints to discover what parameters your actions
 * need and what they return.
 */

use AlizHarb\ForgePulse\Services\ActionSchema;
use Illuminate\Support\Facades\Http;

// ============================================
// 1. USING THE SERVICE DIRECTLY
// ============================================

$schemaService = app(ActionSchema::class);

// Get schema for a specific action
$schema = $schemaService->getSchema(
    class: \App\Actions\ProcessOrderService::class,
    method: 'handle' // optional, will auto-detect
);

/*
Returns:
[
    'class' => 'App\Actions\ProcessOrderService',
    'method' => 'handle',
    'name' => 'Process Customer Order',
    'description' => 'Validates, calculates totals, and creates an order record',
    'category' => 'orders',
    'tags' => ['payment', 'inventory'],
    'recommended_timeout' => 30,
    'parameters' => [
        [
            'name' => 'customerId',
            'type' => 'int',
            'required' => true,
            'default' => null,
            'description' => 'Customer ID who is placing the order',
            'example' => '12345',
            'validation' => ['min' => 1],
            'source' => 'context',
        ],
        [
            'name' => 'items',
            'type' => 'array',
            'required' => true,
            'default' => null,
            'description' => 'Array of items with product_id and quantity',
            'example' => '[{"product_id": 1, "quantity": 2}]',
            'validation' => ['min_items' => 1],
            'source' => 'config',
        ],
        [
            'name' => 'taxRate',
            'type' => 'float',
            'required' => false,
            'default' => 0.0,
            'description' => 'Tax rate as decimal (e.g., 0.08 for 8%)',
            'example' => '0.08',
            'validation' => ['min' => 0, 'max' => 1],
            'source' => 'config',
        ],
    ],
    'return_type' => [
        'type' => 'array',
        'description' => 'Order result with totals and ID',
    ],
    'output_fields' => [
        'order_id' => ['type' => 'int', 'description' => 'Created order ID'],
        'subtotal' => ['type' => 'float', 'description' => 'Order subtotal before tax'],
        'tax' => ['type' => 'float', 'description' => 'Tax amount'],
        'total' => ['type' => 'float', 'description' => 'Total order amount'],
        'status' => ['type' => 'string', 'description' => 'Order status (pending, confirmed)'],
    ],
]
*/

// ============================================
// 2. USING THE API ENDPOINT
// ============================================

// Single action schema
$response = Http::get('/api/forgepulse/actions/schema', [
    'class' => 'App\Actions\ProcessOrderService',
    'method' => 'handle', // optional
]);

$schema = $response->json('data');

// Multiple actions at once (bulk)
$response = Http::post('/api/forgepulse/actions/schema/bulk', [
    'classes' => [
        'App\Actions\ProcessOrderService',
        'App\Actions\SendEmailNotification',
        'App\Actions\UpdateInventoryService',
    ],
]);

$schemas = $response->json('data');

// ============================================
// 3. BUILDING A WORKFLOW WITH SCHEMA INFO
// ============================================

// Step 1: Get schema to know what parameters are needed
$schema = $schemaService->getSchema(\App\Actions\ProcessOrderService::class);

// Step 2: Build step configuration based on schema
$stepConfig = [
    'type' => 'action',
    'configuration' => [
        'class' => 'App\Actions\ProcessOrderService',
        'mode' => 'sync',
        'parameters' => [
            // From schema: customerId is required, source=context
            'customerId' => '{{customer.id}}', // variable substitution

            // From schema: items is required, source=config
            'items' => [
                ['product_id' => 1, 'price' => 29.99, 'quantity' => 2],
                ['product_id' => 2, 'price' => 15.50, 'quantity' => 1],
            ],

            // From schema: taxRate is optional, default=0.0
            'taxRate' => 0.08,
        ],
    ],
];

// Step 3: Create workflow with this step
$workflow = \AlizHarb\ForgePulse\Models\Workflow::create([
    'name' => 'Order Processing Workflow',
    'description' => 'End-to-end order fulfillment',
    'status' => 'active',
]);

$workflow->steps()->create([
    'name' => 'Process Order',
    'type' => 'action',
    'configuration' => $stepConfig['configuration'],
    'order' => 1,
    'timeout' => $schema['recommended_timeout'], // Use recommended timeout from schema
]);

// ============================================
// 4. UI INTEGRATION - DYNAMIC FORM GENERATION
// ============================================

/**
 * Example: Build a UI form dynamically from schema
 */
function generateFormFields(array $schema): array
{
    $fields = [];

    foreach ($schema['parameters'] as $param) {
        $field = [
            'name' => $param['name'],
            'label' => ucfirst(preg_replace('/([A-Z])/', ' $1', $param['name'])),
            'type' => $param['type'],
            'required' => $param['required'],
            'default' => $param['default'],
            'description' => $param['description'],
            'placeholder' => $param['example'],
        ];

        // Add validation rules
        if ($param['validation']) {
            $field['validation'] = $param['validation'];
        }

        // Add source indicator
        if ($param['source'] === 'context') {
            $field['hint'] = 'This value comes from workflow context (use {{variable}})';
        }

        $fields[] = $field;
    }

    return $fields;
}

// Usage in UI controller
$schema = $schemaService->getSchema(\App\Actions\ProcessOrderService::class);
$formFields = generateFormFields($schema);

// Return to frontend
return view('workflow-builder', [
    'action_schema' => $schema,
    'form_fields' => $formFields,
]);

// ============================================
// 5. VALIDATION BEFORE EXECUTION
// ============================================

/**
 * Validate step configuration against action schema
 */
function validateStepConfig(array $stepConfig, array $schema): array
{
    $errors = [];

    // Check all required parameters are present
    foreach ($schema['parameters'] as $param) {
        if ($param['required']) {
            if (! isset($stepConfig['parameters'][$param['name']])) {
                $errors[] = "Missing required parameter: {$param['name']}";
            }
        }
    }

    // Check parameter types
    foreach ($stepConfig['parameters'] as $name => $value) {
        $paramSchema = collect($schema['parameters'])->firstWhere('name', $name);

        if (! $paramSchema) {
            $errors[] = "Unknown parameter: {$name}";

            continue;
        }

        // Type validation (basic)
        $expectedType = $paramSchema['type'];
        $actualType = gettype($value);

        if ($expectedType === 'int' && ! is_int($value)) {
            $errors[] = "Parameter {$name} must be integer, got {$actualType}";
        }
    }

    return $errors;
}

// ============================================
// 6. EXAMPLE: AUTO-DISCOVERY OF ACTIONS
// ============================================

/**
 * Scan directory for available actions
 */
function discoverActions(string $namespace = 'App\\Actions'): array
{
    $actions = [];
    $schemaService = app(ActionSchema::class);

    // Get all classes in namespace (pseudo-code)
    $classes = [
        'App\\Actions\\ProcessOrderService',
        'App\\Actions\\SendEmailNotification',
        'App\\Actions\\UpdateInventoryService',
    ];

    foreach ($classes as $class) {
        try {
            $schema = $schemaService->getSchema($class);
            $actions[] = [
                'id' => $class,
                'name' => $schema['name'],
                'description' => $schema['description'],
                'category' => $schema['category'],
                'tags' => $schema['tags'],
            ];
        } catch (\Exception $e) {
            // Skip classes that can't be used as actions
            continue;
        }
    }

    return $actions;
}

// ============================================
// 7. TESTING WITH SCHEMA
// ============================================

/**
 * Test that action schema matches expectations
 */
test('ProcessOrderService has correct schema', function () {
    $schemaService = app(ActionSchema::class);
    $schema = $schemaService->getSchema(\App\Actions\ProcessOrderService::class);

    expect($schema)
        ->toHaveKey('parameters')
        ->and($schema['parameters'])
        ->toHaveCount(3)
        ->and($schema['parameters'][0]['name'])
        ->toBe('customerId')
        ->and($schema['output_fields'])
        ->toHaveKey('order_id');
});
