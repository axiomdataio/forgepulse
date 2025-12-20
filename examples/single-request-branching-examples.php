<?php

/**
 * Single-Request Branching Examples
 *
 * Demonstrates creating complete branching workflows in one API request
 * using step_identifier and parent_step_identifier.
 */

use Illuminate\Support\Facades\Http;

$token = 'your-api-token';
$apiUrl = 'https://your-app.com/api/forgepulse';

/*
|--------------------------------------------------------------------------
| Example 1: Simple If/Else in One Request
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Order Approval Workflow',
    'status' => 'active',
    'steps' => [
        // Parent step
        [
            'step_identifier' => 'check_amount',
            'name' => 'Check Order Amount',
            'type' => 'condition',
            'configuration' => [],
            'position' => 1,
        ],
        // Branch A: Small orders
        [
            'step_identifier' => 'auto_approve',
            'parent_step_identifier' => 'check_amount',
            'name' => 'Auto-Approve Small Order',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\AutoApprove',
            ],
            'position' => 1,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'order.total', 'operator' => '<', 'value' => 500],
                ],
            ],
        ],
        // Branch B: Large orders
        [
            'step_identifier' => 'manager_approval',
            'parent_step_identifier' => 'check_amount',
            'name' => 'Require Manager Approval',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\ManagerApproval',
            ],
            'position' => 2,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'order.total', 'operator' => '>=', 'value' => 500],
                ],
            ],
        ],
    ],
]);

echo "Created workflow in one request!\n";
$workflow = $response->json('data');
echo "Workflow ID: {$workflow['id']}\n";
echo "Steps created: " . count($workflow['steps']) . "\n\n";

/*
|--------------------------------------------------------------------------
| Example 2: Multi-Branch Payment Processing
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Payment Processing',
    'status' => 'active',
    'steps' => [
        [
            'step_identifier' => 'start',
            'name' => 'Start Payment',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\StartPayment',
            ],
            'position' => 1,
        ],
        [
            'step_identifier' => 'check_method',
            'parent_step_identifier' => 'start',
            'name' => 'Check Payment Method',
            'type' => 'condition',
            'configuration' => [],
            'position' => 1,
        ],
        [
            'step_identifier' => 'card',
            'parent_step_identifier' => 'check_method',
            'name' => 'Process Credit Card',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessCreditCard',
            ],
            'position' => 1,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'payment.method', 'operator' => '==', 'value' => 'card'],
                ],
            ],
        ],
        [
            'step_identifier' => 'paypal',
            'parent_step_identifier' => 'check_method',
            'name' => 'Process PayPal',
            'type' => 'webhook',
            'configuration' => [
                'url' => 'https://api.paypal.com/process',
                'method' => 'POST',
            ],
            'position' => 2,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'payment.method', 'operator' => '==', 'value' => 'paypal'],
                ],
            ],
        ],
        [
            'step_identifier' => 'bank',
            'parent_step_identifier' => 'check_method',
            'name' => 'Process Bank Transfer',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessBankTransfer',
            ],
            'position' => 3,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'payment.method', 'operator' => '==', 'value' => 'bank'],
                ],
            ],
        ],
        [
            'step_identifier' => 'receipt',
            'name' => 'Send Receipt',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\OrderReceipt',
            ],
            'position' => 2,
        ],
    ],
]);

echo "Created payment workflow with 3 payment methods in one request!\n\n";

/*
|--------------------------------------------------------------------------
| Example 3: Nested Branching (Branch within Branch)
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Nested Subscription Logic',
    'status' => 'active',
    'steps' => [
        // Level 1: Root
        [
            'step_identifier' => 'check_type',
            'name' => 'Check User Type',
            'type' => 'condition',
            'configuration' => [],
            'position' => 1,
        ],
        // Level 2: Premium branch
        [
            'step_identifier' => 'premium',
            'parent_step_identifier' => 'check_type',
            'name' => 'Premium User Processing',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessPremiumUser',
            ],
            'position' => 1,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'user.type', 'operator' => '==', 'value' => 'premium'],
                ],
            ],
        ],
        // Level 3: Nested under Premium - Annual
        [
            'step_identifier' => 'annual',
            'parent_step_identifier' => 'premium',
            'name' => 'Process Annual Subscription',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessAnnualSubscription',
            ],
            'position' => 1,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'subscription.plan', 'operator' => '==', 'value' => 'annual'],
                ],
            ],
        ],
        // Level 3: Nested under Premium - Monthly
        [
            'step_identifier' => 'monthly',
            'parent_step_identifier' => 'premium',
            'name' => 'Process Monthly Subscription',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessMonthlySubscription',
            ],
            'position' => 2,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'subscription.plan', 'operator' => '==', 'value' => 'monthly'],
                ],
            ],
        ],
        // Level 2: Free branch
        [
            'step_identifier' => 'free',
            'parent_step_identifier' => 'check_type',
            'name' => 'Free User Processing',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessFreeUser',
            ],
            'position' => 2,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'user.type', 'operator' => '==', 'value' => 'free'],
                ],
            ],
        ],
    ],
]);

echo "Created nested branching workflow in one request!\n\n";

/*
|--------------------------------------------------------------------------
| Example 4: Complex Real-World Example
|--------------------------------------------------------------------------
*/

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'E-Commerce Order Processing',
    'status' => 'active',
    'steps' => [
        [
            'step_identifier' => 'validate',
            'name' => 'Validate Order',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ValidateOrder',
            ],
            'position' => 1,
        ],
        [
            'step_identifier' => 'check_inventory',
            'parent_step_identifier' => 'validate',
            'name' => 'Check Inventory',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\CheckInventory',
            ],
            'position' => 1,
        ],
        [
            'step_identifier' => 'in_stock',
            'parent_step_identifier' => 'check_inventory',
            'name' => 'In Stock Processing',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessInStock',
            ],
            'position' => 1,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'inventory.status', 'operator' => '==', 'value' => 'in_stock'],
                ],
            ],
        ],
        [
            'step_identifier' => 'backorder',
            'parent_step_identifier' => 'check_inventory',
            'name' => 'Backorder Processing',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessBackorder',
            ],
            'position' => 2,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'inventory.status', 'operator' => '==', 'value' => 'backorder'],
                ],
            ],
        ],
        [
            'step_identifier' => 'notify_customer',
            'parent_step_identifier' => 'backorder',
            'name' => 'Notify Customer of Delay',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\BackorderNotification',
            ],
            'position' => 1,
        ],
        [
            'step_identifier' => 'process_payment',
            'name' => 'Process Payment',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessPayment',
            ],
            'position' => 2,
        ],
        [
            'step_identifier' => 'ship_order',
            'parent_step_identifier' => 'process_payment',
            'name' => 'Ship Order',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ShipOrder',
            ],
            'position' => 1,
        ],
        [
            'step_identifier' => 'send_tracking',
            'parent_step_identifier' => 'ship_order',
            'name' => 'Send Tracking Information',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\TrackingEmail',
            ],
            'position' => 1,
        ],
    ],
]);

echo "Created complex e-commerce workflow with multiple branches!\n\n";

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

echo "=== Summary ===\n";
echo "✅ All workflows created in single API requests\n";
echo "✅ No need to parse responses or make multiple calls\n";
echo "✅ User-defined identifiers make relationships clear\n";
echo "✅ Atomic transactions ensure data integrity\n";
echo "\nKey Features:\n";
echo "- step_identifier: User-defined ID for each step\n";
echo "- parent_step_identifier: Reference to parent's identifier\n";
echo "- Automatic resolution to database IDs\n";
echo "- Validation for duplicates and invalid references\n";
