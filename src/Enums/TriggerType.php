<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Enums;

/**
 * Trigger Type Enum
 *
 * Represents the different types of workflow triggers available.
 */
enum TriggerType: string
{
    case MANUAL = 'manual';
    case EVENT = 'event';
    case SCHEDULE = 'schedule';
    case WEBHOOK = 'webhook';
    case MODEL = 'model';

    /**
     * Get the label for the trigger type.
     */
    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::EVENT => 'Event',
            self::SCHEDULE => 'Schedule',
            self::WEBHOOK => 'Webhook',
            self::MODEL => 'Model Event',
        };
    }

    /**
     * Get the description for the trigger type.
     */
    public function description(): string
    {
        return match ($this) {
            self::MANUAL => 'Triggered manually via API or code',
            self::EVENT => 'Triggered when a specific Laravel event is fired',
            self::SCHEDULE => 'Triggered on a schedule (cron expression)',
            self::WEBHOOK => 'Triggered by incoming HTTP webhook',
            self::MODEL => 'Triggered by Eloquent model events (created, updated, deleted)',
        };
    }

    /**
     * Get the icon path for the trigger type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::MANUAL => 'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122',
            self::EVENT => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            self::SCHEDULE => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            self::WEBHOOK => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9',
            self::MODEL => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
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
            self::MANUAL => [],
            self::EVENT => [
                'event_class' => '',
                'conditions' => [],
            ],
            self::SCHEDULE => [
                'cron_expression' => '0 0 * * *',
                'timezone' => 'UTC',
                'context' => [],
            ],
            self::WEBHOOK => [
                'token' => '',
                'validation_rules' => [],
                'payload_mapping' => [],
            ],
            self::MODEL => [
                'model_class' => '',
                'events' => ['created'], // created, updated, deleted, restored
                'conditions' => [],
            ],
        };
    }

    /**
     * Get the handler class for this trigger type.
     */
    public function handlerClass(): string
    {
        return match ($this) {
            self::MANUAL => \AlizHarb\ForgePulse\Services\TriggerHandlers\ManualTriggerHandler::class,
            self::EVENT => \AlizHarb\ForgePulse\Services\TriggerHandlers\EventTriggerHandler::class,
            self::SCHEDULE => \AlizHarb\ForgePulse\Services\TriggerHandlers\ScheduleTriggerHandler::class,
            self::WEBHOOK => \AlizHarb\ForgePulse\Services\TriggerHandlers\WebhookTriggerHandler::class,
            self::MODEL => \AlizHarb\ForgePulse\Services\TriggerHandlers\ModelTriggerHandler::class,
        };
    }

    /**
     * Check if this trigger requires background processing.
     */
    public function requiresBackgroundProcessing(): bool
    {
        return match ($this) {
            self::MANUAL, self::WEBHOOK => false,
            self::EVENT, self::SCHEDULE, self::MODEL => true,
        };
    }
}
