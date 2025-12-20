<?php

declare(strict_types=1);

namespace App\Actions;

use AlizHarb\ForgePulse\Attributes\WorkflowAction;
use AlizHarb\ForgePulse\Attributes\WorkflowOutput;
use AlizHarb\ForgePulse\Attributes\WorkflowParameter;

/**
 * Example 1: Service with full attribute metadata
 *
 * This service processes customer orders and demonstrates
 * how to use all available attributes for schema discovery.
 */
#[WorkflowAction(
    name: 'Process Customer Order',
    description: 'Validates, calculates totals, and creates an order record',
    category: 'orders',
    tags: ['payment', 'inventory'],
    timeout: 30
)]
class ProcessOrderService
{
    /**
     * Process an order with the given items.
     *
     * @param  array  $parameters  Order processing parameters
     * @param  array  $context  Workflow execution context
     * @return array Order result with totals and ID
     */
    #[WorkflowOutput(fields: [
        'order_id' => ['type' => 'int', 'description' => 'Created order ID'],
        'subtotal' => ['type' => 'float', 'description' => 'Order subtotal before tax'],
        'tax' => ['type' => 'float', 'description' => 'Tax amount'],
        'total' => ['type' => 'float', 'description' => 'Total order amount'],
        'status' => ['type' => 'string', 'description' => 'Order status (pending, confirmed)'],
    ])]
    public function handle(
        #[WorkflowParameter(
            description: 'Customer ID who is placing the order',
            example: '12345',
            validation: ['min' => 1],
            source: 'context'
        )]
        int $customerId,

        #[WorkflowParameter(
            description: 'Array of items with product_id and quantity',
            example: '[{"product_id": 1, "quantity": 2}]',
            validation: ['min_items' => 1],
            source: 'config'
        )]
        array $items,

        #[WorkflowParameter(
            description: 'Tax rate as decimal (e.g., 0.08 for 8%)',
            example: '0.08',
            validation: ['min' => 0, 'max' => 1],
            source: 'config'
        )]
        float $taxRate = 0.0,
    ): array {
        // Calculate subtotal
        $subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']);

        // Calculate tax
        $tax = $subtotal * $taxRate;

        // Total
        $total = $subtotal + $tax;

        // Create order (pseudo-code)
        $orderId = rand(1000, 9999); // DB::table('orders')->insertGetId(...)

        return [
            'order_id' => $orderId,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'status' => 'pending',
        ];
    }
}

/**
 * Example 2: Invokable action for sending notifications
 */
#[WorkflowAction(
    name: 'Send Email Notification',
    description: 'Sends an email using Laravel Mail',
    category: 'notifications',
    tags: ['email', 'communication'],
    timeout: 15
)]
class SendEmailNotification
{
    /**
     * Send notification email.
     *
     * @param  array  $parameters  Email parameters
     * @param  array  $context  Workflow context
     * @return array Send result
     */
    #[WorkflowOutput(fields: [
        'sent' => ['type' => 'bool', 'description' => 'Whether email was sent successfully'],
        'message_id' => ['type' => 'string', 'description' => 'Email provider message ID'],
    ])]
    public function __invoke(
        #[WorkflowParameter(
            description: 'Recipient email address',
            example: 'customer@example.com',
            source: 'context'
        )]
        string $to,

        #[WorkflowParameter(
            description: 'Email subject line',
            example: 'Your order has been confirmed',
            source: 'config'
        )]
        string $subject,

        #[WorkflowParameter(
            description: 'Email body content',
            example: 'Thank you for your order #{{order_id}}',
            source: 'config'
        )]
        string $body,
    ): array {
        // Send email (pseudo-code)
        // Mail::to($to)->send(new OrderEmail($subject, $body));

        return [
            'sent' => true,
            'message_id' => uniqid('msg_'),
        ];
    }
}

/**
 * Example 3: Minimal service without attributes (uses reflection only)
 *
 * Schema will be auto-generated from method signature and docblock.
 */
class UpdateInventoryService
{
    /**
     * Update product inventory levels.
     *
     * @param  int  $productId  Product to update
     * @param  int  $quantity  Quantity to add/subtract
     * @param  string  $reason  Reason for adjustment (sale, return, correction)
     * @return array Updated inventory info
     */
    public function handle(int $productId, int $quantity, string $reason = 'sale'): array
    {
        // Update inventory (pseudo-code)
        // $product = Product::find($productId);
        // $product->decrement('stock', $quantity);

        return [
            'product_id' => $productId,
            'new_stock_level' => 100 - $quantity, // pseudo
            'adjustment' => $quantity,
            'reason' => $reason,
        ];
    }
}

/**
 * Example 4: Job class for async processing
 */
class ProcessPaymentJob
{
    public function __construct(
        public int $orderId,
        public float $amount,
        public string $paymentMethod
    ) {}

    /**
     * Process payment transaction.
     *
     * @return void
     */
    public function handle(): void
    {
        // Process payment with provider
        // PaymentGateway::charge($this->amount, $this->paymentMethod);
    }
}
