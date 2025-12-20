<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Http\Controllers\Api;

use AlizHarb\ForgePulse\Http\Requests\CreateWorkflowRequest;
use AlizHarb\ForgePulse\Http\Requests\UpdateWorkflowRequest;
use AlizHarb\ForgePulse\Http\Resources\WorkflowResource;
use AlizHarb\ForgePulse\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Workflow API Controller
 *
 * Provides REST API endpoints for workflow management and monitoring.
 */
class WorkflowApiController extends Controller
{
    /**
     * List all workflows.
     */
    public function index(): AnonymousResourceCollection
    {
        $workflows = Workflow::with(['steps', 'executions'])
            ->latest()
            ->paginate(20);

        return WorkflowResource::collection($workflows);
    }

    /**
     * Get a specific workflow.
     */
    public function show(Workflow $workflow): WorkflowResource
    {
        $workflow->load(['steps', 'executions.logs']);

        return new WorkflowResource($workflow);
    }

    /**
     * Create a new workflow.
     */
    public function store(CreateWorkflowRequest $request): JsonResponse
    {
        $workflow = DB::transaction(function () use ($request) {
            // Create the workflow
            $workflow = Workflow::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'status' => $request->input('status'),
                'configuration' => $request->input('configuration'),
                'is_template' => $request->input('is_template', false),
                'user_id' => $request->input('user_id'),
                'team_id' => $request->input('team_id'),
                'version' => $request->input('version', '1.0.0'),
            ]);

            // Create steps if provided
            if ($request->has('steps') && is_array($request->input('steps'))) {
                foreach ($request->input('steps') as $stepData) {
                    $workflow->steps()->create([
                        'name' => $stepData['name'],
                        'description' => $stepData['description'] ?? null,
                        'type' => $stepData['type'],
                        'configuration' => $stepData['configuration'],
                        'conditions' => $stepData['conditions'] ?? null,
                        'position' => $stepData['position'],
                        'x_position' => $stepData['x_position'] ?? null,
                        'y_position' => $stepData['y_position'] ?? null,
                        'parent_step_id' => $stepData['parent_step_id'] ?? null,
                        'is_enabled' => $stepData['is_enabled'] ?? true,
                        'timeout' => $stepData['timeout'] ?? null,
                        'execution_mode' => $stepData['execution_mode'] ?? 'sequential',
                        'parallel_group' => $stepData['parallel_group'] ?? null,
                    ]);
                }
            }

            // Validate workflow structure if validator is configured
            if (config('forgepulse.validation.enabled', true)) {
                try {
                    $workflow->validate();
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException('Workflow validation failed: '.$e->getMessage());
                }
            }

            return $workflow;
        });

        $workflow->load(['steps', 'executions']);

        return (new WorkflowResource($workflow))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing workflow.
     */
    public function update(UpdateWorkflowRequest $request, Workflow $workflow): WorkflowResource
    {
        DB::transaction(function () use ($request, $workflow) {
            // Update workflow attributes
            $updateData = array_filter([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'status' => $request->input('status'),
                'configuration' => $request->input('configuration'),
                'is_template' => $request->input('is_template'),
                'user_id' => $request->input('user_id'),
                'team_id' => $request->input('team_id'),
                'version' => $request->input('version'),
            ], fn ($value) => $value !== null);

            $workflow->update($updateData);

            // Update steps if provided
            if ($request->has('steps') && is_array($request->input('steps'))) {
                foreach ($request->input('steps') as $stepData) {
                    if (isset($stepData['id'])) {
                        // Update existing step
                        $step = $workflow->steps()->find($stepData['id']);
                        if ($step) {
                            $step->update([
                                'name' => $stepData['name'] ?? $step->name,
                                'description' => $stepData['description'] ?? $step->description,
                                'type' => $stepData['type'] ?? $step->type,
                                'configuration' => $stepData['configuration'] ?? $step->configuration,
                                'conditions' => $stepData['conditions'] ?? $step->conditions,
                                'position' => $stepData['position'] ?? $step->position,
                                'x_position' => $stepData['x_position'] ?? $step->x_position,
                                'y_position' => $stepData['y_position'] ?? $step->y_position,
                                'parent_step_id' => $stepData['parent_step_id'] ?? $step->parent_step_id,
                                'is_enabled' => $stepData['is_enabled'] ?? $step->is_enabled,
                                'timeout' => $stepData['timeout'] ?? $step->timeout,
                                'execution_mode' => $stepData['execution_mode'] ?? $step->execution_mode,
                                'parallel_group' => $stepData['parallel_group'] ?? $step->parallel_group,
                            ]);
                        }
                    } else {
                        // Create new step
                        $workflow->steps()->create([
                            'name' => $stepData['name'],
                            'description' => $stepData['description'] ?? null,
                            'type' => $stepData['type'],
                            'configuration' => $stepData['configuration'],
                            'conditions' => $stepData['conditions'] ?? null,
                            'position' => $stepData['position'],
                            'x_position' => $stepData['x_position'] ?? null,
                            'y_position' => $stepData['y_position'] ?? null,
                            'parent_step_id' => $stepData['parent_step_id'] ?? null,
                            'is_enabled' => $stepData['is_enabled'] ?? true,
                            'timeout' => $stepData['timeout'] ?? null,
                            'execution_mode' => $stepData['execution_mode'] ?? 'sequential',
                            'parallel_group' => $stepData['parallel_group'] ?? null,
                        ]);
                    }
                }
            }

            // Validate workflow structure if validator is configured
            if (config('forgepulse.validation.enabled', true)) {
                try {
                    $workflow->validate();
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException('Workflow validation failed: '.$e->getMessage());
                }
            }
        });

        $workflow->load(['steps', 'executions']);

        return new WorkflowResource($workflow);
    }

    /**
     * Delete a workflow.
     */
    public function destroy(Workflow $workflow): JsonResponse
    {
        // Check if user is authorized to delete
        if (config('forgepulse.permissions.enabled', true)) {
            $this->authorize('delete', $workflow);
        }

        // Soft delete the workflow
        $workflow->delete();

        return response()->json([
            'message' => 'Workflow deleted successfully.',
        ], 200);
    }
}
