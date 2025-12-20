<?php

/**
 * ForgePulse v2.0 - Service Integration Examples
 *
 * This file demonstrates the new step-per-job architecture and
 * enhanced service/job integration capabilities.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */

use AlizHarb\ForgePulse\Enums\StepType;
use AlizHarb\ForgePulse\Enums\WorkflowStatus;
use AlizHarb\ForgePulse\Models\Workflow;

echo "\n=== ForgePulse v2.0 - Service Integration Examples ===\n\n";

/*
|--------------------------------------------------------------------------
| Example 1: Call Existing Service Method
|--------------------------------------------------------------------------
*/

echo "1. Calling Service Methods\n";

// Your existing service
class OrderService
{
    public function processOrder(array $parameters, array $context): array
    {
        $orderId = $parameters['order_id'];

        // Your business logic here
        $order = \App\Models\Order::find($orderId);
        $order->update(['status' => 'processing']);

        return [
            'order_id' => $orderId,
            'order_total' => $order->total,
            'processed_at' => now()->toISOString(),
        ];
    }

    public function calculateShipping(array $parameters, array $context): array
    {
        // Use context from previous steps
        $orderTotal = $context['order_total'] ?? 0;

        $shipping = $orderTotal > 100 ? 0 : 9.99;

        return ['shipping_cost' => $shipping];
    }
}

$workflow = Workflow::create([
    'name' => 'Order Processing Workflow',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Process Order (calls service method)
$step1 = $workflow->steps()->create([
    'name' => 'Process Order',
    'type' => StepType::ACTION,
    'position' => 1,
    'configuration' => [
        'class' => OrderService::class,
        'method' => 'processOrder',  // Specify which method
        'parameters' => [
            'order_id' => '{{order_id}}',
        ],
    ],
]);

// Step 2: Calculate Shipping (uses output from step 1)
$step2 = $workflow->steps()->create([
    'name' => 'Calculate Shipping',
    'type' => StepType::ACTION,
    'position' => 2,
    'parent_step_id' => $step1->id,
    'configuration' => [
        'class' => OrderService::class,
        'method' => 'calculateShipping',
        'parameters' => [],  // Will receive context with order_total
    ],
]);

echo "   Created workflow with service integration\n\n";

/*
|--------------------------------------------------------------------------
| Example 2: Invokable Action Classes
|--------------------------------------------------------------------------
*/

echo "2. Using Invokable Classes\n";

// Invokable action (uses __invoke)
class SendOrderConfirmation
{
    public function __invoke(array $parameters, array $context): array
    {
        $orderId = $context['order_id'];

        // Send email logic
        \Mail::to($context['user_email'])
            ->send(new \App\Mail\OrderConfirmation($orderId));

        return ['confirmation_sent' => true];
    }
}

$workflow->steps()->create([
    'name' => 'Send Confirmation Email',
    'type' => StepType::ACTION,
    'position' => 3,
    'parent_step_id' => $step2->id,
    'configuration' => [
        'class' => SendOrderConfirmation::class,
        // No method needed - auto-detects __invoke()
        'parameters' => [],
    ],
]);

echo "   Added invokable action step\n\n";

/*
|--------------------------------------------------------------------------
| Example 3: Synchronous Job Execution
|--------------------------------------------------------------------------
*/

echo "3. Execute Jobs Synchronously\n";

// Existing Laravel Job
class GenerateInvoiceJob implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Foundation\Bus\Dispatchable;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): array
    {
        // Generate PDF invoice
        $invoice = \App\Services\InvoiceGenerator::generate($this->orderId);

        return [
            'invoice_path' => $invoice->path,
            'invoice_number' => $invoice->number,
        ];
    }
}

$workflow->steps()->create([
    'name' => 'Generate Invoice',
    'type' => StepType::ACTION,
    'position' => 4,
    'parent_step_id' => $step2->id,
    'configuration' => [
        'class' => GenerateInvoiceJob::class,
        'mode' => 'sync',  // Call handle() directly, don't queue
        'parameters' => [
            'order_id' => '{{order_id}}',
        ],
    ],
]);

echo "   Added sync job execution step\n\n";

/*
|--------------------------------------------------------------------------
| Example 4: Async Job Dispatch (Fire and Forget)
|--------------------------------------------------------------------------
*/

echo "4. Dispatch Jobs Asynchronously\n";

$workflow->steps()->create([
    'name' => 'Send Marketing Email',
    'type' => StepType::ACTION,
    'position' => 5,
    'parent_step_id' => $step2->id,
    'configuration' => [
        'class' => \App\Jobs\SendMarketingEmail::class,
        'mode' => 'dispatch',  // Queue the job, don't wait
        'queue' => 'emails',
        'delay' => 300,  // Send 5 minutes later
        'parameters' => [
            'user_id' => '{{user_id}}',
            'campaign' => 'post_purchase',
        ],
    ],
]);

echo "   Added async job dispatch step\n\n";

/*
|--------------------------------------------------------------------------
| Example 5: Proper Delay Handling (No More Sleep!)
|--------------------------------------------------------------------------
*/

echo "5. Using Delays Properly\n";

$workflow->steps()->create([
    'name' => 'Wait for Payment Processing',
    'type' => StepType::DELAY,
    'position' => 6,
    'parent_step_id' => $step2->id,
    'configuration' => [
        'seconds' => 1800,  // 30 minutes
    ],
]);

echo "   Delay scheduled - will not block queue worker!\n\n";

/*
|--------------------------------------------------------------------------
| Example 6: Complete E-commerce Workflow
|--------------------------------------------------------------------------
*/

echo "6. Complete Order Processing Workflow\n";

$ecommerceWorkflow = Workflow::create([
    'name' => 'Complete E-commerce Order Flow',
    'description' => 'Full order processing with v2.0 features',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Validate Order
$validateStep = $ecommerceWorkflow->steps()->create([
    'name' => 'Validate Order',
    'type' => StepType::ACTION,
    'position' => 1,
    'configuration' => [
        'class' => \App\Services\OrderValidationService::class,
        'method' => 'validate',
        'parameters' => ['order_id' => '{{order_id}}'],
    ],
]);

// Step 2: Process Payment
$paymentStep = $ecommerceWorkflow->steps()->create([
    'name' => 'Process Payment',
    'type' => StepType::ACTION,
    'position' => 2,
    'parent_step_id' => $validateStep->id,
    'configuration' => [
        'class' => \App\Services\PaymentService::class,
        'method' => 'processPayment',
        'parameters' => [
            'order_id' => '{{order_id}}',
            'payment_method' => '{{payment_method}}',
        ],
    ],
    'timeout' => 120,  // 2 minutes max for payment
]);

// Step 3: Check Inventory
$inventoryStep = $ecommerceWorkflow->steps()->create([
    'name' => 'Check Inventory',
    'type' => StepType::ACTION,
    'position' => 3,
    'parent_step_id' => $paymentStep->id,
    'configuration' => [
        'class' => \App\Services\InventoryService::class,
        'method' => 'reserve',
        'parameters' => ['order_id' => '{{order_id}}'],
    ],
]);

// Step 4: Notify Warehouse (Async)
$ecommerceWorkflow->steps()->create([
    'name' => 'Notify Warehouse',
    'type' => StepType::ACTION,
    'position' => 4,
    'parent_step_id' => $inventoryStep->id,
    'configuration' => [
        'class' => \App\Jobs\NotifyWarehouse::class,
        'mode' => 'dispatch',
        'queue' => 'warehouse',
        'parameters' => ['order_id' => '{{order_id}}'],
    ],
]);

// Step 5: Send Confirmation
$ecommerceWorkflow->steps()->create([
    'name' => 'Send Order Confirmation',
    'type' => StepType::NOTIFICATION,
    'position' => 5,
    'parent_step_id' => $inventoryStep->id,
    'configuration' => [
        'notification_class' => \App\Notifications\OrderConfirmed::class,
        'recipients' => ['{{user_id}}'],
    ],
]);

// Step 6: Wait 24 Hours
$waitStep = $ecommerceWorkflow->steps()->create([
    'name' => 'Wait 24 Hours',
    'type' => StepType::DELAY,
    'position' => 6,
    'parent_step_id' => $inventoryStep->id,
    'configuration' => [
        'seconds' => 86400,
    ],
]);

// Step 7: Send Follow-up
$ecommerceWorkflow->steps()->create([
    'name' => 'Send Follow-up Email',
    'type' => StepType::ACTION,
    'position' => 7,
    'parent_step_id' => $waitStep->id,
    'configuration' => [
        'class' => \App\Jobs\SendFollowUpEmail::class,
        'mode' => 'dispatch',
        'queue' => 'emails',
        'parameters' => ['order_id' => '{{order_id}}'],
    ],
]);

echo "   Created complete e-commerce workflow\n\n";

/*
|--------------------------------------------------------------------------
| Example 7: Execute and Monitor
|--------------------------------------------------------------------------
*/

echo "7. Execute and Monitor Workflow\n";

// Execute workflow
$execution = $ecommerceWorkflow->execute([
    'order_id' => 12345,
    'user_id' => 67,
    'payment_method' => 'credit_card',
    'user_email' => 'customer@example.com',
]);

echo "   Execution ID: {$execution->id}\n";
echo "   Status: {$execution->status->value}\n";
echo "   Started at: {$execution->started_at}\n\n";

// Monitor progress (simulated)
echo "   Monitoring workflow progress...\n";
sleep(1);

// Reload execution to see updates
$execution->refresh();
echo "   Current step: {$execution->current_step_id}\n";
echo "   Completed steps: ".count($execution->completed_step_ids ?? [])."\n\n";

/*
|--------------------------------------------------------------------------
| Example 8: Resume Failed Execution
|--------------------------------------------------------------------------
*/

echo "8. Resume Failed Execution\n";

// Simulate a failed execution
$failedExecution = \AlizHarb\ForgePulse\Models\WorkflowExecution::create([
    'workflow_id' => $workflow->id,
    'status' => \AlizHarb\ForgePulse\Enums\ExecutionStatus::FAILED,
    'current_step_id' => $step1->id,
    'completed_step_ids' => [],
    'error_message' => 'Service temporarily unavailable',
    'context' => ['order_id' => 999],
]);

echo "   Failed execution ID: {$failedExecution->id}\n";
echo "   Error: {$failedExecution->error_message}\n";

// Resume execution
$failedExecution->resumeExecution();

echo "   ✅ Execution resumed from step {$failedExecution->current_step_id}\n\n";

/*
|--------------------------------------------------------------------------
| Example 9: Spatie Laravel Actions Integration
|--------------------------------------------------------------------------
*/

echo "9. Spatie Laravel Actions Integration\n";

// If using spatie/laravel-actions
class CreateUserProfile extends \Spatie\QueueableActions\QueueableAction
{
    public function execute(array $parameters, array $context): array
    {
        $user = \App\Models\User::find($parameters['user_id']);

        $profile = $user->profile()->create([
            'bio' => 'Welcome!',
            'preferences' => [],
        ]);

        return [
            'profile_id' => $profile->id,
            'profile_created' => true,
        ];
    }
}

$workflow->steps()->create([
    'name' => 'Create User Profile',
    'type' => StepType::ACTION,
    'configuration' => [
        'class' => CreateUserProfile::class,
        // Auto-detects execute() method
        'parameters' => ['user_id' => '{{user_id}}'],
    ],
]);

echo "   Works with Spatie Laravel Actions!\n\n";

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

echo "=== Summary ===\n";
echo "ForgePulse v2.0 Features Demonstrated:\n\n";
echo "✅ Service method integration\n";
echo "✅ Invokable classes\n";
echo "✅ Synchronous job execution\n";
echo "✅ Asynchronous job dispatch\n";
echo "✅ Proper delay handling (no blocking)\n";
echo "✅ Complete workflow examples\n";
echo "✅ Execution monitoring\n";
echo "✅ Resume failed executions\n";
echo "✅ Third-party action package support\n\n";

echo "Architecture Benefits:\n";
echo "✅ Step-per-job execution (fault tolerant)\n";
echo "✅ Vapor/Lambda compatible\n";
echo "✅ No blocked queue workers\n";
echo "✅ Resume from failures\n";
echo "✅ Works with existing code\n\n";

echo "Your workflows are now production-ready! 🚀\n\n";
