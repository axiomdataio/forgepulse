<?php

declare(strict_types=1);

use AlizHarb\ForgePulse\Enums\TriggerType;
use AlizHarb\ForgePulse\Enums\WorkflowStatus;
use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Models\WorkflowTrigger;
use AlizHarb\ForgePulse\Services\Triggers\ScheduleTriggerRunner;
use AlizHarb\ForgePulse\Services\Triggers\TriggerManager;

beforeEach(function () {
    $this->workflow = Workflow::factory()->create([
        'status' => WorkflowStatus::ACTIVE,
    ]);
});

describe('WorkflowTrigger Model', function () {
    it('can create an event trigger', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'On User Created',
            'type' => TriggerType::EVENT,
            'is_active' => true,
            'configuration' => [
                'event_class' => 'App\\Events\\UserCreated',
            ],
        ]);

        expect($trigger)->toBeInstanceOf(WorkflowTrigger::class)
            ->and($trigger->type)->toBe(TriggerType::EVENT)
            ->and($trigger->configuration['event_class'])->toBe('App\\Events\\UserCreated');
    });

    it('can create a schedule trigger', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Daily Report',
            'type' => TriggerType::SCHEDULE,
            'is_active' => true,
            'configuration' => [
                'cron_expression' => '0 9 * * *',
                'timezone' => 'UTC',
            ],
        ]);

        expect($trigger)->toBeInstanceOf(WorkflowTrigger::class)
            ->and($trigger->type)->toBe(TriggerType::SCHEDULE)
            ->and($trigger->configuration['cron_expression'])->toBe('0 9 * * *');
    });

    it('generates webhook token for webhook triggers', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'External Webhook',
            'type' => TriggerType::WEBHOOK,
            'is_active' => true,
            'configuration' => [],
        ]);

        expect($trigger->configuration['webhook_token'])
            ->toBeString()
            ->toHaveLength(64);
    });

    it('can create a model trigger', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'On Order Updated',
            'type' => TriggerType::MODEL,
            'is_active' => true,
            'configuration' => [
                'model_class' => 'App\\Models\\Order',
                'events' => ['created', 'updated'],
            ],
        ]);

        expect($trigger)->toBeInstanceOf(WorkflowTrigger::class)
            ->and($trigger->type)->toBe(TriggerType::MODEL)
            ->and($trigger->configuration['events'])->toBe(['created', 'updated']);
    });

    it('belongs to a workflow', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Test Trigger',
            'type' => TriggerType::MANUAL,
            'is_active' => true,
            'configuration' => [],
        ]);

        expect($trigger->workflow)->toBeInstanceOf(Workflow::class)
            ->and($trigger->workflow->id)->toBe($this->workflow->id);
    });

    it('can check if trigger can fire', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Test Trigger',
            'type' => TriggerType::MANUAL,
            'is_active' => true,
            'configuration' => [],
        ]);

        expect($trigger->canFire())->toBeTrue();

        $trigger->update(['is_active' => false]);
        expect($trigger->canFire())->toBeFalse();
    });

    it('respects rate limiting', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Rate Limited Trigger',
            'type' => TriggerType::MANUAL,
            'is_active' => true,
            'configuration' => [],
            'max_executions' => 2,
            'max_executions_period' => 'per_hour',
        ]);

        expect($trigger->canFire())->toBeTrue();
    });

    it('builds context from trigger data', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Mapped Trigger',
            'type' => TriggerType::WEBHOOK,
            'is_active' => true,
            'configuration' => [],
            'context_mapping' => [
                'user_id' => 'data.user.id',
                'email' => 'data.user.email',
            ],
        ]);

        $data = [
            'data' => [
                'user' => [
                    'id' => 123,
                    'email' => 'test@example.com',
                ],
            ],
        ];

        $context = $trigger->buildContext($data);

        expect($context)
            ->toHaveKey('user_id', 123)
            ->toHaveKey('email', 'test@example.com');
    });

    it('returns raw data when no context mapping defined', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Unmapped Trigger',
            'type' => TriggerType::WEBHOOK,
            'is_active' => true,
            'configuration' => [],
        ]);

        $data = ['foo' => 'bar', 'baz' => 123];
        $context = $trigger->buildContext($data);

        expect($context)->toBe($data);
    });
});

describe('TriggerManager', function () {
    it('can fire a trigger and execute workflow', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Test Trigger',
            'type' => TriggerType::MANUAL,
            'is_active' => true,
            'configuration' => [],
        ]);

        $manager = app(TriggerManager::class);
        $execution = $manager->fire($trigger, ['test' => 'data']);

        expect($execution)->not->toBeNull()
            ->and($execution->workflow_id)->toBe($this->workflow->id)
            ->and($execution->context['test'])->toBe('data')
            ->and($execution->context['_trigger']['id'])->toBe($trigger->id);

        $trigger->refresh();
        expect($trigger->trigger_count)->toBe(1)
            ->and($trigger->last_triggered_at)->not->toBeNull();
    });

    it('returns null when trigger cannot fire', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Inactive Trigger',
            'type' => TriggerType::MANUAL,
            'is_active' => false,
            'configuration' => [],
        ]);

        $manager = app(TriggerManager::class);
        $execution = $manager->fire($trigger, []);

        expect($execution)->toBeNull();
    });

    it('can find event triggers', function () {
        WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'User Created Trigger',
            'type' => TriggerType::EVENT,
            'is_active' => true,
            'configuration' => [
                'event_class' => 'App\\Events\\UserCreated',
            ],
        ]);

        $manager = app(TriggerManager::class);
        $triggers = $manager->findEventTriggers('App\\Events\\UserCreated');

        expect($triggers)->toHaveCount(1)
            ->and($triggers->first()->name)->toBe('User Created Trigger');
    });

    it('can find model triggers', function () {
        WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Order Trigger',
            'type' => TriggerType::MODEL,
            'is_active' => true,
            'configuration' => [
                'model_class' => 'App\\Models\\Order',
                'events' => ['created', 'updated'],
            ],
        ]);

        $manager = app(TriggerManager::class);

        $createdTriggers = $manager->findModelTriggers('App\\Models\\Order', 'created');
        expect($createdTriggers)->toHaveCount(1);

        $deletedTriggers = $manager->findModelTriggers('App\\Models\\Order', 'deleted');
        expect($deletedTriggers)->toHaveCount(0);
    });

    it('can find webhook trigger by token', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Webhook Trigger',
            'type' => TriggerType::WEBHOOK,
            'is_active' => true,
            'configuration' => [],
        ]);

        $token = $trigger->configuration['webhook_token'];

        $manager = app(TriggerManager::class);
        $found = $manager->findWebhookTrigger($token);

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($trigger->id);

        $notFound = $manager->findWebhookTrigger('invalid-token');
        expect($notFound)->toBeNull();
    });
});

describe('ScheduleTriggerRunner', function () {
    it('validates cron expressions', function () {
        $runner = app(ScheduleTriggerRunner::class);

        expect($runner->isValidCronExpression('* * * * *'))->toBeTrue()
            ->and($runner->isValidCronExpression('0 9 * * *'))->toBeTrue()
            ->and($runner->isValidCronExpression('invalid'))->toBeFalse()
            ->and($runner->isValidCronExpression(''))->toBeFalse();
    });

    it('can get next run time for trigger', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Scheduled Trigger',
            'type' => TriggerType::SCHEDULE,
            'is_active' => true,
            'configuration' => [
                'cron_expression' => '0 9 * * *',
                'timezone' => 'UTC',
            ],
        ]);

        $runner = app(ScheduleTriggerRunner::class);
        $nextRun = $runner->getNextRunTime($trigger);

        expect($nextRun)->not->toBeNull()
            ->and($nextRun->format('H:i'))->toBe('09:00');
    });
});

describe('TriggerType Enum', function () {
    it('has all expected trigger types', function () {
        $types = TriggerType::cases();

        expect($types)->toHaveCount(5)
            ->and(collect($types)->pluck('value')->toArray())->toBe([
                'event',
                'schedule',
                'webhook',
                'model',
                'manual',
            ]);
    });

    it('returns correct configuration schemas', function () {
        $eventSchema = TriggerType::EVENT->configurationSchema();
        expect($eventSchema)->toHaveKey('event_class');

        $scheduleSchema = TriggerType::SCHEDULE->configurationSchema();
        expect($scheduleSchema)->toHaveKey('cron_expression');

        $webhookSchema = TriggerType::WEBHOOK->configurationSchema();
        expect($webhookSchema)->toHaveKey('webhook_token');

        $modelSchema = TriggerType::MODEL->configurationSchema();
        expect($modelSchema)->toHaveKey('model_class')
            ->and($modelSchema)->toHaveKey('events');
    });

    it('identifies types requiring registration', function () {
        expect(TriggerType::EVENT->requiresRegistration())->toBeTrue()
            ->and(TriggerType::MODEL->requiresRegistration())->toBeTrue()
            ->and(TriggerType::SCHEDULE->requiresRegistration())->toBeFalse()
            ->and(TriggerType::WEBHOOK->requiresRegistration())->toBeFalse()
            ->and(TriggerType::MANUAL->requiresRegistration())->toBeFalse();
    });
});
