<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Http\Controllers\Api;

use AlizHarb\ForgePulse\Models\ActionSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ActionSchemaManagementController extends Controller
{
    /**
     * List all action schemas.
     *
     * GET /api/forgepulse/action-schemas?type=external&category=payments
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActionSchema::query();

        // Filter by type
        if ($request->has('type')) {
            $query->type($request->input('type'));
        }

        // Filter by category
        if ($request->has('category')) {
            $query->category($request->input('category'));
        }

        // Filter by external/internal
        if ($request->has('is_external')) {
            $query->where('is_external', $request->boolean('is_external'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->active(); // Default to active only
        }

        $schemas = $query->orderBy('name')->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $schemas,
        ]);
    }

    /**
     * Get a specific action schema.
     *
     * GET /api/forgepulse/action-schemas/{id}
     */
    public function show(ActionSchema $actionSchema): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $actionSchema,
        ]);
    }

    /**
     * Create a new action schema.
     *
     * POST /api/forgepulse/action-schemas
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action_class' => 'required|string|max:255',
            'action_type' => 'required|string|in:internal,external,webhook,api',
            'method' => 'nullable|string|max:255',
            'schema' => 'required|array',
            'schema.summary' => 'required|string',
            'schema.parameters' => 'required|array',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'tags' => 'nullable|array',
            'is_active' => 'boolean',
            'is_external' => 'boolean',
            'config' => 'nullable|array',
        ]);

        $validated['source'] = 'manual';
        $validated['version'] = 1;

        $schema = ActionSchema::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Action schema created successfully',
            'data' => $schema,
        ], 201);
    }

    /**
     * Update an action schema.
     *
     * PUT /api/forgepulse/action-schemas/{id}
     */
    public function update(Request $request, ActionSchema $actionSchema): JsonResponse
    {
        // Check if schema is editable
        if (! $actionSchema->isEditable()) {
            return response()->json([
                'success' => false,
                'error' => 'This schema is generated from code and cannot be edited directly',
            ], 403);
        }

        $validated = $request->validate([
            'action_class' => 'sometimes|string|max:255',
            'action_type' => 'sometimes|string|in:internal,external,webhook,api',
            'method' => 'nullable|string|max:255',
            'schema' => 'sometimes|array',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|string|max:255',
            'tags' => 'nullable|array',
            'is_active' => 'boolean',
            'config' => 'nullable|array',
        ]);

        $actionSchema->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Action schema updated successfully',
            'data' => $actionSchema->fresh(),
        ]);
    }

    /**
     * Delete an action schema.
     *
     * DELETE /api/forgepulse/action-schemas/{id}
     */
    public function destroy(ActionSchema $actionSchema): JsonResponse
    {
        if (! $actionSchema->isEditable()) {
            return response()->json([
                'success' => false,
                'error' => 'This schema is generated from code and cannot be deleted',
            ], 403);
        }

        $actionSchema->delete();

        return response()->json([
            'success' => true,
            'message' => 'Action schema deleted successfully',
        ]);
    }

    /**
     * Sync code-based schemas to database.
     *
     * POST /api/forgepulse/action-schemas/sync
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classes' => 'required|array',
            'classes.*' => 'required|string',
        ]);

        $synced = 0;
        $errors = [];

        foreach ($validated['classes'] as $class) {
            try {
                // Skip if class doesn't exist or doesn't provide schema
                if (! class_exists($class) || ! method_exists($class, 'schema')) {
                    continue;
                }

                $schema = $class::schema();
                $method = $schema['method'] ?? null;

                // Check if already exists
                $existing = ActionSchema::where('action_class', $class)
                    ->where('method', $method)
                    ->first();

                if ($existing && $existing->source === 'code') {
                    // Update existing code-based schema
                    $existing->update([
                        'schema' => $schema,
                        'name' => $schema['summary'] ?? class_basename($class),
                        'description' => $schema['description'] ?? null,
                        'category' => $schema['x-category'] ?? 'general',
                        'tags' => $schema['tags'] ?? [],
                        'config' => [
                            'timeout' => $schema['x-timeout'] ?? null,
                        ],
                    ]);
                } elseif (! $existing) {
                    // Create new entry
                    ActionSchema::create([
                        'action_class' => $class,
                        'action_type' => 'internal',
                        'method' => $method,
                        'schema' => $schema,
                        'name' => $schema['summary'] ?? class_basename($class),
                        'description' => $schema['description'] ?? null,
                        'category' => $schema['x-category'] ?? 'general',
                        'tags' => $schema['tags'] ?? [],
                        'is_active' => true,
                        'is_external' => false,
                        'source' => 'code',
                        'version' => 1,
                        'config' => [
                            'timeout' => $schema['x-timeout'] ?? null,
                        ],
                    ]);
                }

                $synced++;
            } catch (\Exception $e) {
                $errors[] = [
                    'class' => $class,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Synced {$synced} action schemas",
            'synced_count' => $synced,
            'errors' => $errors,
        ]);
    }

    /**
     * Import external service schema.
     *
     * POST /api/forgepulse/action-schemas/import
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => 'required|url',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'operation_id' => 'required|string', // Which operation from the OpenAPI spec
        ]);

        // Fetch OpenAPI spec from URL
        try {
            $response = \Illuminate\Support\Facades\Http::get($validated['source_url']);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to fetch OpenAPI specification',
                ], 400);
            }

            $spec = $response->json();
            $operationId = $validated['operation_id'];

            // Find the operation in the spec
            $operation = null;
            foreach ($spec['paths'] ?? [] as $path => $methods) {
                foreach ($methods as $method => $op) {
                    if (($op['operationId'] ?? null) === $operationId) {
                        $operation = $op;
                        break 2;
                    }
                }
            }

            if (! $operation) {
                return response()->json([
                    'success' => false,
                    'error' => "Operation {$operationId} not found in OpenAPI spec",
                ], 404);
            }

            // Create schema entry
            $schema = ActionSchema::create([
                'action_class' => $operationId,
                'action_type' => 'external',
                'method' => null,
                'schema' => $operation,
                'name' => $validated['name'],
                'description' => $operation['description'] ?? null,
                'category' => $validated['category'],
                'tags' => $operation['tags'] ?? [],
                'is_active' => true,
                'is_external' => true,
                'source' => 'imported',
                'version' => 1,
                'config' => [
                    'source_url' => $validated['source_url'],
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'External service schema imported successfully',
                'data' => $schema,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to import schema: '.$e->getMessage(),
            ], 500);
        }
    }
}
