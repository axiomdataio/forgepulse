<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Http\Requests;

use AlizHarb\ForgePulse\Enums\StepType;
use AlizHarb\ForgePulse\Enums\WorkflowStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create Workflow Request
 *
 * Validates incoming requests for creating new workflows with steps.
 */
class CreateWorkflowRequest extends FormRequest
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

        // Check if user has permission to create workflows
        return $this->user()?->can('create', \AlizHarb\ForgePulse\Models\Workflow::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(WorkflowStatus::class)],
            'configuration' => ['nullable', 'array'],
            'is_template' => ['nullable', 'boolean'],
            'user_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'version' => ['nullable', 'string', 'max:50'],
            
            // Steps validation
            'steps' => ['nullable', 'array'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.description' => ['nullable', 'string', 'max:1000'],
            'steps.*.type' => ['required', Rule::enum(StepType::class)],
            'steps.*.configuration' => ['required', 'array'],
            'steps.*.conditions' => ['nullable', 'array'],
            'steps.*.position' => ['required', 'integer', 'min:0'],
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
            'name.required' => 'Workflow name is required.',
            'name.max' => 'Workflow name must not exceed 255 characters.',
            'status.required' => 'Workflow status is required.',
            'steps.*.name.required' => 'Each step must have a name.',
            'steps.*.type.required' => 'Each step must have a type.',
            'steps.*.configuration.required' => 'Each step must have a configuration.',
            'steps.*.position.required' => 'Each step must have a position.',
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

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $data = [];

        if (! $this->has('status')) {
            $data['status'] = WorkflowStatus::DRAFT->value;
        }

        if (! $this->has('is_template')) {
            $data['is_template'] = false;
        }

        if (! $this->has('version')) {
            $data['version'] = '1.0.0';
        }

        // Set user_id to authenticated user if not provided
        if (! $this->has('user_id') && auth()->check()) {
            $data['user_id'] = auth()->id();
        }

        if (! empty($data)) {
            $this->merge($data);
        }
    }
}
