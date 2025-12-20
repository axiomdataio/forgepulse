<?php

/**
 * ===============================================
 * Schema Discovery Approaches - Comparison
 * ===============================================
 *
 * Three ways to provide schema, in order of preference:
 */

// ============================================
// ✅ RECOMMENDED: Self-describing with schema() method
// ============================================

use AlizHarb\ForgePulse\Contracts\ProvidesSchema;

class ProcessOrderService implements ProvidesSchema
{
    // Define schema once, close to your code
    public static function schema(): array
    {
        return [
            'name' => 'Process Order',
            'description' => 'Process a customer order',
            'category' => 'orders',
            'input' => [
                'customerId' => ['type' => 'int', 'required' => true, 'source' => 'context'],
                'items' => ['type' => 'array', 'required' => true],
                'taxRate' => ['type' => 'float', 'required' => false, 'default' => 0.0],
            ],
            'output' => [
                'order_id' => 'int',
                'total' => 'float',
            ],
        ];
    }

    public function handle(int $customerId, array $items, float $taxRate = 0.0): array
    {
        // Implementation
        return ['order_id' => 123, 'total' => 99.99];
    }
}

// ============================================
// ✅ GOOD: Pure reflection (for simple services)
// ============================================

class UpdateInventoryService
{
    // No schema needed - auto-detected from signature
    public function handle(int $productId, int $quantity, string $reason = 'sale'): array
    {
        return ['product_id' => $productId, 'new_stock' => 100];
    }
}

// ============================================
// ⚠️  OPTIONAL: Attributes (for complex metadata)
// ============================================

use AlizHarb\ForgePulse\Attributes\WorkflowAction;
use AlizHarb\ForgePulse\Attributes\WorkflowParameter;

#[WorkflowAction(name: 'Send Email', category: 'notifications')]
class SendEmailNotification
{
    public function __invoke(
        #[WorkflowParameter(description: 'Email recipient')]
        string $to,
        string $subject,
        string $body
    ): array {
        return ['sent' => true];
    }
}

// ============================================
// COMPARISON TABLE
// ============================================

/*
┌─────────────────────┬────────────────┬──────────────┬──────────────┬─────────────┐
│ Approach            │ Verbosity      │ Type Safety  │ Co-location  │ Migration   │
├─────────────────────┼────────────────┼──────────────┼──────────────┼─────────────┤
│ schema() method     │ ⭐⭐⭐⭐        │ ⭐⭐⭐       │ ⭐⭐⭐⭐⭐    │ Easy        │
│ Pure reflection     │ ⭐⭐⭐⭐⭐      │ ⭐⭐⭐⭐⭐    │ ⭐⭐⭐⭐⭐    │ None needed │
│ Attributes          │ ⭐⭐           │ ⭐⭐⭐⭐     │ ⭐⭐⭐⭐     │ Medium      │
└─────────────────────┴────────────────┴──────────────┴──────────────┴─────────────┘

RECOMMENDATION:
- Start with pure reflection (zero setup)
- Add schema() method for actions that need better documentation
- Use attributes only if you really need the extra metadata
*/

// ============================================
// DECISION TREE
// ============================================

/**
 * Which approach should I use?
 *
 * Simple service with obvious parameters?
 * → Use pure reflection (nothing to do!)
 *
 * Need to document output fields or add examples?
 * → Add schema() method
 *
 * Need very detailed validation rules or UI hints?
 * → Use attributes (or still use schema() method with more detail)
 */

// ============================================
// REAL-WORLD EXAMPLES
// ============================================

// Example 1: Simple service - no schema needed
class CalculateTaxService
{
    public function handle(float $amount, float $rate): array
    {
        return ['tax' => $amount * $rate];
    }
}

// Example 2: Needs documentation - add schema()
class ChargePaymentService implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'name' => 'Charge Payment',
            'description' => 'Process payment via Stripe',
            'timeout' => 30,
            'input' => [
                'amount' => ['type' => 'float', 'required' => true, 'description' => 'Amount in dollars'],
                'payment_method' => ['type' => 'string', 'required' => true],
                'customer_id' => ['type' => 'string', 'required' => true, 'source' => 'context'],
            ],
            'output' => [
                'transaction_id' => 'string',
                'status' => 'string',
                'charged_at' => 'datetime',
            ],
        ];
    }

    public function handle(float $amount, string $paymentMethod, string $customerId): array
    {
        // Stripe charge logic...
        return [
            'transaction_id' => 'txn_123',
            'status' => 'succeeded',
            'charged_at' => now(),
        ];
    }
}

// Example 3: Complex action with validation - might use attributes
#[WorkflowAction(
    name: 'Sync Inventory to Warehouse',
    description: 'Syncs product inventory across multiple warehouse systems',
    category: 'inventory',
    tags: ['sync', 'warehouse', 'batch'],
    timeout: 120
)]
class SyncInventoryAction
{
    #[WorkflowOutput(fields: [
        'synced_count' => ['type' => 'int', 'description' => 'Number of items synced'],
        'failed_count' => ['type' => 'int', 'description' => 'Number of failures'],
        'errors' => ['type' => 'array', 'description' => 'List of error messages'],
    ])]
    public function handle(
        #[WorkflowParameter(
            description: 'List of product IDs to sync',
            example: '[1, 2, 3, 4, 5]',
            validation: ['min_items' => 1, 'max_items' => 100],
            source: 'config'
        )]
        array $productIds,

        #[WorkflowParameter(
            description: 'Target warehouse ID',
            example: 'wh_central',
            source: 'context'
        )]
        string $warehouseId
    ): array {
        // Complex sync logic...
        return ['synced_count' => 5, 'failed_count' => 0, 'errors' => []];
    }
}
