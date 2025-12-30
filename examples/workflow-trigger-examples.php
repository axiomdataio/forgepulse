<?php

/**
 * Workflow Trigger API Usage Examples
 *
 * This file demonstrates how to use the ForgePulse Trigger API
 * to configure automatic workflow execution via different trigger types.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */

use Illuminate\Support\Facades\Http;

// API base URL
$apiUrl = 'https://your-app.com/api/forgepulse';
$token = 'your-api-token';

/*
|--------------------------------------------------------------------------
| Example 1: Create an Event Trigger
|--------------------------------------------------------------------------
|
| Trigger a workflow when a Laravel event is dispatched.
|
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows/1/triggers", [
    'name' => 'On User Registration',
    'type' => 'event',
    'is_active' => true,
    'configuration' => [
        'event_class' => 'App\\Events\\UserRegistered',
        'listen_once' => false,
    ],
    'context_mapping' => [
        'user_id' => 'user.id',
        'user_email' => 'user.email',
        'registered_at' => 'timestamp',
    ],
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'field' => 'user.is_verified',
                'operator' => '==',
                'value' => true,
            ],
        ],
    ],
]);

echo "Created event trigger: {$response->json('data.name')}\n";

/*
|--------------------------------------------------------------------------
| Example 2: Create a Schedule Trigger
|--------------------------------------------------------------------------
|
| Trigger a workflow on a cron schedule.
|
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows/1/triggers", [
    'name' => 'Daily Report Generation',
    'type' => 'schedule',
    'is_active' => true,
    'configuration' => [
        'cron_expression' => '0 9 * * *', // Every day at 9 AM
        'timezone' => 'Europe/London',
        'overlap_prevention' => true,
    ],
    'max_executions' => 1,
    'max_executions_period' => 'per_day',
]);

echo "Created schedule trigger: {$response->json('data.name')}\n";
echo "Next run at: {$response->json('data.next_run_at')}\n";

/*
|--------------------------------------------------------------------------
| Example 3: Create a Webhook Trigger
|--------------------------------------------------------------------------
|
| Trigger a workflow via incoming HTTP POST requests.
|
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows/1/triggers", [
    'name' => 'Stripe Payment Webhook',
    'type' => 'webhook',
    'is_active' => true,
    'configuration' => [
        'validate_signature' => true,
        'secret_token' => 'whsec_your_stripe_webhook_secret',
        'signature_header' => 'Stripe-Signature',
        'allowed_ips' => [], // Empty = allow all
    ],
    'context_mapping' => [
        'payment_id' => 'payload.data.object.id',
        'amount' => 'payload.data.object.amount',
        'customer_id' => 'payload.data.object.customer',
        'event_type' => 'payload.type',
    ],
    'conditions' => [
        'operator' => 'or',
        'rules' => [
            ['field' => 'payload.type', 'operator' => '==', 'value' => 'payment_intent.succeeded'],
            ['field' => 'payload.type', 'operator' => '==', 'value' => 'charge.succeeded'],
        ],
    ],
]);

$webhookUrl = $response->json('data.webhook_url');
echo "Created webhook trigger: {$response->json('data.name')}\n";
echo "Webhook URL: {$webhookUrl}\n";

/*
|--------------------------------------------------------------------------
| Example 4: Create a Model Trigger
|--------------------------------------------------------------------------
|
| Trigger a workflow on Eloquent model events.
|
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows/1/triggers", [
    'name' => 'Order Status Changed',
    'type' => 'model',
    'is_active' => true,
    'configuration' => [
        'model_class' => 'App\\Models\\Order',
        'events' => ['updated'],
        'attribute_filters' => [
            'watch_attributes' => ['status'],
            'conditions' => [
                'status' => 'shipped',
            ],
        ],
    ],
    'context_mapping' => [
        'order_id' => 'model.id',
        'customer_id' => 'model.customer_id',
        'new_status' => 'model.status',
        'old_status' => 'original.status',
        'total' => 'model.total',
    ],
]);

echo "Created model trigger: {$response->json('data.name')}\n";

/*
|--------------------------------------------------------------------------
| Example 5: Create a Manual Trigger
|--------------------------------------------------------------------------
|
| A documented entry point for programmatic execution.
|
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows/1/triggers", [
    'name' => 'Admin Manual Execution',
    'type' => 'manual',
    'is_active' => true,
    'configuration' => [
        'description' => 'Triggered manually by administrators for emergency processing',
    ],
]);

echo "Created manual trigger: {$response->json('data.name')}\n";

/*
|--------------------------------------------------------------------------
| Example 6: List Trigger Types
|--------------------------------------------------------------------------
|
| Get available trigger types and their configuration schemas.
|
*/

$response = Http::withToken($token)->get("{$apiUrl}/triggers/types");

echo "Available trigger types:\n";
foreach ($response->json('data') as $type) {
    echo "  - {$type['value']}: {$type['description']}\n";
}

/*
|--------------------------------------------------------------------------
| Example 7: Validate Cron Expression
|--------------------------------------------------------------------------
|
| Validate a cron expression before creating a schedule trigger.
|
*/

$response = Http::withToken($token)->post("{$apiUrl}/triggers/validate-cron", [
    'expression' => '0 */6 * * *', // Every 6 hours
]);

if ($response->json('valid')) {
    echo "Valid cron expression!\n";
    echo "Next run: {$response->json('next_run')}\n";
    echo "Description: {$response->json('description')}\n";
} else {
    echo "Invalid cron expression\n";
}

/*
|--------------------------------------------------------------------------
| Example 8: Toggle Trigger Active State
|--------------------------------------------------------------------------
|
| Enable or disable a trigger without deleting it.
|
*/

$triggerId = 1;
$response = Http::withToken($token)->post("{$apiUrl}/workflows/1/triggers/{$triggerId}/toggle");

$status = $response->json('data.is_active') ? 'active' : 'inactive';
echo "Trigger is now {$status}\n";

/*
|--------------------------------------------------------------------------
| Example 9: Update Trigger Configuration
|--------------------------------------------------------------------------
|
| Modify an existing trigger's settings.
|
*/

$response = Http::withToken($token)->put("{$apiUrl}/workflows/1/triggers/{$triggerId}", [
    'name' => 'Updated Trigger Name',
    'priority' => 100, // Higher priority = executed first when multiple triggers match
    'max_executions' => 5,
    'max_executions_period' => 'per_hour',
]);

echo "Updated trigger: {$response->json('data.name')}\n";

/*
|--------------------------------------------------------------------------
| Example 10: Using Webhooks from External Services
|--------------------------------------------------------------------------
|
| Example of sending data to a ForgePulse webhook endpoint.
|
*/

// After creating a webhook trigger, you get a URL like:
// https://your-app.com/api/forgepulse/webhook/abc123...

$webhookToken = 'your-webhook-token';
$webhookEndpoint = "https://your-app.com/api/forgepulse/webhook/{$webhookToken}";

// External service sends data to this endpoint:
$externalPayload = [
    'event' => 'order.completed',
    'data' => [
        'order_id' => 'ORD-12345',
        'total' => 150.00,
        'customer' => [
            'id' => 'cust_abc123',
            'email' => 'customer@example.com',
        ],
    ],
    'timestamp' => '2024-01-15T10:30:00Z',
];

// With signature validation:
$secret = 'your-webhook-secret';
$payload = json_encode($externalPayload);
$signature = hash_hmac('sha256', $payload, $secret);

$response = Http::withHeaders([
    'Content-Type' => 'application/json',
    'X-Signature' => $signature,
])->post($webhookEndpoint, $externalPayload);

if ($response->status() === 202) {
    echo "Workflow triggered successfully!\n";
    echo "Execution ID: {$response->json('execution.id')}\n";
}

/*
|--------------------------------------------------------------------------
| Example 11: Advanced Model Trigger with Multiple Events
|--------------------------------------------------------------------------
|
| Trigger on multiple model events with attribute filtering.
|
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows/1/triggers", [
    'name' => 'Product Inventory Alert',
    'type' => 'model',
    'is_active' => true,
    'configuration' => [
        'model_class' => 'App\\Models\\Product',
        'events' => ['created', 'updated', 'deleted'],
        'attribute_filters' => [
            'watch_attributes' => ['stock_quantity', 'is_active'],
            'conditions' => [
                'is_active' => true,
            ],
        ],
    ],
    'context_mapping' => [
        'product_id' => 'model.id',
        'product_name' => 'model.name',
        'stock_quantity' => 'model.stock_quantity',
        'event_type' => 'event',
    ],
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'field' => 'model.stock_quantity',
                'operator' => '<',
                'value' => 10,
            ],
        ],
    ],
]);

echo "Created inventory alert trigger\n";

/*
|--------------------------------------------------------------------------
| Example 12: Complex Schedule with Rate Limiting
|--------------------------------------------------------------------------
|
| Schedule trigger with timezone support and execution limits.
|
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows/1/triggers", [
    'name' => 'Business Hours Report',
    'type' => 'schedule',
    'is_active' => true,
    'configuration' => [
        'cron_expression' => '0 9-17 * * 1-5', // Every hour, 9 AM to 5 PM, Mon-Fri
        'timezone' => 'America/New_York',
        'overlap_prevention' => true,
    ],
    'max_executions' => 9, // Max 9 executions per day (one per hour)
    'max_executions_period' => 'per_day',
    'priority' => 50,
]);

echo "Created business hours trigger\n";
