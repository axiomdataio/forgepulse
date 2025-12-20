<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Http\Controllers\Api;

use AlizHarb\ForgePulse\Services\ActionSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ActionSchemaController extends Controller
{
    public function __construct(
        protected ActionSchema $schemaService
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
}
