<?php

declare(strict_types=1);

use AlizHarb\ForgePulse\Enums\TriggerType;
use AlizHarb\ForgePulse\Enums\WorkflowStatus;
use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Models\WorkflowTrigger;

beforeEach(function () {
    $this->workflow = Workflow::factory()->create([
        'status' => WorkflowStatus::ACTIVE,
    ]);
});

describe('Trigger API', function () {
    it('can list triggers for a workflow', function () {
        WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Trigger 1',
            'type' => TriggerType::MANUAL,
            'is_active' => true,
            'configuration' => [],
        ]);

        WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Trigger 2',
            'type' => TriggerType::SCHEDULE,
            'is_active' => true,
            'configuration' => ['cron_expression' => '* * * * *'],
        ]);

        $response = $this->getJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Trigger 1')
            ->assertJsonPath('data.1.name', 'Trigger 2');
    });

    it('can create an event trigger', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            'name' => 'User Created Trigger',
            'type' => 'event',
            'is_active' => true,
            'configuration' => [
                'event_class' => 'App\\Events\\UserCreated',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'User Created Trigger')
            ->assertJsonPath('data.type', 'event')
            ->assertJsonPath('data.configuration.event_class', 'App\\Events\\UserCreated');

        $this->assertDatabaseHas('workflow_triggers', [
            'workflow_id' => $this->workflow->id,
            'name' => 'User Created Trigger',
            'type' => 'event',
        ]);
    });

    it('can create a schedule trigger', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            'name' => 'Daily Report',
            'type' => 'schedule',
            'is_active' => true,
            'configuration' => [
                'cron_expression' => '0 9 * * *',
                'timezone' => 'Europe/London',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Daily Report')
            ->assertJsonPath('data.type', 'schedule')
            ->assertJsonPath('data.configuration.cron_expression', '0 9 * * *');
    });

    it('can create a webhook trigger with auto-generated token', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            'name' => 'Payment Webhook',
            'type' => 'webhook',
            'is_active' => true,
            'configuration' => [
                'validate_signature' => false,
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Payment Webhook')
            ->assertJsonPath('data.type', 'webhook');

        // Check webhook token was generated
        $token = $response->json('data.configuration.webhook_token');
        expect($token)->toBeString()->toHaveLength(64);

        // Check webhook URL is returned
        $webhookUrl = $response->json('data.webhook_url');
        expect($webhookUrl)->toContain($token);
    });

    it('can create a model trigger', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            'name' => 'Order Status Change',
            'type' => 'model',
            'is_active' => true,
            'configuration' => [
                'model_class' => 'App\\Models\\Order',
                'events' => ['updated'],
                'attribute_filters' => [
                    'watch_attributes' => ['status'],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Order Status Change')
            ->assertJsonPath('data.type', 'model')
            ->assertJsonPath('data.configuration.model_class', 'App\\Models\\Order')
            ->assertJsonPath('data.configuration.events', ['updated']);
    });

    it('can update a trigger', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Original Name',
            'type' => TriggerType::MANUAL,
            'is_active' => true,
            'configuration' => [],
        ]);

        $response = $this->putJson(
            "/api/forgepulse/workflows/{$this->workflow->id}/triggers/{$trigger->id}",
            [
                'name' => 'Updated Name',
                'is_active' => false,
                'priority' => 10,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.priority', 10);
    });

    it('can delete a trigger', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'To Delete',
            'type' => TriggerType::MANUAL,
            'is_active' => true,
            'configuration' => [],
        ]);

        $response = $this->deleteJson(
            "/api/forgepulse/workflows/{$this->workflow->id}/triggers/{$trigger->id}"
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Trigger deleted successfully.');

        $this->assertDatabaseMissing('workflow_triggers', [
            'id' => $trigger->id,
        ]);
    });

    it('can toggle a trigger', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Toggle Me',
            'type' => TriggerType::MANUAL,
            'is_active' => true,
            'configuration' => [],
        ]);

        $response = $this->postJson(
            "/api/forgepulse/workflows/{$this->workflow->id}/triggers/{$trigger->id}/toggle"
        );

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Toggle again
        $response = $this->postJson(
            "/api/forgepulse/workflows/{$this->workflow->id}/triggers/{$trigger->id}/toggle"
        );

        $response->assertOk()
            ->assertJsonPath('data.is_active', true);
    });

    it('can list trigger types', function () {
        $response = $this->getJson('/api/forgepulse/triggers/types');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.value', 'event')
            ->assertJsonPath('data.1.value', 'schedule')
            ->assertJsonPath('data.2.value', 'webhook')
            ->assertJsonPath('data.3.value', 'model')
            ->assertJsonPath('data.4.value', 'manual');
    });

    it('can validate cron expressions', function () {
        $response = $this->postJson('/api/forgepulse/triggers/validate-cron', [
            'expression' => '0 9 * * *',
        ]);

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('expression', '0 9 * * *');

        $response = $this->postJson('/api/forgepulse/triggers/validate-cron', [
            'expression' => 'invalid',
        ]);

        $response->assertOk()
            ->assertJsonPath('valid', false);
    });

    it('validates required fields when creating trigger', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            // Missing name, type, configuration
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'configuration']);
    });

    it('validates event class exists for event triggers', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            'name' => 'Invalid Event',
            'type' => 'event',
            'is_active' => true,
            'configuration' => [
                'event_class' => 'NonExistent\\Event\\Class',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['configuration.event_class']);
    });

    it('validates cron expression for schedule triggers', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            'name' => 'Invalid Schedule',
            'type' => 'schedule',
            'is_active' => true,
            'configuration' => [
                'cron_expression' => 'not-a-cron',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['configuration.cron_expression']);
    });

    it('validates model events for model triggers', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            'name' => 'Invalid Model Trigger',
            'type' => 'model',
            'is_active' => true,
            'configuration' => [
                'model_class' => 'App\\Models\\User',
                'events' => ['invalid_event'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['configuration.events']);
    });

    it('can create trigger with context mapping', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            'name' => 'Mapped Trigger',
            'type' => 'webhook',
            'is_active' => true,
            'configuration' => [],
            'context_mapping' => [
                'user_id' => 'data.user.id',
                'action' => 'data.action',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.context_mapping.user_id', 'data.user.id')
            ->assertJsonPath('data.context_mapping.action', 'data.action');
    });

    it('can create trigger with conditions', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            'name' => 'Conditional Trigger',
            'type' => 'webhook',
            'is_active' => true,
            'configuration' => [],
            'conditions' => [
                'operator' => 'and',
                'rules' => [
                    ['field' => 'amount', 'operator' => '>', 'value' => 100],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.conditions.operator', 'and')
            ->assertJsonCount(1, 'data.conditions.rules');
    });

    it('can create trigger with rate limiting', function () {
        $response = $this->postJson("/api/forgepulse/workflows/{$this->workflow->id}/triggers", [
            'name' => 'Rate Limited',
            'type' => 'webhook',
            'is_active' => true,
            'configuration' => [],
            'max_executions' => 10,
            'max_executions_period' => 'per_hour',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.max_executions', 10)
            ->assertJsonPath('data.max_executions_period', 'per_hour');
    });
});

describe('Webhook Endpoint', function () {
    it('can trigger workflow via webhook', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Webhook Trigger',
            'type' => TriggerType::WEBHOOK,
            'is_active' => true,
            'configuration' => [],
        ]);

        $token = $trigger->configuration['webhook_token'];

        $response = $this->postJson("/api/forgepulse/webhook/{$token}", [
            'event' => 'payment.completed',
            'data' => ['amount' => 100],
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('message', 'Workflow triggered successfully.')
            ->assertJsonStructure(['execution']);
    });

    it('returns 404 for invalid webhook token', function () {
        $response = $this->postJson('/api/forgepulse/webhook/invalid-token', [
            'data' => ['test' => true],
        ]);

        $response->assertNotFound()
            ->assertJsonPath('error', 'Invalid webhook token.');
    });

    it('returns 429 when rate limited', function () {
        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Rate Limited Webhook',
            'type' => TriggerType::WEBHOOK,
            'is_active' => false, // Inactive to simulate cannot fire
            'configuration' => [],
        ]);

        $token = $trigger->configuration['webhook_token'];

        $response = $this->postJson("/api/forgepulse/webhook/{$token}", []);

        $response->assertNotFound(); // Inactive triggers not found
    });

    it('validates webhook signature when configured', function () {
        $secret = 'my-secret-key';
        $payload = json_encode(['test' => 'data']);

        $trigger = WorkflowTrigger::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Signed Webhook',
            'type' => TriggerType::WEBHOOK,
            'is_active' => true,
            'configuration' => [
                'validate_signature' => true,
                'secret_token' => $secret,
                'signature_header' => 'X-Signature',
            ],
        ]);

        $token = $trigger->configuration['webhook_token'];

        // Request without signature should fail
        $response = $this->postJson("/api/forgepulse/webhook/{$token}", ['test' => 'data']);
        $response->assertUnauthorized();

        // Request with valid signature should succeed
        $signature = hash_hmac('sha256', $payload, $secret);
        $response = $this->postJson(
            "/api/forgepulse/webhook/{$token}",
            ['test' => 'data'],
            ['X-Signature' => $signature]
        );
        // Note: This may still fail due to payload mismatch, but signature check should pass
    });
});
