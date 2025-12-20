<?php

/**
 * Workflow Trigger API Examples
 *
 * Demonstrates how to create workflows with automatic triggers via the API.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */

use Illuminate\Support\Facades\Http;

$apiUrl = 'https://your-app.com/api/forgepulse';
$token = 'your-api-token';

/*
|--------------------------------------------------------------------------
| Example 1: Event-Triggered Workflow
|--------------------------------------------------------------------------
|
| Automatically execute workflow when UserRegistered event is fired.
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'User Onboarding - Event Triggered',
    'description' => 'Automatically triggers when new user registers',
    'status' => 'active',
    'trigger_type' => 'event',
    'trigger_config' => [
        'event_class' => 'App\\Events\\UserRegistered',
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                ['field' => 'user.email_verified', 'operator' => '==', 'value' => true],
            ],
        ],
    ],
    'auto_trigger_enabled' => true,
    'steps' => [
        [
            'name' => 'Send Welcome Email',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\WelcomeEmail',
                'recipients' => ['{{user.email}}'],
            ],
            'position' => 1,
        ],
        [
            'name' => 'Create User Profile',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\CreateUserProfile',
                'parameters' => ['user_id' => '{{user.id}}'],
            ],
            'position' => 2,
        ],
    ],
]);

$workflow = $response->json('data');
echo "Created event-triggered workflow: {$workflow['id']}\n";

/*
|--------------------------------------------------------------------------
| Example 2: Schedule-Triggered Workflow (Daily Report)
|--------------------------------------------------------------------------
|
| Execute workflow every day at 9 AM.
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Daily Sales Report',
    'description' => 'Generates and emails daily sales report',
    'status' => 'active',
    'trigger_type' => 'schedule',
    'trigger_config' => [
        'cron_expression' => '0 9 * * *', // Every day at 9:00 AM
        'timezone' => 'America/New_York',
        'context' => [
            'report_type' => 'daily_sales',
            'recipients' => ['admin@example.com', 'sales@example.com'],
        ],
    ],
    'auto_trigger_enabled' => true,
    'steps' => [
        [
            'name' => 'Generate Sales Report',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\GenerateSalesReport',
                'parameters' => ['report_type' => '{{report_type}}'],
            ],
            'position' => 1,
        ],
        [
            'name' => 'Email Report to Team',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\SalesReport',
                'recipients' => ['{{recipients}}'],
            ],
            'position' => 2,
        ],
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 3: Webhook-Triggered Workflow
|--------------------------------------------------------------------------
|
| Execute workflow when external service sends webhook.
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Stripe Payment Processing',
    'description' => 'Processes Stripe webhook payments',
    'status' => 'active',
    'trigger_type' => 'webhook',
    'trigger_config' => [
        // Token will be auto-generated if not provided
        'validation_rules' => [
            'type' => 'required|string',
            'data.object.id' => 'required|string',
            'data.object.amount' => 'required|numeric',
        ],
        'payload_mapping' => [
            'payment_id' => 'data.object.id',
            'amount' => 'data.object.amount',
            'currency' => 'data.object.currency',
            'customer_email' => 'data.object.billing_details.email',
        ],
    ],
    'auto_trigger_enabled' => true,
    'steps' => [
        [
            'name' => 'Process Payment',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessStripePayment',
                'parameters' => [
                    'payment_id' => '{{payment_id}}',
                    'amount' => '{{amount}}',
                ],
            ],
            'position' => 1,
        ],
        [
            'name' => 'Send Receipt',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\PaymentReceipt',
                'recipients' => ['{{customer_email}}'],
            ],
            'position' => 2,
        ],
    ],
]);

$workflow = $response->json('data');
$webhookUrl = $workflow['webhook_url'];
$webhookToken = $workflow['webhook_token'];

echo "Webhook URL: {$webhookUrl}\n";
echo "Webhook Token: {$webhookToken}\n";

// Configure this URL in Stripe dashboard:
// https://your-app.com/api/forgepulse/webhook/{workflow_id}/{token}

/*
|--------------------------------------------------------------------------
| Example 4: Model-Triggered Workflow (High-Value Orders)
|--------------------------------------------------------------------------
|
| Execute workflow when Order model is created with high value.
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'High-Value Order Alert',
    'description' => 'Notifies team when orders exceed $1000',
    'status' => 'active',
    'trigger_type' => 'model',
    'trigger_config' => [
        'model_class' => 'App\\Models\\Order',
        'events' => ['created'], // created, updated, deleted, restored
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                ['field' => 'model.total', 'operator' => '>', 'value' => 1000],
                ['field' => 'model.status', 'operator' => '==', 'value' => 'pending'],
            ],
        ],
    ],
    'auto_trigger_enabled' => true,
    'steps' => [
        [
            'name' => 'Notify Sales Team',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\HighValueOrderAlert',
                'recipients' => ['sales@example.com'],
                'data' => [
                    'order_id' => '{{model.id}}',
                    'total' => '{{model.total}}',
                    'customer' => '{{model.customer_name}}',
                ],
            ],
            'position' => 1,
        ],
        [
            'name' => 'Call External CRM API',
            'type' => 'webhook',
            'configuration' => [
                'url' => 'https://crm.example.com/api/high-value-order',
                'method' => 'POST',
                'headers' => ['Authorization' => 'Bearer crm-token'],
                'payload' => [
                    'order_id' => '{{model.id}}',
                    'amount' => '{{model.total}}',
                ],
            ],
            'position' => 2,
        ],
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 5: Complex Schedule Trigger (Weekly Report Every Monday)
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Weekly Summary Report',
    'status' => 'active',
    'trigger_type' => 'schedule',
    'trigger_config' => [
        'cron_expression' => '0 9 * * 1', // Every Monday at 9 AM
        'timezone' => 'UTC',
        'context' => [
            'report_period' => 'week',
            'include_charts' => true,
        ],
    ],
    'auto_trigger_enabled' => true,
    'steps' => [
        [
            'name' => 'Generate Weekly Report',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\GenerateWeeklyReport',
            ],
            'position' => 1,
        ],
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 6: Multi-Event Model Trigger
|--------------------------------------------------------------------------
|
| Trigger on both created and updated events.
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Order Status Tracker',
    'status' => 'active',
    'trigger_type' => 'model',
    'trigger_config' => [
        'model_class' => 'App\\Models\\Order',
        'events' => ['created', 'updated'],
        'conditions' => [
            'operator' => 'or',
            'rules' => [
                ['field' => '_event', 'operator' => '==', 'value' => 'created'],
                [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => '_event', 'operator' => '==', 'value' => 'updated'],
                        ['field' => '_changes.status', 'operator' => 'is_not_null', 'value' => null],
                    ],
                ],
            ],
        ],
    ],
    'auto_trigger_enabled' => true,
    'steps' => [
        [
            'name' => 'Log Order Change',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\LogOrderChange',
                'parameters' => [
                    'order_id' => '{{model_id}}',
                    'event' => '{{_event}}',
                    'changes' => '{{_changes}}',
                ],
            ],
            'position' => 1,
        ],
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 7: Update Workflow to Enable/Disable Trigger
|--------------------------------------------------------------------------
*/

// Disable auto-trigger
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'auto_trigger_enabled' => false,
]);

// Re-enable with updated configuration
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'auto_trigger_enabled' => true,
    'trigger_config' => [
        'cron_expression' => '0 10 * * *', // Changed to 10 AM
        'timezone' => 'America/Los_Angeles',
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 8: Trigger Webhook Manually (Test)
|--------------------------------------------------------------------------
*/

// Test the webhook endpoint
$response = Http::post($webhookUrl, [
    'type' => 'payment.succeeded',
    'data' => [
        'object' => [
            'id' => 'ch_test_123',
            'amount' => 5000,
            'currency' => 'usd',
            'billing_details' => [
                'email' => 'customer@example.com',
            ],
        ],
    ],
]);

if ($response->successful()) {
    $execution = $response->json();
    echo "Workflow executed: {$execution['execution_id']}\n";
}

/*
|--------------------------------------------------------------------------
| Example 9: Event Trigger with Multiple Conditions
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Premium User Welcome Flow',
    'status' => 'active',
    'trigger_type' => 'event',
    'trigger_config' => [
        'event_class' => 'App\\Events\\UserRegistered',
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                ['field' => 'user.plan', 'operator' => '==', 'value' => 'premium'],
                ['field' => 'user.email_verified', 'operator' => '==', 'value' => true],
                ['field' => 'user.country', 'operator' => 'in', 'value' => ['US', 'CA', 'UK']],
            ],
        ],
    ],
    'auto_trigger_enabled' => true,
    'steps' => [
        [
            'name' => 'Send Premium Welcome Email',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\PremiumWelcome',
                'recipients' => ['{{user.email}}'],
            ],
            'position' => 1,
        ],
        [
            'name' => 'Assign Account Manager',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\AssignAccountManager',
                'parameters' => ['user_id' => '{{user.id}}'],
            ],
            'position' => 2,
        ],
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 10: Hourly Scheduled Workflow
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Hourly Data Sync',
    'status' => 'active',
    'trigger_type' => 'schedule',
    'trigger_config' => [
        'cron_expression' => '0 * * * *', // Every hour
        'timezone' => 'UTC',
    ],
    'auto_trigger_enabled' => true,
    'steps' => [
        [
            'name' => 'Sync External Data',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\SyncExternalData',
            ],
            'position' => 1,
        ],
    ],
]);

echo "All workflow triggers created successfully!\n";
