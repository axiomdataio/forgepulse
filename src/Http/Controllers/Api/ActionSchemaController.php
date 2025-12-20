<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Http\Controllers\Api;

use AlizHarb\ForgePulse\Services\ActionSchema;
use AlizHarb\ForgePulse\Services\SchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ActionSchemaController extends Controller
{
    public function __construct(
        protected ActionSchema $schemaService,
        protected SchemaValidator $validator
    ) {}

    /**
     * Get schema for a specific action class.
     *
     * GET /api/forgepulse/actions/schema?class=App\Actions\ProcessOrder&method=handle
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'class' => 'required|string',
            'method' => 'nullable|string',
        ]);

        try {
            $schema = $this->schemaService->getSchema(
                $request->input('class'),
                $request->input('method')
            );

            return response()->json([
                'success' => true,
                'data' => $schema,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get schemas for multiple action classes.
     *
     * POST /api/forgepulse/actions/schema/bulk
     * Body: { "classes": ["App\\Actions\\ProcessOrder", "App\\Actions\\SendEmail"] }
     */
    public function bulk(Request $request): JsonResponse
    {
        $request->validate([
            'classes' => 'required|array',
            'classes.*' => 'required|string',
        ]);

        try {
            $schemas = $this->schemaService->getBulkSchemas(
                $request->input('classes', [])
            );

            return response()->json([
                'success' => true,
                'data' => $schemas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Export all actions as OpenAPI 3.0 specification.
     *
     * POST /api/forgepulse/actions/openapi
     * Body: { "classes": ["App\\Actions\\ProcessOrder", ...] }
     */
    public function openapi(Request $request): JsonResponse
    {
        $request->validate([
            'classes' => 'required|array',
            'classes.*' => 'required|string',
        ]);

        try {
            $spec = $this->schemaService->exportAsOpenAPI(
                $request->input('classes', [])
            );

            return response()->json($spec);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validate parameters against action schema.
     *
     * POST /api/forgepulse/actions/validate
     * Body: { "class": "App\\Actions\\ProcessOrder", "parameters": {...} }
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'class' => 'required|string',
            'method' => 'nullable|string',
            'parameters' => 'required|array',
        ]);

        try {
            $schema = $this->schemaService->getSchema(
                $request->input('class'),
                $request->input('method')
            );

            $result = $this->validator->validate(
                $request->input('parameters'),
                $schema['parameters']
            );

            return response()->json([
                'valid' => $result['valid'],
                'errors' => $result['errors'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'errors' => [$e->getMessage()],
            ], 400);
        }
    }
}
