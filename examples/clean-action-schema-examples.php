<?php

declare(strict_types=1);

namespace App\Actions;

use AlizHarb\ForgePulse\Contracts\ProvidesSchema;

/**
 * Clean approach: Self-describing action with schema method
 */
class ProcessOrderService implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'name' => 'Process Customer Order',
            'description' => 'Validates, calculates totals, and creates an order record',
            'category' => 'orders',
            'tags' => ['payment', 'inventory'],
            'timeout' => 30,
            'input' => [
                'customerId' => [
                    'type' => 'int',
                    'required' => true,
                    'description' => 'Customer ID',
                    'source' => 'context',
                    'example' => 12345,
                ],
                'items' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'Order items',
                    'source' => 'config',
                    'example' => [['product_id' => 1, 'quantity' => 2]],
                ],
                'taxRate' => [
                    'type' => 'float',
                    'required' => false,
                    'default' => 0.0,
                    'description' => 'Tax rate as decimal',
                    'validation' => ['min' => 0, 'max' => 1],
                ],
            ],
            'output' => [
                'order_id' => ['type' => 'int', 'description' => 'Created order ID'],
                'subtotal' => ['type' => 'float', 'description' => 'Subtotal before tax'],
                'tax' => ['type' => 'float', 'description' => 'Tax amount'],
                'total' => ['type' => 'float', 'description' => 'Total amount'],
                'status' => ['type' => 'string', 'description' => 'Order status'],
            ],
        ];
    }

    public function handle(int $customerId, array $items, float $taxRate = 0.0): array
    {
        // Implementation...
        $subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $tax = $subtotal * $taxRate;
        $total = $subtotal + $tax;

        return [
            'order_id' => rand(1000, 9999),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'status' => 'pending',
        ];
    }
}

/**
 * Even cleaner: Minimal schema with just essentials
 */
class SendEmailNotification implements ProvidesSchema
{
    public static function schema(): array
    {
        return [
            'name' => 'Send Email',
            'input' => [
                'to' => 'string',
                'subject' => 'string',
                'body' => 'string',
            ],
            'output' => [
                'sent' => 'bool',
                'message_id' => 'string',
            ],
        ];
    }

    public function __invoke(string $to, string $subject, string $body): array
    {
        // Send email...
        return ['sent' => true, 'message_id' => uniqid('msg_')];
    }
}

/**
 * Alternative: DTO-based approach (Laravel-style)
 */
class CreateUserService
{
    // No interface needed - ActionSchema can detect DTOs automatically

    public function handle(CreateUserData $data): array
    {
        // $data->email, $data->name, etc.
        return ['user_id' => 123];
    }
}

class CreateUserData
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly ?string $phone = null,
    ) {}
}

/**
 * No schema at all: Use pure reflection (for simple services)
 */
class UpdateInventoryService
{
    // Just a regular service - schema auto-generated via reflection
    public function handle(int $productId, int $quantity): array
    {
        return ['product_id' => $productId, 'new_stock' => 100 - $quantity];
    }
}
