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
            'context_schema' => $type->contextSchema(),
            'example_context_data' => $type->exampleContextData(),
        ]);

        return response()->json([
            'data' => $types->values(),
        ]);
    }

    /**
     * Get context schema for a specific trigger type.
     * Helps users understand what data is available for context_mapping.
     */
    public function contextSchema(Request $request, string $type): JsonResponse
    {
        $triggerType = TriggerType::tryFrom($type);

        if (! $triggerType) {
            return response()->json([
                'error' => "Invalid trigger type: {$type}",
                'valid_types' => array_column(TriggerType::cases(), 'value'),
            ], 400);
        }

        return response()->json([
            'type' => $triggerType->value,
            'label' => $triggerType->label(),
            'context_schema' => $triggerType->contextSchema(),
            'example_data' => $triggerType->exampleContextData(),
            'example_mapping' => $this->getExampleMapping($triggerType),
        ]);
    }

    /**
     * Preview context mapping for a trigger.
     * Allows users to test their mapping against example data.
     */
    public function previewMapping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'context_mapping' => ['required', 'array'],
            'sample_data' => ['nullable', 'array'],
        ]);

        $triggerType = TriggerType::tryFrom($validated['type']);

        if (! $triggerType) {
            return response()->json([
                'error' => "Invalid trigger type: {$validated['type']}",
            ], 400);
        }

        // Use provided sample data or example data
        $sourceData = $validated['sample_data'] ?? $triggerType->exampleContextData();

        // Apply the mapping
        $mappedContext = [];
        foreach ($validated['context_mapping'] as $contextKey => $sourcePath) {
            $mappedContext[$contextKey] = data_get($sourceData, $sourcePath);
        }

        return response()->json([
            'source_data' => $sourceData,
            'context_mapping' => $validated['context_mapping'],
            'result' => $mappedContext,
            'unmapped_fields' => $this->findUnmappedFields($sourceData, $validated['context_mapping']),
        ]);
    }

    /**
     * Get example context mapping for a trigger type.
     *
     * @return array<string, string>
     */
    protected function getExampleMapping(TriggerType $type): array
    {
        return match ($type) {
            TriggerType::EVENT => [
                'user_id' => 'user.id',
                'user_email' => 'user.email',
                'user_name' => 'user.name',
            ],
            TriggerType::SCHEDULE => [
                'run_time' => 'scheduled_at',
                'schedule' => 'cron_expression',
            ],
            TriggerType::WEBHOOK => [
                'event_type' => 'payload.type',
                'payment_id' => 'payload.data.object.id',
                'amount' => 'payload.data.object.amount',
                'customer_id' => 'payload.data.object.customer',
                'source_ip' => 'ip',
            ],
            TriggerType::MODEL => [
                'id' => 'model.id',
                'status' => 'model.status',
                'previous_status' => 'original.status',
                'event_type' => 'event',
                'changed_attributes' => 'changes',
            ],
            TriggerType::MANUAL => [
                'user_id' => 'user_id',
                'action' => 'action',
            ],
        };
    }

    /**
     * Find fields in source data that are not mapped.
     *
     * @return array<string>
     */
    protected function findUnmappedFields(array $data, array $mapping, string $prefix = ''): array
    {
        $unmapped = [];
        $mappedPaths = array_values($mapping);

        foreach ($data as $key => $value) {
            $path = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && ! $this->isAssociativeArray($value)) {
                // Skip numeric arrays
                if (! in_array($path, $mappedPaths)) {
                    $unmapped[] = $path;
                }
            } elseif (is_array($value)) {
                $unmapped = array_merge($unmapped, $this->findUnmappedFields($value, $mapping, $path));
            } else {
                if (! in_array($path, $mappedPaths)) {
                    $unmapped[] = $path;
                }
            }
        }

        return $unmapped;
    }

    /**
     * Check if an array is associative.
     */
    protected function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
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
     * Introspect a class to discover its properties for context mapping.
     * Works with Event classes, Model classes, or any PHP class.
     */
    public function introspectClass(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:event,model'],
        ]);

        $className = $validated['class'];
        $type = $validated['type'] ?? 'event';

        if (! class_exists($className)) {
            return response()->json([
                'error' => "Class not found: {$className}",
                'suggestion' => 'Ensure the fully qualified class name is correct (e.g., App\\Events\\OrderPlaced)',
            ], 404);
        }

        try {
            $reflection = new \ReflectionClass($className);
            $result = [
                'class' => $className,
                'type' => $type,
                'properties' => $this->getClassProperties($reflection),
                'methods' => $this->getRelevantMethods($reflection),
                'context_paths' => [],
                'example_mapping' => [],
            ];

            // Build context paths based on type
            if ($type === 'model') {
                $result['context_paths'] = $this->buildModelContextPaths($className, $reflection);
                $result['available_paths'] = $this->getModelAvailablePaths($className);
            } else {
                $result['context_paths'] = $this->buildEventContextPaths($reflection);
            }

            // Generate example mapping
            $result['example_mapping'] = $this->generateExampleMapping($result['context_paths']);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to introspect class',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get public properties from a class.
     *
     * @return array<array{name: string, type: string|null, description: string}>
     */
    protected function getClassProperties(\ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();
            $typeName = $type ? $this->getTypeName($type) : 'mixed';

            $properties[] = [
                'name' => $property->getName(),
                'type' => $typeName,
                'path' => $property->getName(),
                'nullable' => $type?->allowsNull() ?? true,
                'description' => $this->getPropertyDescription($property),
            ];
        }

        // Also check constructor parameters (for promoted properties)
        $constructor = $reflection->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                // Skip if already found as property
                if (collect($properties)->contains('name', $param->getName())) {
                    continue;
                }

                // Check if it's a promoted property
                if ($param->isPromoted()) {
                    $type = $param->getType();
                    $typeName = $type ? $this->getTypeName($type) : 'mixed';

                    $properties[] = [
                        'name' => $param->getName(),
                        'type' => $typeName,
                        'path' => $param->getName(),
                        'nullable' => $type?->allowsNull() ?? true,
                        'description' => "Constructor parameter (promoted property)",
                    ];
                }
            }
        }

        return $properties;
    }

    /**
     * Get relevant methods for context (toArray, broadcastWith, etc).
     *
     * @return array<array{name: string, returns: string|null}>
     */
    protected function getRelevantMethods(\ReflectionClass $reflection): array
    {
        $relevantMethods = ['toArray', 'broadcastWith', 'jsonSerialize', 'getAttributes'];
        $methods = [];

        foreach ($relevantMethods as $methodName) {
            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                $returnType = $method->getReturnType();

                $methods[] = [
                    'name' => $methodName,
                    'returns' => $returnType ? $this->getTypeName($returnType) : 'mixed',
                    'note' => $this->getMethodNote($methodName),
                ];
            }
        }

        return $methods;
    }

    /**
     * Build context paths for an event class.
     *
     * @return array<array{path: string, type: string, description: string, nested_paths?: array}>
     */
    protected function buildEventContextPaths(\ReflectionClass $reflection): array
    {
        $paths = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();
            $typeName = $type ? $this->getTypeName($type) : 'mixed';
            $propertyName = $property->getName();

            $pathInfo = [
                'path' => $propertyName,
                'type' => $typeName,
                'description' => "Access via \"{$propertyName}\"",
            ];

            // If it's a class type, try to get nested properties
            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                $nestedClass = $type->getName();
                if (class_exists($nestedClass)) {
                    $pathInfo['nested_paths'] = $this->getNestedPaths($nestedClass, $propertyName);
                }
            }

            $paths[] = $pathInfo;
        }

        // Check constructor for promoted properties
        $constructor = $reflection->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                if (! $param->isPromoted()) {
                    continue;
                }

                $existingPaths = collect($paths)->pluck('path')->toArray();
                if (in_array($param->getName(), $existingPaths)) {
                    continue;
                }

                $type = $param->getType();
                $typeName = $type ? $this->getTypeName($type) : 'mixed';
                $paramName = $param->getName();

                $pathInfo = [
                    'path' => $paramName,
                    'type' => $typeName,
                    'description' => "Access via \"{$paramName}\"",
                ];

                if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                    $nestedClass = $type->getName();
                    if (class_exists($nestedClass)) {
                        $pathInfo['nested_paths'] = $this->getNestedPaths($nestedClass, $paramName);
                    }
                }

                $paths[] = $pathInfo;
            }
        }

        return $paths;
    }

    /**
     * Get nested paths for a class (e.g., user.id, user.email).
     *
     * @return array<array{path: string, type: string}>
     */
    protected function getNestedPaths(string $className, string $prefix, int $depth = 0): array
    {
        if ($depth > 2) {
            return []; // Prevent infinite recursion
        }

        $paths = [];

        try {
            $reflection = new \ReflectionClass($className);

            // Check if it's an Eloquent model
            if ($reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class)) {
                return $this->getModelNestedPaths($className, $prefix);
            }

            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $type = $property->getType();
                $typeName = $type ? $this->getTypeName($type) : 'mixed';

                $paths[] = [
                    'path' => "{$prefix}.{$property->getName()}",
                    'type' => $typeName,
                ];
            }
        } catch (\Exception) {
            // Ignore reflection errors
        }

        return $paths;
    }

    /**
     * Get nested paths for an Eloquent model.
     *
     * @return array<array{path: string, type: string}>
     */
    protected function getModelNestedPaths(string $className, string $prefix): array
    {
        $paths = [];

        try {
            // Try to get fillable/visible attributes from the model
            $reflection = new \ReflectionClass($className);

            // Get fillable property if it exists
            if ($reflection->hasProperty('fillable')) {
                $fillableProperty = $reflection->getProperty('fillable');
                $fillableProperty->setAccessible(true);

                // Create instance to get default value
                $instance = $reflection->newInstanceWithoutConstructor();
                $fillable = $fillableProperty->getValue($instance);

                foreach ($fillable as $attribute) {
                    $paths[] = [
                        'path' => "{$prefix}.{$attribute}",
                        'type' => 'mixed',
                        'source' => 'fillable',
                    ];
                }
            }

            // Add common model attributes
            $commonAttributes = ['id', 'created_at', 'updated_at'];
            foreach ($commonAttributes as $attr) {
                if (! collect($paths)->contains('path', "{$prefix}.{$attr}")) {
                    $paths[] = [
                        'path' => "{$prefix}.{$attr}",
                        'type' => $attr === 'id' ? 'int' : 'datetime',
                        'source' => 'common',
                    ];
                }
            }
        } catch (\Exception) {
            // Return basic paths if reflection fails
            $paths = [
                ['path' => "{$prefix}.id", 'type' => 'int'],
                ['path' => "{$prefix}.*", 'type' => 'mixed', 'note' => 'All model attributes'],
            ];
        }

        return $paths;
    }

    /**
     * Build context paths for a model trigger.
     *
     * @return array<array{path: string, type: string, description: string}>
     */
    protected function buildModelContextPaths(string $className, \ReflectionClass $reflection): array
    {
        $paths = [
            [
                'path' => 'model',
                'type' => 'object',
                'description' => 'The model as array (all attributes)',
            ],
            [
                'path' => 'model_class',
                'type' => 'string',
                'description' => 'Fully qualified class name',
                'example' => $className,
            ],
            [
                'path' => 'model_id',
                'type' => 'mixed',
                'description' => 'Primary key value',
            ],
            [
                'path' => 'event',
                'type' => 'string',
                'description' => 'Event type: created, updated, or deleted',
            ],
            [
                'path' => 'changes',
                'type' => 'object',
                'description' => 'Changed attributes (updated event only)',
            ],
            [
                'path' => 'original',
                'type' => 'object',
                'description' => 'Original values (updated event only)',
            ],
        ];

        // Add model-specific paths
        $modelPaths = $this->getModelNestedPaths($className, 'model');
        foreach ($modelPaths as $modelPath) {
            $paths[] = array_merge($modelPath, [
                'description' => "Model attribute",
            ]);
        }

        return $paths;
    }

    /**
     * Get available paths for a model by examining its structure.
     *
     * @return array<string, mixed>
     */
    protected function getModelAvailablePaths(string $className): array
    {
        $result = [
            'fillable' => [],
            'casts' => [],
            'dates' => [],
            'relationships' => [],
        ];

        try {
            $reflection = new \ReflectionClass($className);
            $instance = $reflection->newInstanceWithoutConstructor();

            // Get fillable
            if ($reflection->hasProperty('fillable')) {
                $prop = $reflection->getProperty('fillable');
                $prop->setAccessible(true);
                $result['fillable'] = $prop->getValue($instance);
            }

            // Get casts
            if ($reflection->hasMethod('getCasts')) {
                try {
                    $result['casts'] = $instance->getCasts();
                } catch (\Exception) {
                    // Ignore
                }
            }

            // Find relationship methods
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getNumberOfParameters() > 0) {
                    continue;
                }

                $returnType = $method->getReturnType();
                if ($returnType instanceof \ReflectionNamedType) {
                    $typeName = $returnType->getName();
                    if (str_contains($typeName, 'Relation') || 
                        in_array($typeName, ['HasMany', 'HasOne', 'BelongsTo', 'BelongsToMany'])) {
                        $result['relationships'][] = $method->getName();
                    }
                }
            }
        } catch (\Exception) {
            // Ignore
        }

        return $result;
    }

    /**
     * Generate example context mapping from paths.
     *
     * @param  array  $paths
     * @return array<string, string>
     */
    protected function generateExampleMapping(array $paths): array
    {
        $mapping = [];

        foreach ($paths as $pathInfo) {
            $path = $pathInfo['path'];

            // Skip wildcard paths
            if (str_contains($path, '*')) {
                continue;
            }

            // Create a sensible context key
            $key = str_replace('.', '_', $path);

            // Limit to first 5 paths
            if (count($mapping) >= 5) {
                break;
            }

            $mapping[$key] = $path;

            // Also add nested paths if available
            if (isset($pathInfo['nested_paths'])) {
                foreach (array_slice($pathInfo['nested_paths'], 0, 3) as $nested) {
                    $nestedKey = str_replace('.', '_', $nested['path']);
                    $mapping[$nestedKey] = $nested['path'];

                    if (count($mapping) >= 8) {
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Get the string representation of a reflection type.
     */
    protected function getTypeName(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(
                fn ($t) => $t instanceof \ReflectionNamedType ? $t->getName() : 'mixed',
                $type->getTypes()
            ));
        }

        return 'mixed';
    }

    /**
     * Get description for a property from docblock.
     */
    protected function getPropertyDescription(\ReflectionProperty $property): string
    {
        $docComment = $property->getDocComment();

        if ($docComment) {
            // Try to extract @var description
            if (preg_match('/@var\s+\S+\s+(.+)$/m', $docComment, $matches)) {
                return trim($matches[1]);
            }
        }

        return "Public property";
    }

    /**
     * Get note for a method.
     */
    protected function getMethodNote(string $methodName): string
    {
        return match ($methodName) {
            'toArray' => 'If implemented, trigger will use this for context data',
            'broadcastWith' => 'Fallback if toArray is not available',
            'jsonSerialize' => 'Used for JSON serialization',
            'getAttributes' => 'Eloquent model attributes',
            default => '',
        };
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
