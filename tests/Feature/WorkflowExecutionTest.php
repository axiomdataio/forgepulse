<?php

use AlizHarb\ForgePulse\Jobs\ExecuteStepJob;
use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Models\WorkflowExecution;
use AlizHarb\ForgePulse\Models\WorkflowStep;
use AlizHarb\ForgePulse\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('executes a simple workflow step by step', function () {
    Queue::fake();

    $workflow = Workflow::factory()->create(['status' => 'active']);

    $step = WorkflowStep::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => 'delay',
        'configuration' => ['seconds' => 1],
    ]);

    $execution = WorkflowExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'pending',
        'context' => [],
    ]);

    $engine = app(WorkflowEngine::class);
    $engine->start($execution);

    $execution->refresh();

    expect($execution->status->value)->toBe('running')
        ->and($execution->started_at)->not->toBeNull();

    // Verify first step job was dispatched
    Queue::assertPushed(ExecuteStepJob::class, function ($job) use ($step) {
        return $job->stepId === $step->id;
    });
});

it('executes workflow step and advances to next', function () {
    $workflow = Workflow::factory()->create(['status' => 'active']);

    $step1 = WorkflowStep::factory()->create([
        'workflow_id' => $workflow->id,
        'position' => 1,
        'type' => 'delay',
        'configuration' => ['seconds' => 0],
    ]);

    $step2 = WorkflowStep::factory()->create([
        'workflow_id' => $workflow->id,
        'position' => 2,
        'parent_step_id' => $step1->id,
        'type' => 'delay',
        'configuration' => ['seconds' => 0],
    ]);

    $execution = WorkflowExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'running',
        'context' => [],
    ]);

    $engine = app(WorkflowEngine::class);

    // Execute first step
    $engine->executeStep($execution, $step1);

    $execution->refresh();
    expect($execution->completed_step_ids)->toContain($step1->id);

    // Advance should determine next step
    Queue::fake();
    $engine->advance($execution);

    Queue::assertPushed(ExecuteStepJob::class, function ($job) use ($step2) {
        return $job->stepId === $step2->id;
    });
});

it('handles workflow execution failures', function () {
    $workflow = Workflow::factory()->create();

    $step = WorkflowStep::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => 'action',
        'configuration' => ['action_class' => 'NonExistentClass'],
    ]);

    $execution = WorkflowExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'running',
    ]);

    $engine = app(WorkflowEngine::class);

    try {
        $engine->executeStep($execution, $step);
    } catch (\Exception $e) {
        // Expected
    }

    $execution->refresh();
    expect($execution->status->value)->toBe('failed');
});

it('executes steps in correct order', function () {
    $workflow = Workflow::factory()->create();

    $step1 = WorkflowStep::factory()->create([
        'workflow_id' => $workflow->id,
        'position' => 1,
        'type' => 'delay',
        'configuration' => ['seconds' => 0],
    ]);

    $step2 = WorkflowStep::factory()->create([
        'workflow_id' => $workflow->id,
        'position' => 2,
        'parent_step_id' => $step1->id,
        'type' => 'delay',
        'configuration' => ['seconds' => 0],
    ]);

    $execution = WorkflowExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'running',
    ]);

    $engine = app(WorkflowEngine::class);

    // Execute step 1
    $engine->executeStep($execution, $step1);

    // Execute step 2
    $engine->executeStep($execution, $step2);

    $logs = $execution->logs()->orderBy('created_at')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->workflow_step_id)->toBe($step1->id)
        ->and($logs[1]->workflow_step_id)->toBe($step2->id);
});

it('skips steps with unmet conditions', function () {
    $workflow = Workflow::factory()->create();

    $step = WorkflowStep::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => 'delay',
        'configuration' => ['seconds' => 0],
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                ['field' => 'should_run', 'operator' => '==', 'value' => true],
            ],
        ],
    ]);

    $execution = WorkflowExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'running',
        'context' => ['should_run' => false], // Condition will fail
    ]);

    $engine = app(WorkflowEngine::class);
    $engine->executeStep($execution, $step);

    $log = $execution->logs()->first();

    expect($log->status->value)->toBe('skipped');
});

it('can resume failed execution', function () {
    Queue::fake();

    $workflow = Workflow::factory()->create();

    $step = WorkflowStep::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => 'delay',
        'configuration' => ['seconds' => 0],
    ]);

    $execution = WorkflowExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'failed',
        'current_step_id' => $step->id,
        'error_message' => 'Test failure',
    ]);

    $execution->resumeExecution();

    $execution->refresh();
    expect($execution->status->value)->toBe('running')
        ->and($execution->error_message)->toBeNull();

    Queue::assertPushed(ExecuteStepJob::class);
});

it('completes workflow when no more steps', function () {
    $workflow = Workflow::factory()->create();

    $step = WorkflowStep::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => 'delay',
        'configuration' => ['seconds' => 0],
    ]);

    $execution = WorkflowExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'running',
        'completed_step_ids' => [$step->id],
    ]);

    $engine = app(WorkflowEngine::class);
    $engine->advance($execution);

    $execution->refresh();
    expect($execution->status->value)->toBe('completed')
        ->and($execution->completed_at)->not->toBeNull();
});

