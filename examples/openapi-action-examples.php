<?php

/**
 * ===============================================
 * OpenAPI Schema for Workflow Actions
 * ===============================================
 *
 * Using industry-standard OpenAPI 3.0 format
 * Benefits:
 * - Standard format everyone knows
 * - JSON Schema validation built-in
 * - Compatible with Swagger UI, Postman, etc.
 * - Can generate client SDKs automatically
 * - Rich ecosystem of tools
 */

use AlizHarb\ForgePulse\Contracts\ProvidesSchema;

// ============================================
// ✅ FULL OpenAPI Schema Example
// ============================================

class ProcessOrderService implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'summary' => 'Process Customer Order',
            'description' => 'Validates order items, calculates totals with tax, and creates an order record in the database',
            'tags' => ['orders', 'payment', 'inventory'],
            'operationId' => 'processOrder',

            // Input parameters using JSON Schema
            'parameters' => [
                'customerId' => [
                    'type' => 'integer',
                    'description' => 'Customer ID from workflow context',
                    'required' => true,
                    'example' => 12345,
                    'minimum' => 1,
                    'x-source' => 'context', // Custom extension
                ],
                'items' => [
                    'type' => 'array',
                    'description' => 'Order line items',
                    'required' => true,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => ['type' => 'integer', 'minimum' => 1],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                            'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0],
                        ],
                        'required' => ['product_id', 'quantity', 'price'],
                    ],
                    'minItems' => 1,
                    'example' => [
                        ['product_id' => 101, 'quantity' => 2, 'price' => 29.99],
                        ['product_id' => 203, 'quantity' => 1, 'price' => 15.50],
                    ],
                ],
                'taxRate' => [
                    'type' => 'number',
                    'format' => 'float',
                    'description' => 'Tax rate as decimal (e.g., 0.08 for 8%)',
                    'required' => false,
                    'default' => 0.0,
                    'minimum' => 0,
                    'maximum' => 1,
                    'example' => 0.08,
                ],
            ],

            // Output response using OpenAPI response schema
            'responses' => [
                '200' => [
                    'description' => 'Order processed successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'order_id' => [
                                        'type' => 'integer',
                                        'description' => 'Newly created order ID',
                                        'example' => 5678,
                                    ],
                                    'subtotal' => [
                                        'type' => 'number',
                                        'format' => 'float',
                                        'description' => 'Order subtotal before tax',
                                        'example' => 75.48,
                                    ],
                                    'tax' => [
                                        'type' => 'number',
                                        'format' => 'float',
                                        'description' => 'Calculated tax amount',
                                        'example' => 6.04,
                                    ],
                                    'total' => [
                                        'type' => 'number',
                                        'format' => 'float',
                                        'description' => 'Total order amount',
                                        'example' => 81.52,
                                    ],
                                    'status' => [
                                        'type' => 'string',
                                        'enum' => ['pending', 'confirmed', 'processing'],
                                        'description' => 'Current order status',
                                        'example' => 'pending',
                                    ],
                                ],
                                'required' => ['order_id', 'total', 'status'],
                            ],
                        ],
                    ],
                ],
            ],

            // Optional: Timeout recommendation (custom extension)
            'x-timeout' => 30,
            'x-category' => 'orders',
        ];
    }

    public function handle(int $customerId, array $items, float $taxRate = 0.0): array
    {
        $subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $tax = $subtotal * $taxRate;

        return [
            'order_id' => rand(1000, 9999),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'status' => 'pending',
        ];
    }
}

// ============================================
// ✅ Minimal OpenAPI Schema (Simple actions)
// ============================================

class SendEmailNotification implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'summary' => 'Send Email Notification',
            'tags' => ['notifications'],
            'parameters' => [
                'to' => [
                    'type' => 'string',
                    'format' => 'email',
                    'required' => true,
                ],
                'subject' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'body' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Email sent successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'sent' => ['type' => 'boolean'],
                                    'message_id' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function __invoke(string $to, string $subject, string $body): array
    {
        return ['sent' => true, 'message_id' => uniqid('msg_')];
    }
}

// ============================================
// ✅ Using JSON Schema References
// ============================================

class CreateUserService implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'summary' => 'Create User Account',
            'parameters' => [
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                    'required' => true,
                ],
                'name' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'maxLength' => 100,
                    'required' => true,
                ],
                'role' => [
                    'type' => 'string',
                    'enum' => ['user', 'admin', 'manager'],
                    'default' => 'user',
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'User created',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/User'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function handle(string $email, string $name, string $role = 'user'): array
    {
        return ['id' => 123, 'email' => $email, 'name' => $name, 'role' => $role];
    }
}

// ============================================
// Benefits of OpenAPI Format
// ============================================

/*
1. ✅ Industry Standard
   - Everyone knows OpenAPI/Swagger
   - No learning curve for custom format

2. ✅ Built-in Validation
   - JSON Schema validation (min/max, patterns, formats)
   - Type coercion rules
   - Enum validation

3. ✅ Rich Ecosystem
   - Swagger UI for interactive docs
   - Postman can import OpenAPI specs
   - Code generators for clients
   - Validation libraries

4. ✅ Reusability
   - Can export workflow actions as API docs
   - Share schemas with frontend teams
   - Generate TypeScript types

5. ✅ Extensibility
   - Custom x-* fields for workflow-specific metadata
   - Compatible with API Gateway tools
*/

// ============================================
// Exporting as OpenAPI Document
// ============================================

/**
 * Export all actions as a complete OpenAPI 3.0 spec
 */
function exportWorkflowActionsAsOpenAPI(array $actionClasses): array
{
    $paths = [];

    foreach ($actionClasses as $class) {
        if (! method_exists($class, 'schema')) {
            continue;
        }

        $schema = $class::schema();
        $operationId = $schema['operationId'] ?? class_basename($class);

        $paths["/actions/{$operationId}"] = [
            'post' => $schema,
        ];
    }

    return [
        'openapi' => '3.0.3',
        'info' => [
            'title' => 'ForgePulse Workflow Actions',
            'version' => '1.0.0',
            'description' => 'Available actions for workflow orchestration',
        ],
        'paths' => $paths,
    ];
}

// Usage: Generate OpenAPI spec file
$spec = exportWorkflowActionsAsOpenAPI([
    ProcessOrderService::class,
    SendEmailNotification::class,
    CreateUserService::class,
]);

file_put_contents('openapi.json', json_encode($spec, JSON_PRETTY_PRINT));

// Now you can:
// - Import into Swagger UI
// - Generate client SDKs
// - Validate requests
// - Share with frontend teams
