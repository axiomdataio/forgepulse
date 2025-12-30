<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Http\Controllers\Api;

use AlizHarb\ForgePulse\Enums\TriggerType;
use AlizHarb\ForgePulse\Http\Resources\TriggerResource;
use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Models\WorkflowTrigger;
use AlizHarb\ForgePulse\Services\Triggers\ScheduleTriggerRunner;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trigger API Controller
 *
 * Provides REST API endpoints for workflow trigger management.
 * Supports CRUD operations for all trigger types.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
class TriggerApiController extends Controller
{
    public function __construct(
        private readonly ScheduleTriggerRunner $scheduleRunner
    ) {}

    /**
     * List all triggers for a workflow.
     */
    public function index(Workflow $workflow): AnonymousResourceCollection
    {
        $triggers = $workflow->triggers()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return TriggerResource::collection($triggers);
    }

    /**
     * Get a specific trigger.
     */
    public function show(Workflow $workflow, WorkflowTrigger $trigger): TriggerResource
    {
        $this->ensureTriggerBelongsToWorkflow($workflow, $trigger);

        return new TriggerResource($trigger);
    }

    /**
     * Create a new trigger.
     */
    public function store(Request $request, Workflow $workflow): JsonResponse
    {
        $validated = $this->validateTrigger($request);

        // Validate type-specific configuration
        $this->validateTypeConfiguration(
            TriggerType::from($validated['type']),
            $validated['configuration']
        );

        // Inherit team_id from workflow for multi-tenancy
        if (config('forgepulse.teams.enabled', false)) {
            $validated['team_id'] = $workflow->team_id;
        }

        $trigger = $workflow->triggers()->create($validated);

        return (new TriggerResource($trigger->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing trigger.
     */
    public function update(Request $request, Workflow $workflow, WorkflowTrigger $trigger): TriggerResource
    {
        $this->ensureTriggerBelongsToWorkflow($workflow, $trigger);

        $validated = $this->validateTrigger($request, isUpdate: true);

        // If type or configuration changed, validate configuration
        $type = isset($validated['type'])
            ? TriggerType::from($validated['type'])
            : $trigger->type;

        $configuration = $validated['configuration']
            ?? $trigger->configuration->getArrayCopy();

        if (isset($validated['type']) || isset($validated['configuration'])) {
            $this->validateTypeConfiguration($type, $configuration);
        }

        $trigger->update(array_filter($validated, fn ($v) => $v !== null));

        return new TriggerResource($trigger->fresh());
    }

    /**
     * Delete a trigger.
     */
    public function destroy(Workflow $workflow, WorkflowTrigger $trigger): JsonResponse
    {
        $this->ensureTriggerBelongsToWorkflow($workflow, $trigger);

        $trigger->delete();

        return response()->json([
            'message' => 'Trigger deleted successfully.',
        ]);
    }

    /**
     * Toggle trigger active state.
     */
    public function toggle(Workflow $workflow, WorkflowTrigger $trigger): TriggerResource
    {
        $this->ensureTriggerBelongsToWorkflow($workflow, $trigger);

        $trigger->update(['is_active' => ! $trigger->is_active]);

        return new TriggerResource($trigger->fresh());
    }

    /**
     * Get trigger types and their schemas.
     */
    public function types(): JsonResponse
    {
        $types = collect(TriggerType::cases())->map(fn (TriggerType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'icon' => $type->icon(),
            'color' => $type->color(),
            'configuration_schema' => $type->configurationSchema(),
            'default_configuration' => $type->defaultConfiguration(),
            'requires_registration' => $type->requiresRegistration(),
        ]);

        return response()->json([
            'data' => $types->values(),
        ]);
    }

    /**
     * Validate a cron expression.
     */
    public function validateCron(Request $request): JsonResponse
    {
        $expression = $request->input('expression', '');

        $isValid = $this->scheduleRunner->isValidCronExpression($expression);

        $response = [
            'valid' => $isValid,
            'expression' => $expression,
        ];

        if ($isValid) {
            try {
                $cron = new CronExpression($expression);
                $response['next_run'] = $cron->getNextRunDate()->format('Y-m-d H:i:s');
                $response['description'] = $this->describeCronExpression($expression);
            } catch (\Exception) {
                // Ignore
            }
        }

        return response()->json($response);
    }

    /**
     * Validate trigger request data.
     *
     * @return array<string, mixed>
     */
    protected function validateTrigger(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => [$isUpdate ? 'sometimes' : 'required', Rule::enum(TriggerType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'configuration' => [$isUpdate ? 'sometimes' : 'required', 'array'],
            'conditions' => ['nullable', 'array'],
            'context_mapping' => ['nullable', 'array'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'max_executions' => ['nullable', 'integer', 'min:1'],
            'max_executions_period' => ['nullable', 'string', 'in:per_minute,per_hour,per_day'],
        ];

        return Validator::make($request->all(), $rules, [
            'name.required' => 'Trigger name is required.',
            'type.required' => 'Trigger type is required.',
            'configuration.required' => 'Trigger configuration is required.',
        ])->validate();
    }

    /**
     * Validate type-specific configuration.
     *
     * @param  array<string, mixed>  $configuration
     *
     * @throws ValidationException
     */
    protected function validateTypeConfiguration(TriggerType $type, array $configuration): void
    {
        $errors = [];

        match ($type) {
            TriggerType::EVENT => $errors = $this->validateEventConfiguration($configuration),
            TriggerType::SCHEDULE => $errors = $this->validateScheduleConfiguration($configuration),
            TriggerType::WEBHOOK => $errors = $this->validateWebhookConfiguration($configuration),
            TriggerType::MODEL => $errors = $this->validateModelConfiguration($configuration),
            TriggerType::MANUAL => null,
        };

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate event trigger configuration.
     *
     * @param  array<string, mixed>  $configuration
     * @return array<string, array<string>>
     */
    protected function validateEventConfiguration(array $configuration): array
    {
        $errors = [];

        if (empty($configuration['event_class'])) {
            $errors['configuration.event_class'] = ['Event class is required for event triggers.'];
        } elseif (! class_exists($configuration['event_class'])) {
            $errors['configuration.event_class'] = ['Event class does not exist: '.$configuration['event_class']];
        }

        return $errors;
    }

    /**
     * Validate schedule trigger configuration.
     *
     * @param  array<string, mixed>  $configuration
     * @return array<string, array<string>>
     */
    protected function validateScheduleConfiguration(array $configuration): array
    {
        $errors = [];

        if (empty($configuration['cron_expression'])) {
            $errors['configuration.cron_expression'] = ['Cron expression is required for schedule triggers.'];
        } elseif (! $this->scheduleRunner->isValidCronExpression($configuration['cron_expression'])) {
            $errors['configuration.cron_expression'] = ['Invalid cron expression: '.$configuration['cron_expression']];
        }

        if (! empty($configuration['timezone'])) {
            try {
                new \DateTimeZone($configuration['timezone']);
            } catch (\Exception) {
                $errors['configuration.timezone'] = ['Invalid timezone: '.$configuration['timezone']];
            }
        }

        return $errors;
    }

    /**
     * Validate webhook trigger configuration.
     *
     * @param  array<string, mixed>  $configuration
     * @return array<string, array<string>>
     */
    protected function validateWebhookConfiguration(array $configuration): array
    {
        $errors = [];

        // Validate signature settings
        if (! empty($configuration['validate_signature']) && empty($configuration['secret_token'])) {
            $errors['configuration.secret_token'] = ['Secret token is required when signature validation is enabled.'];
        }

        // Validate allowed IPs format
        if (! empty($configuration['allowed_ips']) && ! is_array($configuration['allowed_ips'])) {
            $errors['configuration.allowed_ips'] = ['Allowed IPs must be an array.'];
        }

        return $errors;
    }

    /**
     * Validate model trigger configuration.
     *
     * @param  array<string, mixed>  $configuration
     * @return array<string, array<string>>
     */
    protected function validateModelConfiguration(array $configuration): array
    {
        $errors = [];

        if (empty($configuration['model_class'])) {
            $errors['configuration.model_class'] = ['Model class is required for model triggers.'];
        } elseif (! class_exists($configuration['model_class'])) {
            $errors['configuration.model_class'] = ['Model class does not exist: '.$configuration['model_class']];
        }

        if (empty($configuration['events'])) {
            $errors['configuration.events'] = ['At least one model event is required.'];
        } else {
            $validEvents = ['created', 'updated', 'deleted'];
            foreach ($configuration['events'] as $event) {
                if (! in_array($event, $validEvents, true)) {
                    $errors['configuration.events'] = ['Invalid model event: '.$event.'. Valid events: '.implode(', ', $validEvents)];
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Generate a human-readable description of a cron expression.
     */
    protected function describeCronExpression(string $expression): string
    {
        // Basic descriptions for common patterns
        $descriptions = [
            '* * * * *' => 'Every minute',
            '0 * * * *' => 'Every hour',
            '0 0 * * *' => 'Daily at midnight',
            '0 0 * * 0' => 'Weekly on Sunday at midnight',
            '0 0 1 * *' => 'Monthly on the 1st at midnight',
        ];

        return $descriptions[$expression] ?? 'Custom schedule';
    }

    /**
     * Ensure the trigger belongs to the workflow.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function ensureTriggerBelongsToWorkflow(Workflow $workflow, WorkflowTrigger $trigger): void
    {
        if ($trigger->workflow_id !== $workflow->id) {
            abort(404, 'Trigger not found for this workflow.');
        }
    }
}
