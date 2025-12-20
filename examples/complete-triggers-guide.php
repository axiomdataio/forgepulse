<?php

/**
 * Complete Workflow Triggers Implementation Guide
 *
 * This file demonstrates all trigger types with real-world examples.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */

namespace App\Examples;

use AlizHarb\ForgePulse\Models\Workflow;
use Illuminate\Support\Facades\Http;

class WorkflowTriggersGuide
{
    private string $apiUrl = 'https://your-app.com/api/forgepulse';
    private string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Example 1: EVENT TRIGGER - User Registration
     *
     * Automatically sends welcome emails and sets up user profile
     * when a user registers and verifies their email.
     */
    public function createUserRegistrationWorkflow()
    {
        $response = Http::withToken($this->token)->post("{$this->apiUrl}/workflows", [
            'name' => 'User Registration & Onboarding',
            'description' => 'Triggered when UserRegistered event fires',
            'status' => 'active',
            
            // TRIGGER CONFIGURATION
            'trigger_type' => 'event',
            'trigger_config' => [
                'event_class' => 'App\\Events\\UserRegistered',
                
                // Only trigger for verified users
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => 'user.email_verified', 'operator' => '==', 'value' => true],
                    ],
                ],
            ],
            'auto_trigger_enabled' => true,

            // WORKFLOW STEPS
            'steps' => [
                [
                    'name' => 'Send Welcome Email',
                    'type' => 'notification',
                    'configuration' => [
                        'notification_class' => 'App\\Notifications\\WelcomeEmail',
                        'recipients' => ['{{user.email}}'],
                        'data' => [
                            'user_name' => '{{user.name}}',
                        ],
                    ],
                    'position' => 1,
                ],
                [
                    'name' => 'Create User Profile',
                    'type' => 'action',
                    'configuration' => [
                        'action_class' => 'App\\Actions\\CreateUserProfile',
                        'parameters' => [
                            'user_id' => '{{user.id}}',
                        ],
                    ],
                    'position' => 2,
                ],
                [
                    'name' => 'Notify Sales Team',
                    'type' => 'notification',
                    'configuration' => [
                        'notification_class' => 'App\\Notifications\\NewUserAlert',
                        'recipients' => ['sales@example.com'],
                        'data' => [
                            'user_id' => '{{user.id}}',
                            'user_email' => '{{user.email}}',
                        ],
                    ],
                    'position' => 3,
                ],
            ],
        ]);

        $workflow = $response->json('data');
        echo "✅ Event-triggered workflow created: {$workflow['id']}\n";
        
        return $workflow;
    }

    /**
     * Example 2: SCHEDULE TRIGGER - Daily Sales Report
     *
     * Generates and emails sales report every day at 9 AM.
     */
    public function createDailySalesReportWorkflow()
    {
        $response = Http::withToken($this->token)->post("{$this->apiUrl}/workflows", [
            'name' => 'Daily Sales Report Generation',
            'description' => 'Runs every day at 9 AM Eastern Time',
            'status' => 'active',
            
            // TRIGGER CONFIGURATION
            'trigger_type' => 'schedule',
            'trigger_config' => [
                'cron_expression' => '0 9 * * *',  // Every day at 9:00 AM
                'timezone' => 'America/New_York',
                
                // Context passed to workflow execution
                'context' => [
                    'report_type' => 'daily_sales',
                    'include_charts' => true,
                    'recipients' => ['admin@example.com', 'sales@example.com'],
                ],
            ],
            'auto_trigger_enabled' => true,

            // WORKFLOW STEPS
            'steps' => [
                [
                    'name' => 'Generate Sales Data',
                    'type' => 'action',
                    'configuration' => [
                        'action_class' => 'App\\Actions\\GenerateSalesReport',
                        'parameters' => [
                            'report_type' => '{{report_type}}',
                            'date' => '{{_triggered_at}}',
                        ],
                    ],
                    'position' => 1,
                ],
                [
                    'name' => 'Create Charts',
                    'type' => 'action',
                    'configuration' => [
                        'action_class' => 'App\\Actions\\GenerateSalesCharts',
                    ],
                    'conditions' => [
                        'operator' => 'and',
                        'rules' => [
                            ['field' => 'include_charts', 'operator' => '==', 'value' => true],
                        ],
                    ],
                    'position' => 2,
                ],
                [
                    'name' => 'Email Report',
                    'type' => 'notification',
                    'configuration' => [
                        'notification_class' => 'App\\Notifications\\DailySalesReport',
                        'recipients' => ['{{recipients}}'],
                    ],
                    'position' => 3,
                ],
            ],
        ]);

        $workflow = $response->json('data');
        echo "✅ Schedule-triggered workflow created: {$workflow['id']}\n";
        echo "📅 Cron: Every day at 9:00 AM EST\n";
        
        return $workflow;
    }

    /**
     * Example 3: WEBHOOK TRIGGER - Stripe Payment Processing
     *
     * Processes payments when Stripe sends webhook notifications.
     */
    public function createStripePaymentWorkflow()
    {
        $response = Http::withToken($this->token)->post("{$this->apiUrl}/workflows", [
            'name' => 'Stripe Payment Processing',
            'description' => 'Processes Stripe payment webhooks',
            'status' => 'active',
            
            // TRIGGER CONFIGURATION
            'trigger_type' => 'webhook',
            'trigger_config' => [
                // Validate incoming webhook payload
                'validation_rules' => [
                    'type' => 'required|string',
                    'data.object.id' => 'required|string',
                    'data.object.amount' => 'required|numeric',
                    'data.object.currency' => 'required|string',
                ],
                
                // Map webhook payload to workflow context
                'payload_mapping' => [
                    'payment_id' => 'data.object.id',
                    'amount' => 'data.object.amount',
                    'currency' => 'data.object.currency',
                    'customer_email' => 'data.object.billing_details.email',
                    'payment_method' => 'data.object.payment_method',
                ],
            ],
            'auto_trigger_enabled' => true,

            // WORKFLOW STEPS
            'steps' => [
                [
                    'name' => 'Validate Payment',
                    'type' => 'action',
                    'configuration' => [
                        'action_class' => 'App\\Actions\\ValidateStripePayment',
                        'parameters' => [
                            'payment_id' => '{{payment_id}}',
                        ],
                    ],
                    'position' => 1,
                ],
                [
                    'name' => 'Process Payment',
                    'type' => 'action',
                    'configuration' => [
                        'action_class' => 'App\\Actions\\ProcessPayment',
                        'parameters' => [
                            'payment_id' => '{{payment_id}}',
                            'amount' => '{{amount}}',
                            'currency' => '{{currency}}',
                        ],
                    ],
                    'position' => 2,
                ],
                [
                    'name' => 'Send Receipt',
                    'type' => 'notification',
                    'configuration' => [
                        'notification_class' => 'App\\Notifications\\PaymentReceipt',
                        'recipients' => ['{{customer_email}}'],
                        'data' => [
                            'payment_id' => '{{payment_id}}',
                            'amount' => '{{amount}}',
                        ],
                    ],
                    'position' => 3,
                ],
                [
                    'name' => 'Update Accounting System',
                    'type' => 'webhook',
                    'configuration' => [
                        'url' => 'https://accounting.example.com/api/payments',
                        'method' => 'POST',
                        'headers' => [
                            'Authorization' => 'Bearer accounting-api-token',
                        ],
                        'payload' => [
                            'payment_id' => '{{payment_id}}',
                            'amount' => '{{amount}}',
                            'date' => '{{_triggered_at}}',
                        ],
                    ],
                    'position' => 4,
                ],
            ],
        ]);

        $workflow = $response->json('data');
        $webhookUrl = $workflow['webhook_url'];
        $webhookToken = $workflow['webhook_token'];
        
        echo "✅ Webhook-triggered workflow created: {$workflow['id']}\n";
        echo "🔗 Webhook URL: {$webhookUrl}\n";
        echo "🔑 Token: {$webhookToken}\n";
        echo "\n📝 Configure this URL in Stripe Dashboard:\n";
        echo "   Developers > Webhooks > Add endpoint\n";
        
        return $workflow;
    }

    /**
     * Example 4: MODEL TRIGGER - High-Value Order Processing
     *
     * Automatically processes orders when they exceed a threshold.
     */
    public function createHighValueOrderWorkflow()
    {
        $response = Http::withToken($this->token)->post("{$this->apiUrl}/workflows", [
            'name' => 'High-Value Order Processing',
            'description' => 'Triggers when orders over $1000 are created',
            'status' => 'active',
            
            // TRIGGER CONFIGURATION
            'trigger_type' => 'model',
            'trigger_config' => [
                'model_class' => 'App\\Models\\Order',
                'events' => ['created'],  // created, updated, deleted, restored
                
                // Only trigger for high-value orders
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => 'model.total', 'operator' => '>', 'value' => 1000],
                        ['field' => 'model.status', 'operator' => '==', 'value' => 'pending'],
                    ],
                ],
            ],
            'auto_trigger_enabled' => true,

            // WORKFLOW STEPS
            'steps' => [
                [
                    'name' => 'Notify Sales Manager',
                    'type' => 'notification',
                    'configuration' => [
                        'notification_class' => 'App\\Notifications\\HighValueOrderAlert',
                        'recipients' => ['sales-manager@example.com'],
                        'data' => [
                            'order_id' => '{{model.id}}',
                            'total' => '{{model.total}}',
                            'customer_name' => '{{model.customer_name}}',
                        ],
                    ],
                    'position' => 1,
                ],
                [
                    'name' => 'Request Manual Verification',
                    'type' => 'action',
                    'configuration' => [
                        'action_class' => 'App\\Actions\\CreateVerificationRequest',
                        'parameters' => [
                            'order_id' => '{{model_id}}',
                        ],
                    ],
                    'position' => 2,
                ],
                [
                    'name' => 'Update CRM',
                    'type' => 'webhook',
                    'configuration' => [
                        'url' => 'https://crm.example.com/api/high-value-orders',
                        'method' => 'POST',
                        'headers' => ['Authorization' => 'Bearer crm-token'],
                        'payload' => [
                            'order_id' => '{{model.id}}',
                            'amount' => '{{model.total}}',
                            'customer_id' => '{{model.customer_id}}',
                        ],
                    ],
                    'position' => 3,
                ],
            ],
        ]);

        $workflow = $response->json('data');
        echo "✅ Model-triggered workflow created: {$workflow['id']}\n";
        echo "📊 Monitors: App\\Models\\Order (created)\n";
        echo "💰 Condition: total > $1000\n";
        
        return $workflow;
    }

    /**
     * Example 5: MULTI-EVENT MODEL TRIGGER - Order Status Tracking
     *
     * Tracks order status changes across multiple events.
     */
    public function createOrderStatusTrackingWorkflow()
    {
        $response = Http::withToken($this->token)->post("{$this->apiUrl}/workflows", [
            'name' => 'Order Status Change Tracking',
            'description' => 'Tracks order creation and status updates',
            'status' => 'active',
            
            // TRIGGER CONFIGURATION
            'trigger_type' => 'model',
            'trigger_config' => [
                'model_class' => 'App\\Models\\Order',
                'events' => ['created', 'updated'],
                
                // Trigger only if status field changed
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

            // WORKFLOW STEPS
            'steps' => [
                [
                    'name' => 'Log Status Change',
                    'type' => 'action',
                    'configuration' => [
                        'action_class' => 'App\\Actions\\LogOrderStatusChange',
                        'parameters' => [
                            'order_id' => '{{model_id}}',
                            'event_type' => '{{_event}}',
                            'new_status' => '{{model.status}}',
                            'changes' => '{{_changes}}',
                        ],
                    ],
                    'position' => 1,
                ],
                [
                    'name' => 'Notify Customer',
                    'type' => 'notification',
                    'configuration' => [
                        'notification_class' => 'App\\Notifications\\OrderStatusUpdate',
                        'recipients' => ['{{model.customer_email}}'],
                    ],
                    'conditions' => [
                        'operator' => 'and',
                        'rules' => [
                            ['field' => '_event', 'operator' => '==', 'value' => 'updated'],
                        ],
                    ],
                    'position' => 2,
                ],
            ],
        ]);

        $workflow = $response->json('data');
        echo "✅ Multi-event model workflow created: {$workflow['id']}\n";
        
        return $workflow;
    }

    /**
     * Example 6: COMPLEX SCHEDULE TRIGGER - Weekly Report with Conditions
     */
    public function createWeeklyReportWorkflow()
    {
        $response = Http::withToken($this->token)->post("{$this->apiUrl}/workflows", [
            'name' => 'Weekly Performance Report',
            'description' => 'Every Monday at 9 AM',
            'status' => 'active',
            
            'trigger_type' => 'schedule',
            'trigger_config' => [
                'cron_expression' => '0 9 * * 1',  // Every Monday at 9:00 AM
                'timezone' => 'UTC',
                'context' => [
                    'report_period' => 'week',
                    'send_if_empty' => false,
                ],
            ],
            'auto_trigger_enabled' => true,

            'steps' => [
                [
                    'name' => 'Generate Report Data',
                    'type' => 'action',
                    'configuration' => [
                        'action_class' => 'App\\Actions\\GenerateWeeklyReport',
                    ],
                    'position' => 1,
                ],
                [
                    'name' => 'Send Report',
                    'type' => 'notification',
                    'configuration' => [
                        'notification_class' => 'App\\Notifications\\WeeklyReport',
                        'recipients' => ['team@example.com'],
                    ],
                    'position' => 2,
                ],
            ],
        ]);

        return $response->json('data');
    }

    /**
     * Demonstrate updating trigger configuration
     */
    public function updateTriggerConfiguration(int $workflowId)
    {
        // Disable auto-trigger
        $response = Http::withToken($this->token)->put("{$this->apiUrl}/workflows/{$workflowId}", [
            'auto_trigger_enabled' => false,
        ]);

        echo "⏸️  Auto-trigger disabled\n";

        // Update schedule
        $response = Http::withToken($this->token)->put("{$this->apiUrl}/workflows/{$workflowId}", [
            'trigger_config' => [
                'cron_expression' => '0 10 * * *',  // Changed to 10 AM
                'timezone' => 'America/Los_Angeles',
            ],
        ]);

        echo "⏰ Schedule updated to 10 AM PST\n";

        // Re-enable
        $response = Http::withToken($this->token)->put("{$this->apiUrl}/workflows/{$workflowId}", [
            'auto_trigger_enabled' => true,
        ]);

        echo "▶️  Auto-trigger re-enabled\n";
    }

    /**
     * Test webhook trigger manually
     */
    public function testWebhookTrigger(string $webhookUrl, array $payload)
    {
        $response = Http::post($webhookUrl, $payload);

        if ($response->successful()) {
            $result = $response->json();
            echo "✅ Webhook triggered successfully\n";
            echo "📋 Execution ID: {$result['execution_id']}\n";
            echo "📊 Status: {$result['status']}\n";
            
            return $result;
        } else {
            echo "❌ Webhook trigger failed\n";
            echo "Error: {$response->body()}\n";
            
            return null;
        }
    }

    /**
     * Run all examples
     */
    public function runAllExamples()
    {
        echo "🚀 Creating workflow triggers...\n\n";

        echo "1️⃣  EVENT TRIGGER\n";
        echo str_repeat('-', 50)."\n";
        $eventWorkflow = $this->createUserRegistrationWorkflow();
        echo "\n";

        echo "2️⃣  SCHEDULE TRIGGER\n";
        echo str_repeat('-', 50)."\n";
        $scheduleWorkflow = $this->createDailySalesReportWorkflow();
        echo "\n";

        echo "3️⃣  WEBHOOK TRIGGER\n";
        echo str_repeat('-', 50)."\n";
        $webhookWorkflow = $this->createStripePaymentWorkflow();
        echo "\n";

        echo "4️⃣  MODEL TRIGGER\n";
        echo str_repeat('-', 50)."\n";
        $modelWorkflow = $this->createHighValueOrderWorkflow();
        echo "\n";

        echo "5️⃣  MULTI-EVENT MODEL TRIGGER\n";
        echo str_repeat('-', 50)."\n";
        $multiEventWorkflow = $this->createOrderStatusTrackingWorkflow();
        echo "\n";

        echo "✅ All workflows created successfully!\n";
        echo "\n";
        echo "📚 Summary:\n";
        echo "- Event-triggered: {$eventWorkflow['id']}\n";
        echo "- Schedule-triggered: {$scheduleWorkflow['id']}\n";
        echo "- Webhook-triggered: {$webhookWorkflow['id']}\n";
        echo "- Model-triggered: {$modelWorkflow['id']}\n";
        echo "- Multi-event: {$multiEventWorkflow['id']}\n";
    }
}

// Usage
$guide = new WorkflowTriggersGuide('your-api-token-here');
$guide->runAllExamples();
