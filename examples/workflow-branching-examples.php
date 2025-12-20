<?php

/**
 * Workflow Branching Examples
 *
 * Demonstrates how to create branching workflows using parent_step_id
 * and conditional logic.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */

use Illuminate\Support\Facades\Http;

$token = 'your-api-token';
$apiUrl = 'https://your-app.com/api/forgepulse';

/*
|--------------------------------------------------------------------------
| Example 1: Simple If/Else Branch
|--------------------------------------------------------------------------
|
| Workflow Structure:
|   1. Check User Role (root)
|      ├─ 2. Premium User Path (child, condition: role == premium)
|      └─ 3. Free User Path (child, condition: role == free)
*/

echo "Example 1: Simple If/Else Branch\n";

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'User Onboarding with Branching',
    'status' => 'active',
    'steps' => [
        [
            'name' => 'Check User Role',
            'type' => 'condition',
            'configuration' => [
                'pass_message' => 'Role checked',
            ],
            'position' => 1,
            'parent_step_id' => null, // Root step
        ],
    ],
]);

$workflow = $response->json('data');
$checkRoleStepId = $workflow['steps'][0]['id'];

// Now add the child branches
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'steps' => [
        // Branch A: Premium User
        [
            'name' => 'Send Premium Welcome Email',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\PremiumWelcome',
            ],
            'position' => 1,
            'parent_step_id' => $checkRoleStepId, // Child of "Check User Role"
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'user.role', 'operator' => '==', 'value' => 'premium'],
                ],
            ],
        ],
        // Branch B: Free User
        [
            'name' => 'Send Free Welcome Email',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\FreeWelcome',
            ],
            'position' => 2,
            'parent_step_id' => $checkRoleStepId, // Also child of "Check User Role"
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'user.role', 'operator' => '==', 'value' => 'free'],
                ],
            ],
        ],
    ],
]);

echo "Created branching workflow with 2 paths\n\n";

/*
|--------------------------------------------------------------------------
| Example 2: Multi-Branch Decision Tree
|--------------------------------------------------------------------------
|
| Workflow Structure:
|   1. Validate Order (root)
|      ├─ 2. Small Order (<$100)
|      ├─ 3. Medium Order ($100-$1000)
|      └─ 4. Large Order (>$1000)
|           └─ 5. Require Manager Approval (sub-branch)
*/

echo "Example 2: Multi-Branch Decision Tree\n";

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Order Processing Decision Tree',
    'status' => 'active',
    'steps' => [
        [
            'name' => 'Validate Order',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ValidateOrder',
            ],
            'position' => 1,
            'parent_step_id' => null,
        ],
    ],
]);

$workflow = $response->json('data');
$validateStepId = $workflow['steps'][0]['id'];

// Add three branches based on order total
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'steps' => [
        // Branch 1: Small Order
        [
            'name' => 'Process Small Order',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessSmallOrder',
            ],
            'position' => 1,
            'parent_step_id' => $validateStepId,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'order.total', 'operator' => '<', 'value' => 100],
                ],
            ],
        ],
        // Branch 2: Medium Order
        [
            'name' => 'Process Medium Order',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessMediumOrder',
            ],
            'position' => 2,
            'parent_step_id' => $validateStepId,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'order.total', 'operator' => '>=', 'value' => 100],
                    ['field' => 'order.total', 'operator' => '<=', 'value' => 1000],
                ],
            ],
        ],
        // Branch 3: Large Order
        [
            'name' => 'Process Large Order',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessLargeOrder',
            ],
            'position' => 3,
            'parent_step_id' => $validateStepId,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'order.total', 'operator' => '>', 'value' => 1000],
                ],
            ],
        ],
    ],
]);

$largeOrderStepId = $response->json('data.steps')[2]['id'];

// Add sub-branch to Large Order path
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'steps' => [
        [
            'name' => 'Require Manager Approval',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\ManagerApprovalRequired',
            ],
            'position' => 1,
            'parent_step_id' => $largeOrderStepId, // Child of "Process Large Order"
        ],
    ],
]);

echo "Created decision tree with 3 branches + nested approval step\n\n";

/*
|--------------------------------------------------------------------------
| Example 3: Parallel Branches (No Conditions)
|--------------------------------------------------------------------------
|
| Workflow Structure:
|   1. User Registered (root)
|      ├─ 2. Send Welcome Email
|      ├─ 3. Send SMS
|      └─ 4. Create Profile
*/

echo "Example 3: Parallel Branches (No Conditions)\n";

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Multi-Channel Onboarding',
    'status' => 'active',
    'steps' => [
        [
            'name' => 'User Registered',
            'type' => 'event',
            'configuration' => [
                'event_class' => 'App\\Events\\UserRegistered',
            ],
            'position' => 1,
            'parent_step_id' => null,
        ],
    ],
]);

$workflow = $response->json('data');
$registeredStepId = $workflow['steps'][0]['id'];

// Add parallel child steps (all execute)
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'steps' => [
        [
            'name' => 'Send Welcome Email',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\WelcomeEmail',
            ],
            'position' => 1,
            'parent_step_id' => $registeredStepId,
            'execution_mode' => 'parallel',
            'parallel_group' => 'onboarding',
        ],
        [
            'name' => 'Send Welcome SMS',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\WelcomeSMS',
            ],
            'position' => 2,
            'parent_step_id' => $registeredStepId,
            'execution_mode' => 'parallel',
            'parallel_group' => 'onboarding',
        ],
        [
            'name' => 'Create User Profile',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\CreateUserProfile',
            ],
            'position' => 3,
            'parent_step_id' => $registeredStepId,
        ],
    ],
]);

echo "Created parallel execution branches\n\n";

/*
|--------------------------------------------------------------------------
| Example 4: Complex Branching with Convergence
|--------------------------------------------------------------------------
|
| Workflow Structure:
|   1. Start Order (root)
|   2. Check Payment Method (root)
|      ├─ 3. Process Credit Card
|      ├─ 4. Process PayPal
|      └─ 5. Process Bank Transfer
|   6. Send Receipt (root) ← All branches lead here
*/

echo "Example 4: Complex Branching with Convergence\n";

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Payment Processing with Convergence',
    'status' => 'active',
    'steps' => [
        [
            'name' => 'Start Order',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\StartOrder',
            ],
            'position' => 1,
            'parent_step_id' => null,
        ],
        [
            'name' => 'Check Payment Method',
            'type' => 'condition',
            'configuration' => [],
            'position' => 2,
            'parent_step_id' => null,
        ],
        // Convergence point - runs after any branch
        [
            'name' => 'Send Receipt',
            'type' => 'notification',
            'configuration' => [
                'notification_class' => 'App\\Notifications\\OrderReceipt',
            ],
            'position' => 3,
            'parent_step_id' => null, // Root level = convergence
        ],
    ],
]);

$workflow = $response->json('data');
$checkPaymentStepId = $workflow['steps'][1]['id'];

// Add payment method branches
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'steps' => [
        [
            'name' => 'Process Credit Card',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessCreditCard',
            ],
            'position' => 1,
            'parent_step_id' => $checkPaymentStepId,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'payment.method', 'operator' => '==', 'value' => 'card'],
                ],
            ],
        ],
        [
            'name' => 'Process PayPal',
            'type' => 'webhook',
            'configuration' => [
                'url' => 'https://api.paypal.com/process',
                'method' => 'POST',
            ],
            'position' => 2,
            'parent_step_id' => $checkPaymentStepId,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'payment.method', 'operator' => '==', 'value' => 'paypal'],
                ],
            ],
        ],
        [
            'name' => 'Process Bank Transfer',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessBankTransfer',
            ],
            'position' => 3,
            'parent_step_id' => $checkPaymentStepId,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'payment.method', 'operator' => '==', 'value' => 'bank'],
                ],
            ],
        ],
    ],
]);

echo "Created branching with convergence (all paths lead to 'Send Receipt')\n\n";

/*
|--------------------------------------------------------------------------
| Example 5: Nested Branching (Branch within Branch)
|--------------------------------------------------------------------------
|
| Workflow Structure:
|   1. Check User Type (root)
|      └─ 2. Premium User (condition: type == premium)
|           ├─ 3. Annual Subscription (condition: plan == annual)
|           └─ 4. Monthly Subscription (condition: plan == monthly)
*/

echo "Example 5: Nested Branching\n";

$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Nested Subscription Logic',
    'status' => 'active',
    'steps' => [
        [
            'name' => 'Check User Type',
            'type' => 'condition',
            'configuration' => [],
            'position' => 1,
            'parent_step_id' => null,
        ],
    ],
]);

$workflow = $response->json('data');
$checkUserTypeStepId = $workflow['steps'][0]['id'];

// First level branch
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'steps' => [
        [
            'name' => 'Premium User Processing',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessPremiumUser',
            ],
            'position' => 1,
            'parent_step_id' => $checkUserTypeStepId,
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'user.type', 'operator' => '==', 'value' => 'premium'],
                ],
            ],
        ],
    ],
]);

$premiumStepId = $response->json('data.steps')[0]['id'];

// Second level branches (nested under Premium)
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'steps' => [
        [
            'name' => 'Process Annual Subscription',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessAnnualSubscription',
            ],
            'position' => 1,
            'parent_step_id' => $premiumStepId, // Nested under Premium step
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'subscription.plan', 'operator' => '==', 'value' => 'annual'],
                ],
            ],
        ],
        [
            'name' => 'Process Monthly Subscription',
            'type' => 'action',
            'configuration' => [
                'action_class' => 'App\\Actions\\ProcessMonthlySubscription',
            ],
            'position' => 2,
            'parent_step_id' => $premiumStepId, // Also nested under Premium step
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'subscription.plan', 'operator' => '==', 'value' => 'monthly'],
                ],
            ],
        ],
    ],
]);

echo "Created nested branching (branch within branch)\n\n";

/*
|--------------------------------------------------------------------------
| Example 6: Creating All Steps in One Request
|--------------------------------------------------------------------------
|
| Note: When creating steps with parent_step_id in a single request,
| you cannot reference step IDs that don't exist yet. 
| Workaround: Create in multiple requests or use position-based logic.
*/

echo "Example 6: Creating Complete Branching Workflow (Multi-Request)\n";

// This is the recommended approach for complex branching
$response = Http::withToken($token)->post("{$apiUrl}/workflows", [
    'name' => 'Complete Approval Workflow',
    'status' => 'draft', // Draft while building
    'steps' => [
        // Root steps only
        [
            'name' => 'Submit Request',
            'type' => 'action',
            'configuration' => ['action_class' => 'App\\Actions\\SubmitRequest'],
            'position' => 1,
            'parent_step_id' => null,
        ],
        [
            'name' => 'Check Amount',
            'type' => 'condition',
            'configuration' => [],
            'position' => 2,
            'parent_step_id' => null,
        ],
    ],
]);

$workflow = $response->json('data');
$checkAmountStepId = $workflow['steps'][1]['id'];

// Add child branches
$response = Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'steps' => [
        [
            'name' => 'Auto-Approve Small Amount',
            'type' => 'action',
            'configuration' => ['action_class' => 'App\\Actions\\AutoApprove'],
            'position' => 1,
            'parent_step_id' => $checkAmountStepId,
            'conditions' => [
                'operator' => 'and',
                'rules' => [['field' => 'amount', 'operator' => '<', 'value' => 500]],
            ],
        ],
        [
            'name' => 'Require Manager Approval',
            'type' => 'notification',
            'configuration' => ['notification_class' => 'App\\Notifications\\ManagerApproval'],
            'position' => 2,
            'parent_step_id' => $checkAmountStepId,
            'conditions' => [
                'operator' => 'and',
                'rules' => [['field' => 'amount', 'operator' => '>=', 'value' => 500]],
            ],
        ],
    ],
]);

// Activate once complete
Http::withToken($token)->put("{$apiUrl}/workflows/{$workflow['id']}", [
    'status' => 'active',
]);

echo "Created complete approval workflow with branches\n\n";

echo "=== Summary ===\n";
echo "Demonstrated 6 branching patterns:\n";
echo "1. Simple If/Else (2 branches)\n";
echo "2. Multi-branch decision tree (3+ branches)\n";
echo "3. Parallel execution (all branches run)\n";
echo "4. Convergence (branches rejoin)\n";
echo "5. Nested branching (branch within branch)\n";
echo "6. Complete workflow creation strategy\n\n";

echo "Key Concepts:\n";
echo "- parent_step_id: Links child steps to parent\n";
echo "- position: Orders siblings at same level\n";
echo "- conditions: Determines which branch executes\n";
echo "- Root steps (parent_step_id = null): Top-level flow\n";
echo "- Convergence: Multiple root steps = paths rejoin\n\n";
