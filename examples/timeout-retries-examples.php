<?php

/**
 * ForgePulse v2.0+ - Configurable Timeout and Retries Example
 *
 * Demonstrates the three-level priority system for timeouts and retries.
 */

use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Enums\StepType;
use AlizHarb\ForgePulse\Enums\WorkflowStatus;

echo "\n=== Configurable Timeout & Retries Example ===\n\n";

/*
|--------------------------------------------------------------------------
| Example 1: Using Global Defaults Only
|--------------------------------------------------------------------------
*/

echo "1. Using Global Defaults (No Overrides)\n";

$workflow1 = Workflow::create([
    'name' => 'Standard Workflow',
    'status' => WorkflowStatus::ACTIVE,
    // No timeout or max_retries set - uses config defaults
]);

$workflow1->steps()->create([
    'name' => 'Standard Step',
    'type' => StepType::ACTION,
    'position' => 1,
    // No timeout or max_retries - uses config: 300s, 3 retries
    'configuration' => [
        'class' => 'App\Services\StandardService',
        'method' => 'process',
    ],
]);

echo "   Created workflow with global defaults\n";
echo "   Step will use: timeout=300s, max_retries=3\n\n";

/*
|--------------------------------------------------------------------------
| Example 2: Workflow-Level Override
|--------------------------------------------------------------------------
*/

echo "2. Workflow-Level Override\n";

$workflow2 = Workflow::create([
    'name' => 'High-Priority Workflow',
    'status' => WorkflowStatus::ACTIVE,
    'timeout' => 600,          // 10 minutes for ALL steps
    'max_retries' => 10,       // 10 retries for ALL steps
]);

$workflow2->steps()->create([
    'name' => 'Step 1',
    'type' => StepType::ACTION,
    'position' => 1,
    // No override - inherits workflow settings
    'configuration' => [
        'class' => 'App\Services\Step1Service',
        'method' => 'process',
    ],
]);

$workflow2->steps()->create([
    'name' => 'Step 2',
    'type' => StepType::ACTION,
    'position' => 2,
    // No override - also inherits workflow settings
    'configuration' => [
        'class' => 'App\Services\Step2Service',
        'method' => 'process',
    ],
]);

echo "   Created workflow with workflow-level settings\n";
echo "   All steps will use: timeout=600s, max_retries=10\n\n";

/*
|--------------------------------------------------------------------------
| Example 3: Step-Level Override (Highest Priority)
|--------------------------------------------------------------------------
*/

echo "3. Step-Level Override (Most Specific)\n";

$workflow3 = Workflow::create([
    'name' => 'Mixed Priority Workflow',
    'status' => WorkflowStatus::ACTIVE,
    'timeout' => 300,          // Default for most steps
    'max_retries' => 5,
]);

// Fast validation step
$workflow3->steps()->create([
    'name' => 'Quick Validation',
    'type' => StepType::ACTION,
    'position' => 1,
    'timeout' => 30,           // Override: just 30 seconds
    'max_retries' => 2,        // Override: only 2 tries
    'configuration' => [
        'class' => 'App\Services\ValidationService',
        'method' => 'quickCheck',
    ],
]);

// Critical payment step
$workflow3->steps()->create([
    'name' => 'Process Payment',
    'type' => StepType::ACTION,
    'position' => 2,
    'timeout' => 120,          // Override: 2 minutes
    'max_retries' => 15,       // Override: 15 tries (critical!)
    'configuration' => [
        'class' => 'App\Services\PaymentService',
        'method' => 'processPayment',
    ],
]);

// Standard step (uses workflow defaults)
$workflow3->steps()->create([
    'name' => 'Send Confirmation',
    'type' => StepType::NOTIFICATION,
    'position' => 3,
    // No overrides - uses workflow: timeout=300s, max_retries=5
    'configuration' => [
        'notification_class' => 'App\Notifications\OrderConfirmed',
    ],
]);

echo "   Created workflow with mixed priorities:\n";
echo "   - Step 1: timeout=30s, max_retries=2 (step override)\n";
echo "   - Step 2: timeout=120s, max_retries=15 (step override)\n";
echo "   - Step 3: timeout=300s, max_retries=5 (workflow default)\n\n";

/*
|--------------------------------------------------------------------------
| Example 4: Partial Override
|--------------------------------------------------------------------------
*/

echo "4. Partial Override (Mix of Levels)\n";

$workflow4 = Workflow::create([
    'name' => 'Partial Override Example',
    'status' => WorkflowStatus::ACTIVE,
    'timeout' => 180,          // 3 minutes default
    'max_retries' => 5,        // 5 retries default
]);

$workflow4->steps()->create([
    'name' => 'API Call',
    'type' => StepType::ACTION,
    'position' => 1,
    'timeout' => 60,           // Override timeout only
    // max_retries not set - uses workflow default (5)
    'configuration' => [
        'class' => 'App\Services\ApiService',
        'method' => 'fetchData',
    ],
]);

echo "   Created workflow with partial override:\n";
echo "   Step uses: timeout=60s (override), max_retries=5 (workflow)\n\n";

/*
|--------------------------------------------------------------------------
| Example 5: Vapor-Optimized Configuration
|--------------------------------------------------------------------------
*/

echo "5. Vapor-Optimized Workflow\n";

$vaporWorkflow = Workflow::create([
    'name' => 'Vapor-Safe Workflow',
    'status' => WorkflowStatus::ACTIVE,
    'timeout' => 300,          // 5 minutes (safe for Lambda)
    'max_retries' => 3,
]);

// All steps stay under Lambda timeout
$vaporWorkflow->steps()->create([
    'name' => 'Validate',
    'type' => StepType::ACTION,
    'position' => 1,
    'timeout' => 30,           // Quick validation
    'configuration' => [...],
]);

$vaporWorkflow->steps()->create([
    'name' => 'Process',
    'type' => StepType::ACTION,
    'position' => 2,
    'timeout' => 120,          // Main processing
    'configuration' => [...],
]);

$vaporWorkflow->steps()->create([
    'name' => 'Finalize',
    'type' => StepType::ACTION,
    'position' => 3,
    'timeout' => 60,           // Cleanup
    'configuration' => [...],
]);

echo "   Created Vapor-safe workflow:\n";
echo "   All steps < 5 minutes (safe for Lambda)\n";
echo "   Total workflow time: 3.5 minutes (well under 15 min limit)\n\n";

/*
|--------------------------------------------------------------------------
| Example 6: Execution with Custom Settings
|--------------------------------------------------------------------------
*/

echo "6. Execute Workflow with Custom Settings\n";

$testWorkflow = Workflow::create([
    'name' => 'Test Workflow',
    'status' => WorkflowStatus::ACTIVE,
    'timeout' => 180,
    'max_retries' => 5,
]);

$testStep = $testWorkflow->steps()->create([
    'name' => 'Test Step',
    'type' => StepType::DELAY,
    'position' => 1,
    'timeout' => 60,
    'max_retries' => 3,
    'configuration' => ['seconds' => 1],
]);

// Execute the workflow
$execution = $testWorkflow->execute(['test' => true]);

echo "   Workflow execution started\n";
echo "   ExecuteStepJob will use:\n";
echo "   - Timeout: 60 seconds (from step)\n";
echo "   - Max retries: 3 attempts (from step)\n";
echo "   - Queue: 'workflows' (from config)\n\n";

/*
|--------------------------------------------------------------------------
| Example 7: Real-World E-commerce Workflow
|--------------------------------------------------------------------------
*/

echo "7. Real-World E-commerce Workflow\n";

$ecommerceWorkflow = Workflow::create([
    'name' => 'E-commerce Order Processing',
    'description' => 'Complete order flow with optimized timeouts',
    'status' => WorkflowStatus::ACTIVE,
    'timeout' => 300,          // Default 5 minutes
    'max_retries' => 5,        // Default 5 retries
]);

// Step 1: Fast inventory check (30s, 2 retries)
$ecommerceWorkflow->steps()->create([
    'name' => 'Check Inventory',
    'type' => StepType::ACTION,
    'position' => 1,
    'timeout' => 30,
    'max_retries' => 2,
    'configuration' => [
        'class' => 'App\Services\InventoryService',
        'method' => 'checkAvailability',
        'parameters' => ['order_id' => '{{order_id}}'],
    ],
]);

// Step 2: Critical payment processing (2 min, 10 retries)
$ecommerceWorkflow->steps()->create([
    'name' => 'Process Payment',
    'type' => StepType::ACTION,
    'position' => 2,
    'timeout' => 120,
    'max_retries' => 10,
    'configuration' => [
        'class' => 'App\Services\PaymentService',
        'method' => 'charge',
        'parameters' => [
            'order_id' => '{{order_id}}',
            'amount' => '{{total}}',
        ],
    ],
]);

// Step 3: Update order status (uses workflow defaults: 300s, 5 retries)
$ecommerceWorkflow->steps()->create([
    'name' => 'Update Order Status',
    'type' => StepType::ACTION,
    'position' => 3,
    'configuration' => [
        'class' => 'App\Services\OrderService',
        'method' => 'updateStatus',
        'parameters' => ['order_id' => '{{order_id}}', 'status' => 'paid'],
    ],
]);

// Step 4: Send confirmation email (standard: 300s, 5 retries)
$ecommerceWorkflow->steps()->create([
    'name' => 'Send Confirmation Email',
    'type' => StepType::NOTIFICATION,
    'position' => 4,
    'configuration' => [
        'notification_class' => 'App\Notifications\OrderConfirmation',
        'recipients' => ['{{user_id}}'],
    ],
]);

echo "   Created realistic e-commerce workflow:\n";
echo "   - Inventory check: 30s timeout, 2 retries (fast)\n";
echo "   - Payment: 2 min timeout, 10 retries (critical)\n";
echo "   - Status update: 5 min timeout, 5 retries (standard)\n";
echo "   - Email: 5 min timeout, 5 retries (standard)\n\n";

/*
|--------------------------------------------------------------------------
| Example 8: Monitoring Effective Settings
|--------------------------------------------------------------------------
*/

echo "8. Check Effective Settings via API\n";

// Simulate API response
$apiResponse = [
    'id' => $ecommerceWorkflow->id,
    'name' => $ecommerceWorkflow->name,
    'timeout' => $ecommerceWorkflow->timeout,
    'max_retries' => $ecommerceWorkflow->max_retries,
    'steps' => $ecommerceWorkflow->steps->map(function ($step) {
        return [
            'id' => $step->id,
            'name' => $step->name,
            'timeout' => $step->timeout,
            'max_retries' => $step->max_retries,
        ];
    }),
];

echo "   API Response:\n";
echo json_encode($apiResponse, JSON_PRETTY_PRINT);
echo "\n\n";

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

echo "=== Summary ===\n";
echo "Demonstrated:\n\n";
echo "✅ Global defaults (config file)\n";
echo "✅ Workflow-level override (applies to all steps)\n";
echo "✅ Step-level override (most specific, highest priority)\n";
echo "✅ Partial overrides (mix of levels)\n";
echo "✅ Vapor-optimized configuration\n";
echo "✅ Real-world e-commerce example\n";
echo "✅ API transparency of settings\n\n";

echo "Priority Hierarchy:\n";
echo "  Step > Workflow > Config\n";
echo "  (Most specific wins)\n\n";

echo "Perfect for production use on Laravel Vapor! 🚀\n\n";
