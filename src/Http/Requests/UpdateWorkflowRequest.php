<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Http\Requests;

use AlizHarb\ForgePulse\Enums\StepType;
use AlizHarb\ForgePulse\Enums\WorkflowStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Workflow Request
 *
 * Validates incoming requests for updating existing workflows.
 */
class UpdateWorkflowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if permissions are enabled in config
        if (! config('forgepulse.permissions.enabled', true)) {
            return true;
        }

        // Get the workflow from the route
        $workflow = $this->route('workflow');

        // Check if user has permission to update this workflow
        return $this->user()?->can('update', $workflow) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::enum(WorkflowStatus::class)],
            'configuration' => ['nullable', 'array'],
            'is_template' => ['sometimes', 'boolean'],
            'user_id' => ['sometimes', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'version' => ['sometimes', 'string', 'max:50'],
            
            // Steps validation (optional for updates)
            'steps' => ['nullable', 'array'],
            'steps.*.id' => ['nullable', 'integer'], // If provided, updates existing step
            'steps.*.name' => ['required_with:steps.*', 'string', 'max:255'],
            'steps.*.description' => ['nullable', 'string', 'max:1000'],
            'steps.*.type' => ['required_with:steps.*', Rule::enum(StepType::class)],
            'steps.*.configuration' => ['required_with:steps.*', 'array'],
            'steps.*.conditions' => ['nullable', 'array'],
            'steps.*.position' => ['required_with:steps.*', 'integer', 'min:0'],
            'steps.*.x_position' => ['nullable', 'integer'],
            'steps.*.y_position' => ['nullable', 'integer'],
            'steps.*.parent_step_id' => ['nullable', 'integer'],
            'steps.*.step_identifier' => ['nullable', 'string', 'max:255'],
            'steps.*.parent_step_identifier' => ['nullable', 'string', 'max:255'],
            'steps.*.is_enabled' => ['nullable', 'boolean'],
            'steps.*.timeout' => ['nullable', 'integer', 'min:1'],
            'steps.*.execution_mode' => ['nullable', 'string', 'in:sequential,parallel'],
            'steps.*.parallel_group' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Workflow name must not exceed 255 characters.',
            'steps.*.name.required_with' => 'Each step must have a name.',
            'steps.*.type.required_with' => 'Each step must have a type.',
            'steps.*.configuration.required_with' => 'Each step must have a configuration.',
            'steps.*.position.required_with' => 'Each step must have a position.',
            'steps.*.step_identifier.max' => 'Step identifier must not exceed 255 characters.',
            'steps.*.parent_step_identifier.max' => 'Parent step identifier must not exceed 255 characters.',
        ];
    }
    
    /**
     * Validate that all parent_step_identifier references exist.
     *
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->has('steps')) {
                return;
            }

            $steps = $this->input('steps', []);
            $identifiers = [];

            // Collect all step identifiers
            foreach ($steps as $index => $step) {
                if (isset($step['step_identifier'])) {
                    if (in_array($step['step_identifier'], $identifiers)) {
                        $validator->errors()->add(
                            "steps.{$index}.step_identifier",
                            "The step identifier '{$step['step_identifier']}' is duplicated. Each step_identifier must be unique."
                        );
                    }
                    $identifiers[] = $step['step_identifier'];
                }
            }

            // Validate parent_step_identifier references
            foreach ($steps as $index => $step) {
                if (isset($step['parent_step_identifier']) && $step['parent_step_identifier'] !== null) {
                    if (! in_array($step['parent_step_identifier'], $identifiers)) {
                        $validator->errors()->add(
                            "steps.{$index}.parent_step_identifier",
                            "The parent_step_identifier '{$step['parent_step_identifier']}' does not reference any existing step_identifier."
                        );
                    }
                }
            }
        });
    }
}
