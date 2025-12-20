<?php

/**
 * ===============================================
 * Internal Action Schema in Database - Examples
 * ===============================================
 *
 * How internal PHP actions are stored in OpenAPI format
 */

use AlizHarb\ForgePulse\Models\ActionSchema;

// ============================================
// EXAMPLE 1: SIMPLE SERVICE
// ============================================

// Your PHP action class
namespace App\Actions;

class ProcessOrderService implements ProvidesSchema
{
    public function handle(int $customerId, array $items, float $taxRate = 0.0): array
    {
        // Implementation
        return ['order_id' => 123, 'total' => 99.99];
    }
}

// How it looks in the database
ActionSchema::create([
    // Identification
    'action_class' => 'App\\Actions\\ProcessOrderService',
    'action_type' => 'internal',
    'method' => 'handle',
    'is_external' => false,
    'is_active' => true,

    // Metadata
    'name' => 'Process Customer Order',
    'description' => 'Validates order items, calculates totals, and creates order record',
    'category' => 'orders',
    'tags' => ['orders', 'checkout', 'payment'],

    // Source tracking
    'source' => 'code', // or 'manual', 'imported'
    'version' => 1,

    // OpenAPI 3.0 Schema (JSON field)
    'schema' => [
        'summary' => 'Process Customer Order',
        'description' => 'Validates order items, calculates totals with tax, and creates an order record in the database',
        'operationId' => 'processOrder',
        'tags' => ['orders'],

        // Input parameters (JSON Schema)
        'parameters' => [
            'customerId' => [
                'type' => 'integer',
                'description' => 'Customer ID from the workflow context',
                'required' => true,
                'minimum' => 1,
                'example' => 12345,
                'x-source' => 'context', // Custom: where value comes from
                'x-variable' => '{{customer.id}}', // Custom: suggested variable
            ],
            'items' => [
                'type' => 'array',
                'description' => 'Order line items with product details',
                'required' => true,
                'minItems' => 1,
                'maxItems' => 100,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => [
                            'type' => 'integer',
                            'description' => 'Product ID',
                            'minimum' => 1,
                        ],
                        'quantity' => [
                            'type' => 'integer',
                            'description' => 'Quantity to order',
                            'minimum' => 1,
                            'maximum' => 999,
                        ],
                        'price' => [
                            'type' => 'number',
                            'format' => 'float',
                            'description' => 'Unit price',
                            'minimum' => 0,
                        ],
                    ],
                    'required' => ['product_id', 'quantity', 'price'],
                ],
                'example' => [
                    ['product_id' => 101, 'quantity' => 2, 'price' => 29.99],
                    ['product_id' => 203, 'quantity' => 1, 'price' => 15.50],
                ],
                'x-source' => 'config',
            ],
            'taxRate' => [
                'type' => 'number',
                'format' => 'float',
                'description' => 'Tax rate as decimal (e.g., 0.08 for 8% tax)',
                'required' => false,
                'default' => 0.0,
                'minimum' => 0,
                'maximum' => 1,
                'example' => 0.08,
                'x-source' => 'config',
            ],
        ],

        // Output response (OpenAPI response schema)
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
                                    'description' => 'Total order amount including tax',
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
            '400' => [
                'description' => 'Invalid input parameters',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'details' => ['type' => 'array'],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        // Custom extensions (x-* fields)
        'x-timeout' => 30,
        'x-retries' => 3,
        'x-category' => 'orders',
        'x-php-class' => 'App\\Actions\\ProcessOrderService',
        'x-php-method' => 'handle',
    ],

    // Additional configuration
    'config' => [
        'timeout' => 30,
        'max_retries' => 3,
        'queue' => 'orders',
    ],
]);

// ============================================
// EXAMPLE 2: NOTIFICATION SERVICE
// ============================================

namespace App\Actions;

class SendEmailNotification
{
    public function __invoke(string $to, string $subject, string $body): array
    {
        // Send email
        return ['sent' => true, 'message_id' => 'msg_123'];
    }
}

// Database record
ActionSchema::create([
    'action_class' => 'App\\Actions\\SendEmailNotification',
    'action_type' => 'internal',
    'method' => '__invoke',
    'is_external' => false,
    'is_active' => true,
    'name' => 'Send Email Notification',
    'description' => 'Sends transactional email via Laravel Mail',
    'category' => 'notifications',
    'tags' => ['email', 'notifications'],
    'source' => 'code',
    'version' => 1,

    'schema' => [
        'summary' => 'Send Email Notification',
        'description' => 'Sends a transactional email using Laravel Mail system',
        'operationId' => 'sendEmailNotification',
        'tags' => ['notifications'],

        'parameters' => [
            'to' => [
                'type' => 'string',
                'format' => 'email',
                'description' => 'Recipient email address',
                'required' => true,
                'pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
                'example' => 'customer@example.com',
                'x-source' => 'context',
                'x-variable' => '{{customer.email}}',
            ],
            'subject' => [
                'type' => 'string',
                'description' => 'Email subject line',
                'required' => true,
                'minLength' => 1,
                'maxLength' => 255,
                'example' => 'Your Order #{{order.id}} has been confirmed',
                'x-source' => 'config',
            ],
            'body' => [
                'type' => 'string',
                'description' => 'Email body content (supports HTML)',
                'required' => true,
                'minLength' => 1,
                'example' => '<h1>Thank you!</h1><p>Your order has been confirmed.</p>',
                'x-source' => 'config',
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
                                'sent' => [
                                    'type' => 'boolean',
                                    'description' => 'Whether email was sent',
                                    'example' => true,
                                ],
                                'message_id' => [
                                    'type' => 'string',
                                    'description' => 'Email provider message ID',
                                    'example' => 'msg_abc123xyz',
                                ],
                            ],
                            'required' => ['sent'],
                        ],
                    ],
                ],
            ],
        ],

        'x-timeout' => 15,
        'x-category' => 'notifications',
    ],

    'config' => [
        'timeout' => 15,
        'queue' => 'emails',
    ],
]);

// ============================================
// EXAMPLE 3: COMPLEX DATA TRANSFORMATION
// ============================================

namespace App\Actions;

class TransformCustomerData
{
    public function handle(array $rawData, string $format = 'json'): array
    {
        // Transform data
        return ['transformed' => true, 'data' => []];
    }
}

// Database record
ActionSchema::create([
    'action_class' => 'App\\Actions\\TransformCustomerData',
    'action_type' => 'internal',
    'method' => 'handle',
    'is_external' => false,
    'is_active' => true,
    'name' => 'Transform Customer Data',
    'description' => 'Transforms raw customer data into standardized format',
    'category' => 'data',
    'tags' => ['transformation', 'etl', 'data'],
    'source' => 'manual',
    'version' => 1,

    'schema' => [
        'summary' => 'Transform Customer Data',
        'description' => 'Transforms raw customer data from various sources into a standardized format for downstream processing',
        'operationId' => 'transformCustomerData',
        'tags' => ['data'],

        'parameters' => [
            'rawData' => [
                'type' => 'object',
                'description' => 'Raw customer data from external source',
                'required' => true,
                'properties' => [
                    'first_name' => ['type' => 'string'],
                    'last_name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'phone' => ['type' => 'string'],
                    'address' => [
                        'type' => 'object',
                        'properties' => [
                            'street' => ['type' => 'string'],
                            'city' => ['type' => 'string'],
                            'state' => ['type' => 'string'],
                            'zip' => ['type' => 'string'],
                        ],
                    ],
                ],
                'additionalProperties' => true,
                'example' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ],
                'x-source' => 'context',
            ],
            'format' => [
                'type' => 'string',
                'description' => 'Output format for transformed data',
                'required' => false,
                'default' => 'json',
                'enum' => ['json', 'xml', 'csv'],
                'example' => 'json',
                'x-source' => 'config',
            ],
        ],

        'responses' => [
            '200' => [
                'description' => 'Data transformed successfully',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'transformed' => [
                                    'type' => 'boolean',
                                    'example' => true,
                                ],
                                'data' => [
                                    'type' => 'object',
                                    'description' => 'Transformed customer data',
                                ],
                                'format' => [
                                    'type' => 'string',
                                    'example' => 'json',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'x-timeout' => 10,
        'x-category' => 'data',
    ],

    'config' => [
        'timeout' => 10,
        'queue' => 'transformations',
    ],
]);

// ============================================
// DATABASE TABLE STRUCTURE
// ============================================

/*
action_schemas table:

id: 1
action_class: "App\\Actions\\ProcessOrderService"
action_type: "internal"
method: "handle"
is_external: false
is_active: true
name: "Process Customer Order"
description: "Validates order items, calculates totals..."
category: "orders"
tags: ["orders", "checkout", "payment"]
source: "code"
version: 1
schema: {
    "summary": "Process Customer Order",
    "parameters": {...},
    "responses": {...}
}
config: {
    "timeout": 30,
    "max_retries": 3,
    "queue": "orders"
}
created_at: "2025-12-20 10:00:00"
updated_at: "2025-12-20 10:00:00"
*/

// ============================================
// QUERYING EXAMPLES
// ============================================

// Get schema from database
$schema = ActionSchema::where('action_class', 'App\\Actions\\ProcessOrderService')
    ->where('method', 'handle')
    ->first();

echo $schema->name; // "Process Customer Order"
echo $schema->category; // "orders"
echo $schema->version; // 1

// Access OpenAPI schema
$openapi = $schema->schema;
echo $openapi['summary']; // "Process Customer Order"
$params = $openapi['parameters'];
// $params['customerId']['type'] === 'integer'

// Get parameters easily
foreach ($schema->parameters as $param) {
    echo "{$param['name']}: {$param['type']}\n";
}

// Get output schema
$outputSchema = $schema->response_schema;
// $outputSchema['properties']['order_id']['type'] === 'integer'

// ============================================
// HOW IT'S USED IN WORKFLOWS
// ============================================

// Step configuration references the schema
$workflow->steps()->create([
    'name' => 'Process Order',
    'type' => 'action',
    'order' => 1,
    'configuration' => [
        // This references the database schema
        'class' => 'App\\Actions\\ProcessOrderService',
        'method' => 'handle', // optional if in schema
        'mode' => 'sync',

        // Parameters validated against schema
        'parameters' => [
            'customerId' => '{{customer.id}}',
            'items' => [
                ['product_id' => 101, 'quantity' => 2, 'price' => 29.99],
            ],
            'taxRate' => 0.08,
        ],
    ],
]);

// When workflow executes:
// 1. Get schema from DB for 'App\\Actions\\ProcessOrderService'
// 2. Validate parameters against schema['parameters']
// 3. Execute the action
// 4. Validate output against schema['responses']['200']

// ============================================
// SYNC CODE TO DATABASE
// ============================================

// If your action has ProvidesSchema::schema() method,
// you can sync it to database:

Http::post('/api/forgepulse/action-schemas/sync', [
    'classes' => ['App\\Actions\\ProcessOrderService'],
]);

// This creates/updates the database record automatically
// source will be 'code'

// ============================================
// OVERRIDE CODE-BASED SCHEMA
// ============================================

// You can override a code-based schema in the database
$schema = ActionSchema::where('action_class', 'App\\Actions\\ProcessOrderService')->first();

// Change the schema
$newSchema = $schema->schema;
$newSchema['parameters']['customerId']['minimum'] = 100; // New constraint!
$newSchema['x-timeout'] = 60; // Increase timeout

$schema->update([
    'schema' => $newSchema,
    'source' => 'manual', // Now it's manually managed
]);

// Database now takes priority over code!
