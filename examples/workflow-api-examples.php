<?php

/**
 * Workflow API Usage Examples
 *
 * This file demonstrates how to use the ForgePulse Workflow API
 * to create, update, and manage workflows programmatically.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */

use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Setup
|--------------------------------------------------------------------------
|
| First, generate an API token for authentication:
*/

// Generate token (run once)
$token = auth()->user()->createToken('workflow-api')->plainTextToken;
// Store this token securely - it will only be shown once

// API base URL
$apiUrl = 'https://your-app.com/api/forgepulse';

/*
|--------------------------------------------------------------------------
| Example 1: Create a Simple Workflow
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Simple Order Processing',
    'description' => 'Process incoming orders',
    'status' => 'active',
]);

$workflow = $response->json('data');
echo "Created workflow ID: {$workflow['id']}\n";

/*
|--------------------------------------------------------------------------
| Example 2: Create Workflow with Steps
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'User Onboarding Flow',
    'description' => 'Comprehensive user onboarding process',
    'status' => 'active',
    'steps' => [
        [
            'name' => 'Send Welcome Email',
            'description' => 'Send welcome email to new user',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\WelcomeEmail',
                'recipients' => ['{{user_id}}'],
            ],
            'position' => 1,
        ],
        [
            'name' => 'Wait 24 Hours',
            'description' => 'Wait before sending follow-up',
            'type' => 'delay',
            'configuration' => [
                'seconds' => 86400,
            ],
            'position' => 2,
        ],
        [
            'name' => 'Send Follow-up Email',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\FollowUpEmail',
                'recipients' => ['{{user_id}}'],
            ],
            'position' => 3,
        ],
    ],
]);

$onboardingWorkflow = $response->json('data');
echo "Created onboarding workflow with {$onboardingWorkflow['steps_count']} steps\n";

/*
|--------------------------------------------------------------------------
| Example 3: Create Workflow with Conditional Steps
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Premium User Workflow',
    'status' => 'active',
    'steps' => [
        [
            'name' => 'Check User Role',
            'type' => 'condition',
            'configuration' => [
                'pass_message' => 'User is premium',
                'fail_message' => 'User is not premium',
            ],
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    [
                        'field' => 'user.role',
                        'operator' => '==',
                        'value' => 'premium',
                    ],
                ],
            ],
            'position' => 1,
        ],
        [
            'name' => 'Send Premium Features Email',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\PremiumFeaturesEmail',
            ],
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    [
                        'field' => 'user.role',
                        'operator' => '==',
                        'value' => 'premium',
                    ],
                ],
            ],
            'position' => 2,
        ],
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 4: Create Workflow with Webhook Integration
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'External API Integration',
    'status' => 'active',
    'steps' => [
        [
            'name' => 'Call External API',
            'type' => 'webhook',
            'configuration' => [
                'url' => 'https://api.external-service.com/webhook',
                'method' => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer external-api-token',
                    'Content-Type' => 'application/json',
                ],
                'payload' => [
                    'user_id' => '{{user_id}}',
                    'event' => 'user_registered',
                    'timestamp' => '{{timestamp}}',
                ],
            ],
            'position' => 1,
            'timeout' => 30,
        ],
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 5: Create Workflow with Parallel Execution
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Multi-Channel Notifications',
    'status' => 'active',
    'steps' => [
        [
            'name' => 'Send Email Notification',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\EmailNotification',
            ],
            'position' => 1,
            'execution_mode' => 'parallel',
            'parallel_group' => 'notifications',
        ],
        [
            'name' => 'Send SMS Notification',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\SmsNotification',
            ],
            'position' => 2,
            'execution_mode' => 'parallel',
            'parallel_group' => 'notifications',
        ],
        [
            'name' => 'Send Push Notification',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\PushNotification',
            ],
            'position' => 3,
            'execution_mode' => 'parallel',
            'parallel_group' => 'notifications',
        ],
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 6: Update Workflow
|--------------------------------------------------------------------------
*/

// Update workflow metadata
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'name' => 'Updated Order Processing',
    'description' => 'Enhanced order processing with validation',
    'status' => 'inactive',
]);

echo "Updated workflow: {$response->json('data.name')}\n";

/*
|--------------------------------------------------------------------------
| Example 7: Update Workflow Steps
|--------------------------------------------------------------------------
*/

// Get existing workflow first
$workflow = Http::withToken($token)->get("{$apiUrl}/workflows/1")->json('data');

// Update existing step and add new one
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'steps' => [
        [
            'id' => $workflow['steps'][0]['id'], // Update existing step
            'name' => 'Updated Step Name',
            'type' => $workflow['steps'][0]['type'],
            'configuration' => $workflow['steps'][0]['configuration'],
            'position' => 1,
        ],
        [
            // New step (no ID)
            'name' => 'Additional Validation',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ValidateOrder',
            ],
            'position' => 2,
        ],
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 8: Create Workflow Template
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Standard Approval Process Template',
    'description' => 'Reusable approval workflow template',
    'status' => 'active',
    'is_template' => true,
    'steps' => [
        [
            'name' => 'Submit for Approval',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\SubmitForApproval',
            ],
            'position' => 1,
        ],
        [
            'name' => 'Wait for Approval',
            'type' => 'delay',
            'configuration' => [
                'seconds' => 3600,
            ],
            'position' => 2,
        ],
        [
            'name' => 'Process Approval',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessApproval',
            ],
            'position' => 3,
        ],
    ],
]);

$template = $response->json('data');
echo "Created template: {$template['name']}\n";

/*
|--------------------------------------------------------------------------
| Example 9: List Workflows with Pagination
|--------------------------------------------------------------------------
*/

$page = 1;
$response = Http::withToken($token)->get("{$apiUrl}/workflows", [
    'page' => $page,
]);

$workflows = $response->json('data');
$meta = $response->json('meta');

echo "Found {$meta['total']} workflows\n";
foreach ($workflows as $wf) {
    echo "- {$wf['name']} ({$wf['status']})\n";
}

/*
|--------------------------------------------------------------------------
| Example 10: Get Workflow Details
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->get("{$apiUrl}/workflows/1");
$workflow = $response->json('data');

echo "Workflow: {$workflow['name']}\n";
echo "Status: {$workflow['status']}\n";
echo "Steps: {$workflow['steps_count']}\n";
echo "Executions: {$workflow['executions_count']}\n";

// Access steps
foreach ($workflow['steps'] as $step) {
    echo "  - Step {$step['position']}: {$step['name']} ({$step['type']})\n";
}

/*
|--------------------------------------------------------------------------
| Example 11: Delete Workflow
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->delete("{$apiUrl}/workflows/{$workflow['id']}");

if ($response->successful()) {
    echo $response->json('message')."\n";
}

/*
|--------------------------------------------------------------------------
| Example 12: Error Handling
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'description' => 'Missing required name field',
]);

if ($response->failed()) {
    if ($response->status() === 422) {
        // Validation error
        $errors = $response->json('errors');
        echo "Validation errors:\n";
        foreach ($errors as $field => $messages) {
            echo "- {$field}: ".implode(', ', $messages)."\n";
        }
    } elseif ($response->status() === 401) {
        echo "Authentication failed\n";
    } elseif ($response->status() === 403) {
        echo "Unauthorized action\n";
    } elseif ($response->status() === 404) {
        echo "Workflow not found\n";
    }
}

/*
|--------------------------------------------------------------------------
| Example 13: Complex Conditional Workflow
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Order Processing with Complex Rules',
    'status' => 'active',
    'steps' => [
        [
            'name' => 'Validate High-Value Order',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ValidateOrder',
            ],
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    [
                        'field' => 'order.total',
                        'operator' => '>',
                        'value' => 1000,
                    ],
                    [
                        'field' => 'user.verified',
                        'operator' => '==',
                        'value' => true,
                    ],
                ],
            ],
            'position' => 1,
        ],
        [
            'name' => 'Notify Admin for Large Orders',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\AdminAlert',
            ],
            'conditions' => [
                'operator' => 'or',
                'rules' => [
                    [
                        'field' => 'order.total',
                        'operator' => '>',
                        'value' => 5000,
                    ],
                    [
                        'field' => 'order.items_count',
                        'operator' => '>',
                        'value' => 50,
                    ],
                ],
            ],
            'position' => 2,
        ],
    ],
]);

/*
|--------------------------------------------------------------------------
| Example 14: Using with Laravel Jobs
|--------------------------------------------------------------------------
*/

use App\Jobs\CreateWorkflowJob;
use Illuminate\Support\Facades\Queue;

// Queue workflow creation for background processing
Queue::push(new CreateWorkflowJob([
    'name' => 'Background Workflow',
    'status' => 'draft',
    'steps' => [...],
], $token));

/*
|--------------------------------------------------------------------------
| Example 15: Bulk Operations (Custom Implementation)
|--------------------------------------------------------------------------
*/

// Create multiple workflows
$workflowsToCreate = [
    [
        'name' => 'Workflow 1',
        'status' => 'active',
    ],
    [
        'name' => 'Workflow 2',
        'status' => 'active',
    ],
    [
        'name' => 'Workflow 3',
        'status' => 'draft',
    ],
];

$createdWorkflows = [];
foreach ($workflowsToCreate as $workflowData) {
    $response = Http::withToken($token)->post("{$apiUrl}/workflows", $workflowData);
    if ($response->successful()) {
        $createdWorkflows[] = $response->json('data');
    }
}

echo "Created ".count($createdWorkflows)." workflows\n";
