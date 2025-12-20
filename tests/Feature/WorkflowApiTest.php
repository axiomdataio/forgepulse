<?php

use AlizHarb\ForgePulse\Enums\StepType;
use AlizHarb\ForgePulse\Enums\WorkflowStatus;
use AlizHarb\ForgePulse\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Disable permissions for tests
    config(['forgepulse.permissions.enabled' => false]);
    config(['forgepulse.validation.enabled' => false]);
});

it('can list workflows via API', function () {
    Workflow::factory()->count(3)->create();

    $response = $this->getJson('/api/forgepulse/workflows');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'status', 'created_at'],
            ],
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

it('can show a single workflow via API', function () {
    $workflow = Workflow::factory()->create([
        'name' => 'Test Workflow',
        'description' => 'Test Description',
    ]);

    $response = $this->getJson("/api/forgepulse/workflows/{$workflow->id}");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $workflow->id,
                'name' => 'Test Workflow',
                'description' => 'Test Description',
            ],
        ]);
});

it('can create a workflow via API without steps', function () {
    $data = [
        'name' => 'New Workflow',
        'description' => 'A new workflow',
        'status' => WorkflowStatus::DRAFT->value,
    ];

    $response = $this->postJson('/api/forgepulse/workflows', $data);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'name' => 'New Workflow',
                'description' => 'A new workflow',
                'status' => 'draft',
            ],
        ]);

    $this->assertDatabaseHas('workflows', [
        'name' => 'New Workflow',
    ]);
});

it('can create a workflow with steps via API', function () {
    $data = [
        'name' => 'Workflow with Steps',
        'description' => 'Test workflow',
        'status' => WorkflowStatus::ACTIVE->value,
        'steps' => [
            [
                'name' => 'Step 1',
                'type' => StepType::DELAY->value,
                'configuration' => ['seconds' => 10],
                'position' => 1,
            ],
            [
                'name' => 'Step 2',
                'type' => StepType::NOTIFICATION->value,
                'configuration' => [
                    'notification_class' => 'App\\Notifications\\TestNotification',
                ],
                'position' => 2,
            ],
        ],
    ];

    $response = $this->postJson('/api/forgepulse/workflows', $data);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'name' => 'Workflow with Steps',
            ],
        ]);

    $workflow = Workflow::where('name', 'Workflow with Steps')->first();
    expect($workflow->steps)->toHaveCount(2);
    expect($workflow->steps[0]->name)->toBe('Step 1');
    expect($workflow->steps[1]->name)->toBe('Step 2');
});

it('validates required fields when creating workflow', function () {
    $response = $this->postJson('/api/forgepulse/workflows', [
        'description' => 'Missing name',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'status']);
});

it('validates step fields when creating workflow', function () {
    $data = [
        'name' => 'Test Workflow',
        'status' => WorkflowStatus::DRAFT->value,
        'steps' => [
            [
                'name' => 'Invalid Step',
                // Missing required type and configuration
                'position' => 1,
            ],
        ],
    ];

    $response = $this->postJson('/api/forgepulse/workflows', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['steps.0.type', 'steps.0.configuration']);
});

it('can update a workflow via API', function () {
    $workflow = Workflow::factory()->create([
        'name' => 'Original Name',
        'status' => WorkflowStatus::DRAFT->value,
    ]);

    $response = $this->putJson("/api/forgepulse/workflows/{$workflow->id}", [
        'name' => 'Updated Name',
        'status' => WorkflowStatus::ACTIVE->value,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $workflow->id,
                'name' => 'Updated Name',
                'status' => 'active',
            ],
        ]);

    $this->assertDatabaseHas('workflows', [
        'id' => $workflow->id,
        'name' => 'Updated Name',
    ]);
});

it('can update workflow steps via API', function () {
    $workflow = Workflow::factory()->create();
    $step = $workflow->steps()->create([
        'name' => 'Original Step',
        'type' => StepType::DELAY,
        'configuration' => ['seconds' => 5],
        'position' => 1,
    ]);

    $response = $this->putJson("/api/forgepulse/workflows/{$workflow->id}", [
        'steps' => [
            [
                'id' => $step->id,
                'name' => 'Updated Step',
                'type' => StepType::DELAY->value,
                'configuration' => ['seconds' => 10],
                'position' => 1,
            ],
        ],
    ]);

    $response->assertStatus(200);

    $step->refresh();
    expect($step->name)->toBe('Updated Step');
    expect($step->configuration['seconds'])->toBe(10);
});

it('can add new steps when updating workflow via API', function () {
    $workflow = Workflow::factory()->create();

    $response = $this->putJson("/api/forgepulse/workflows/{$workflow->id}", [
        'steps' => [
            [
                'name' => 'New Step',
                'type' => StepType::DELAY->value,
                'configuration' => ['seconds' => 5],
                'position' => 1,
            ],
        ],
    ]);

    $response->assertStatus(200);

    $workflow->refresh();
    expect($workflow->steps)->toHaveCount(1);
    expect($workflow->steps[0]->name)->toBe('New Step');
});

it('can delete a workflow via API', function () {
    $workflow = Workflow::factory()->create();

    $response = $this->deleteJson("/api/forgepulse/workflows/{$workflow->id}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Workflow deleted successfully.',
        ]);

    $this->assertSoftDeleted('workflows', [
        'id' => $workflow->id,
    ]);
});

it('returns 404 when workflow not found', function () {
    $response = $this->getJson('/api/forgepulse/workflows/99999');

    $response->assertStatus(404);
});

it('sets default values when creating workflow', function () {
    $response = $this->postJson('/api/forgepulse/workflows', [
        'name' => 'Test Workflow',
        // Status will be set to DRAFT by default
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'status' => 'draft',
                'is_template' => false,
                'version' => '1.0.0',
            ],
        ]);
});

it('can create workflow with conditional steps', function () {
    $data = [
        'name' => 'Conditional Workflow',
        'status' => WorkflowStatus::ACTIVE->value,
        'steps' => [
            [
                'name' => 'Conditional Step',
                'type' => StepType::DELAY->value,
                'configuration' => ['seconds' => 5],
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => 'user.role', 'operator' => '==', 'value' => 'admin'],
                    ],
                ],
                'position' => 1,
            ],
        ],
    ];

    $response = $this->postJson('/api/forgepulse/workflows', $data);

    $response->assertStatus(201);

    $workflow = Workflow::where('name', 'Conditional Workflow')->first();
    expect($workflow->steps[0]->conditions)->not->toBeNull();
    expect($workflow->steps[0]->conditions['operator'])->toBe('and');
});

it('can create workflow with step identifiers for branching', function () {
    $data = [
        'name' => 'Branching Workflow with Identifiers',
        'status' => WorkflowStatus::ACTIVE->value,
        'steps' => [
            [
                'step_identifier' => 'check_amount',
                'name' => 'Check Amount',
                'type' => StepType::CONDITION->value,
                'configuration' => [],
                'position' => 1,
            ],
            [
                'step_identifier' => 'small_order',
                'parent_step_identifier' => 'check_amount',
                'name' => 'Process Small Order',
                'type' => StepType::ACTION->value,
                'configuration' => ['action_class' => 'App\\Actions\\ProcessSmall'],
                'position' => 1,
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => 'amount', 'operator' => '<', 'value' => 100],
                    ],
                ],
            ],
            [
                'step_identifier' => 'large_order',
                'parent_step_identifier' => 'check_amount',
                'name' => 'Process Large Order',
                'type' => StepType::ACTION->value,
                'configuration' => ['action_class' => 'App\\Actions\\ProcessLarge'],
                'position' => 2,
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => 'amount', 'operator' => '>=', 'value' => 100],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/forgepulse/workflows', $data);

    $response->assertStatus(201);

    $workflow = Workflow::where('name', 'Branching Workflow with Identifiers')->first();
    expect($workflow->steps)->toHaveCount(3);

    $checkStep = $workflow->steps()->where('name', 'Check Amount')->first();
    $smallStep = $workflow->steps()->where('name', 'Process Small Order')->first();
    $largeStep = $workflow->steps()->where('name', 'Process Large Order')->first();

    expect($checkStep->parent_step_id)->toBeNull();
    expect($smallStep->parent_step_id)->toBe($checkStep->id);
    expect($largeStep->parent_step_id)->toBe($checkStep->id);
});

it('validates duplicate step identifiers', function () {
    $data = [
        'name' => 'Invalid Workflow',
        'status' => WorkflowStatus::ACTIVE->value,
        'steps' => [
            [
                'step_identifier' => 'duplicate',
                'name' => 'Step 1',
                'type' => StepType::ACTION->value,
                'configuration' => [],
                'position' => 1,
            ],
            [
                'step_identifier' => 'duplicate',
                'name' => 'Step 2',
                'type' => StepType::ACTION->value,
                'configuration' => [],
                'position' => 2,
            ],
        ],
    ];

    $response = $this->postJson('/api/forgepulse/workflows', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['steps.1.step_identifier']);
});

it('validates non-existent parent step identifier', function () {
    $data = [
        'name' => 'Invalid Parent Reference',
        'status' => WorkflowStatus::ACTIVE->value,
        'steps' => [
            [
                'step_identifier' => 'child',
                'parent_step_identifier' => 'non_existent',
                'name' => 'Child Step',
                'type' => StepType::ACTION->value,
                'configuration' => [],
                'position' => 1,
            ],
        ],
    ];

    $response = $this->postJson('/api/forgepulse/workflows', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['steps.0.parent_step_identifier']);
});

it('can create nested branching with identifiers', function () {
    $data = [
        'name' => 'Nested Branching',
        'status' => WorkflowStatus::ACTIVE->value,
        'steps' => [
            [
                'step_identifier' => 'root',
                'name' => 'Root',
                'type' => StepType::ACTION->value,
                'configuration' => [],
                'position' => 1,
            ],
            [
                'step_identifier' => 'branch_a',
                'parent_step_identifier' => 'root',
                'name' => 'Branch A',
                'type' => StepType::ACTION->value,
                'configuration' => [],
                'position' => 1,
            ],
            [
                'step_identifier' => 'branch_a_1',
                'parent_step_identifier' => 'branch_a',
                'name' => 'Branch A.1',
                'type' => StepType::ACTION->value,
                'configuration' => [],
                'position' => 1,
            ],
            [
                'step_identifier' => 'branch_a_2',
                'parent_step_identifier' => 'branch_a',
                'name' => 'Branch A.2',
                'type' => StepType::ACTION->value,
                'configuration' => [],
                'position' => 2,
            ],
        ],
    ];

    $response = $this->postJson('/api/forgepulse/workflows', $data);

    $response->assertStatus(201);

    $workflow = Workflow::where('name', 'Nested Branching')->first();
    
    $root = $workflow->steps()->where('name', 'Root')->first();
    $branchA = $workflow->steps()->where('name', 'Branch A')->first();
    $branchA1 = $workflow->steps()->where('name', 'Branch A.1')->first();
    $branchA2 = $workflow->steps()->where('name', 'Branch A.2')->first();

    expect($branchA->parent_step_id)->toBe($root->id);
    expect($branchA1->parent_step_id)->toBe($branchA->id);
    expect($branchA2->parent_step_id)->toBe($branchA->id);
});
