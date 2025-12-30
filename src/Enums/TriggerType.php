<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Enums;

/**
 * Trigger Type Enum
 *
 * Represents the different types of workflow triggers available.
 * Each trigger type has its own configuration schema and activation mechanism.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
enum TriggerType: string
{
    case EVENT = 'event';
    case SCHEDULE = 'schedule';
    case WEBHOOK = 'webhook';
    case MODEL = 'model';
    case MANUAL = 'manual';

    /**
     * Get the human-readable label for the trigger type.
     */
    public function label(): string
    {
        return match ($this) {
            self::EVENT => 'Event Trigger',
            self::SCHEDULE => 'Scheduled Trigger',
            self::WEBHOOK => 'Webhook Trigger',
            self::MODEL => 'Model Trigger',
            self::MANUAL => 'Manual Trigger',
        };
    }

    /**
     * Get the description for the trigger type.
     */
    public function description(): string
    {
        return match ($this) {
            self::EVENT => 'Triggers when a Laravel event is dispatched',
            self::SCHEDULE => 'Triggers on a cron schedule',
            self::WEBHOOK => 'Triggers via incoming HTTP POST requests',
            self::MODEL => 'Triggers on Eloquent model events (created, updated, deleted)',
            self::MANUAL => 'Triggered programmatically via code or API',
        };
    }

    /**
     * Get the icon path for the trigger type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::EVENT => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
            self::SCHEDULE => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            self::WEBHOOK => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9',
            self::MODEL => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4',
            self::MANUAL => 'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122',
        };
    }

    /**
     * Get the color for the trigger type.
     */
    public function color(): string
    {
        return match ($this) {
            self::EVENT => 'purple',
            self::SCHEDULE => 'blue',
            self::WEBHOOK => 'indigo',
            self::MODEL => 'green',
            self::MANUAL => 'gray',
        };
    }

    /**
     * Get the required configuration fields for this trigger type.
     *
     * @return array<string, array<string, mixed>>
     */
    public function configurationSchema(): array
    {
        return match ($this) {
            self::EVENT => [
                'event_class' => ['type' => 'string', 'required' => true, 'description' => 'Fully qualified event class name'],
                'listen_once' => ['type' => 'boolean', 'required' => false, 'default' => false, 'description' => 'Only trigger once per event instance'],
            ],
            self::SCHEDULE => [
                'cron_expression' => ['type' => 'string', 'required' => true, 'description' => 'Cron expression (e.g., "0 9 * * *")'],
                'timezone' => ['type' => 'string', 'required' => false, 'default' => 'UTC', 'description' => 'Timezone for schedule evaluation'],
                'overlap_prevention' => ['type' => 'boolean', 'required' => false, 'default' => true, 'description' => 'Prevent overlapping executions'],
            ],
            self::WEBHOOK => [
                'webhook_token' => ['type' => 'string', 'required' => false, 'description' => 'Auto-generated unique token for webhook URL'],
                'secret_token' => ['type' => 'string', 'required' => false, 'description' => 'Secret for signature validation'],
                'validate_signature' => ['type' => 'boolean', 'required' => false, 'default' => false, 'description' => 'Require signature validation'],
                'signature_header' => ['type' => 'string', 'required' => false, 'default' => 'X-Signature', 'description' => 'Header containing the signature'],
                'allowed_ips' => ['type' => 'array', 'required' => false, 'default' => [], 'description' => 'Allowed IP addresses'],
            ],
            self::MODEL => [
                'model_class' => ['type' => 'string', 'required' => true, 'description' => 'Fully qualified Eloquent model class name'],
                'events' => ['type' => 'array', 'required' => true, 'description' => 'Model events to listen for (created, updated, deleted)'],
                'attribute_filters' => ['type' => 'array', 'required' => false, 'default' => [], 'description' => 'Filter by attribute changes'],
            ],
            self::MANUAL => [
                'description' => ['type' => 'string', 'required' => false, 'default' => 'Manual execution', 'description' => 'Description of manual trigger purpose'],
            ],
        };
    }

    /**
     * Get the default configuration for this trigger type.
     *
     * @return array<string, mixed>
     */
    public function defaultConfiguration(): array
    {
        return match ($this) {
            self::EVENT => ['event_class' => '', 'listen_once' => false],
            self::SCHEDULE => ['cron_expression' => '0 * * * *', 'timezone' => 'UTC', 'overlap_prevention' => true],
            self::WEBHOOK => ['validate_signature' => false, 'signature_header' => 'X-Signature', 'allowed_ips' => []],
            self::MODEL => ['model_class' => '', 'events' => ['created'], 'attribute_filters' => []],
            self::MANUAL => ['description' => 'Manual execution'],
        };
    }

    /**
     * Check if this trigger type requires runtime registration.
     */
    public function requiresRegistration(): bool
    {
        return match ($this) {
            self::EVENT, self::MODEL => true,
            self::SCHEDULE, self::WEBHOOK, self::MANUAL => false,
        };
    }

    /**
     * Get all trigger types that require registration.
     *
     * @return array<self>
     */
    public static function registrationRequired(): array
    {
        return [self::EVENT, self::MODEL];
    }

    /**
     * Get the context data schema for this trigger type.
     * Describes what data is available for context_mapping.
     *
     * @return array<string, array<string, mixed>>
     */
    public function contextSchema(): array
    {
        return match ($this) {
            self::EVENT => [
                '_description' => 'Data from the Laravel event. Structure depends on your event class.',
                '_note' => 'Implement toArray() on your event for best results.',
                '_example_mapping' => [
                    'user_id' => 'user.id',
                    'user_email' => 'user.email',
                    'order_id' => 'order.id',
                ],
                'fields' => [
                    '*' => [
                        'type' => 'mixed',
                        'description' => 'All public properties from your event class',
                        'example' => 'If event has $user property, access via "user" or "user.id"',
                    ],
                ],
            ],
            self::SCHEDULE => [
                '_description' => 'Data provided when a scheduled trigger fires.',
                'fields' => [
                    'scheduled_at' => [
                        'type' => 'string',
                        'description' => 'ISO8601 timestamp when the trigger fired',
                        'example' => '2024-01-15T09:00:00+00:00',
                    ],
                    'cron_expression' => [
                        'type' => 'string',
                        'description' => 'The cron expression that triggered this execution',
                        'example' => '0 9 * * *',
                    ],
                    'timezone' => [
                        'type' => 'string',
                        'description' => 'Timezone of the schedule',
                        'example' => 'UTC',
                    ],
                ],
            ],
            self::WEBHOOK => [
                '_description' => 'Data from the incoming HTTP request.',
                '_example_mapping' => [
                    'event_type' => 'payload.type',
                    'payment_id' => 'payload.data.object.id',
                    'amount' => 'payload.data.object.amount',
                    'source_ip' => 'ip',
                ],
                'fields' => [
                    'payload' => [
                        'type' => 'object',
                        'description' => 'The JSON body of the webhook request',
                        'example' => '{"type": "payment.completed", "data": {...}}',
                    ],
                    'headers' => [
                        'type' => 'object',
                        'description' => 'HTTP headers (sensitive headers excluded)',
                        'example' => '{"content-type": ["application/json"]}',
                    ],
                    'method' => [
                        'type' => 'string',
                        'description' => 'HTTP method',
                        'example' => 'POST',
                    ],
                    'content_type' => [
                        'type' => 'string',
                        'description' => 'Content-Type header value',
                        'example' => 'application/json',
                    ],
                    'ip' => [
                        'type' => 'string',
                        'description' => 'IP address of the request',
                        'example' => '192.168.1.1',
                    ],
                    'received_at' => [
                        'type' => 'string',
                        'description' => 'ISO8601 timestamp when webhook was received',
                        'example' => '2024-01-15T10:30:00+00:00',
                    ],
                ],
            ],
            self::MODEL => [
                '_description' => 'Data from the Eloquent model event.',
                '_example_mapping' => [
                    'order_id' => 'model.id',
                    'customer_id' => 'model.customer_id',
                    'new_status' => 'model.status',
                    'old_status' => 'original.status',
                    'changed_fields' => 'changes',
                ],
                'fields' => [
                    'model' => [
                        'type' => 'object',
                        'description' => 'The model as array (current state)',
                        'example' => '{"id": 1, "status": "shipped", ...}',
                    ],
                    'model_class' => [
                        'type' => 'string',
                        'description' => 'Fully qualified class name of the model',
                        'example' => 'App\\Models\\Order',
                    ],
                    'model_id' => [
                        'type' => 'mixed',
                        'description' => 'Primary key of the model',
                        'example' => '1',
                    ],
                    'event' => [
                        'type' => 'string',
                        'description' => 'The model event type',
                        'example' => 'created | updated | deleted',
                    ],
                    'changes' => [
                        'type' => 'object',
                        'description' => 'Changed attributes (only for "updated" event)',
                        'example' => '{"status": "shipped"}',
                    ],
                    'original' => [
                        'type' => 'object',
                        'description' => 'Original values before update (only for "updated" event)',
                        'example' => '{"status": "processing"}',
                    ],
                    'team_id' => [
                        'type' => 'integer|null',
                        'description' => 'Team ID for multi-tenancy (if applicable)',
                        'example' => '1',
                    ],
                ],
            ],
            self::MANUAL => [
                '_description' => 'Data passed when manually executing the workflow.',
                '_note' => 'Structure is defined by the caller of workflow->execute($context)',
                'fields' => [
                    '*' => [
                        'type' => 'mixed',
                        'description' => 'Any data passed to the execute() method',
                        'example' => 'workflow->execute(["user_id" => 1, "action" => "approve"])',
                    ],
                ],
            ],
        };
    }

    /**
     * Get example context data for this trigger type.
     *
     * @return array<string, mixed>
     */
    public function exampleContextData(): array
    {
        return match ($this) {
            self::EVENT => [
                'user' => [
                    'id' => 123,
                    'email' => 'user@example.com',
                    'name' => 'John Doe',
                ],
                'order' => [
                    'id' => 456,
                    'total' => 99.99,
                ],
                'timestamp' => '2024-01-15T10:30:00+00:00',
            ],
            self::SCHEDULE => [
                'scheduled_at' => '2024-01-15T09:00:00+00:00',
                'cron_expression' => '0 9 * * *',
                'timezone' => 'UTC',
            ],
            self::WEBHOOK => [
                'payload' => [
                    'type' => 'payment_intent.succeeded',
                    'data' => [
                        'object' => [
                            'id' => 'pi_123456',
                            'amount' => 5000,
                            'currency' => 'usd',
                            'customer' => 'cus_ABC123',
                        ],
                    ],
                ],
                'headers' => [
                    'content-type' => ['application/json'],
                    'user-agent' => ['Stripe/1.0'],
                ],
                'method' => 'POST',
                'content_type' => 'application/json',
                'ip' => '52.63.170.100',
                'received_at' => '2024-01-15T10:30:00+00:00',
            ],
            self::MODEL => [
                'model' => [
                    'id' => 789,
                    'status' => 'shipped',
                    'customer_id' => 123,
                    'total' => 150.00,
                    'created_at' => '2024-01-10T08:00:00+00:00',
                    'updated_at' => '2024-01-15T10:30:00+00:00',
                ],
                'model_class' => 'App\\Models\\Order',
                'model_id' => 789,
                'event' => 'updated',
                'changes' => [
                    'status' => 'shipped',
                ],
                'original' => [
                    'status' => 'processing',
                ],
                'team_id' => 1,
            ],
            self::MANUAL => [
                'user_id' => 123,
                'action' => 'approve',
                'notes' => 'Manual approval by admin',
            ],
        };
    }
}
