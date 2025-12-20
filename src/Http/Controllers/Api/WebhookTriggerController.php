<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Http\Controllers\Api;

use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Services\TriggerHandlers\WebhookTriggerHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * Webhook Trigger Controller
 *
 * Handles incoming webhook triggers for workflows.
 */
class WebhookTriggerController extends Controller
{
    /**
     * Handle incoming webhook for a workflow.
     *
     * @param  Request  $request  The incoming request
     * @param  int  $workflowId  The workflow ID
     * @param  string  $token  The webhook token
     * @return JsonResponse
     */
    public function handle(Request $request, int $workflowId, string $token): JsonResponse
    {
        // Find workflow
        $workflow = Workflow::find($workflowId);

        if (! $workflow) {
            return response()->json([
                'error' => 'Workflow not found',
            ], 404);
        }

        // Verify workflow is active and has webhook trigger enabled
        if (! $workflow->canExecute() || ! $workflow->auto_trigger_enabled) {
            return response()->json([
                'error' => 'Workflow is not enabled',
            ], 403);
        }

        // Verify trigger type
        if ($workflow->trigger_type !== 'webhook') {
            return response()->json([
                'error' => 'Workflow is not configured for webhook triggers',
            ], 400);
        }

        // Verify token
        $config = $workflow->trigger_config?->getArrayCopy() ?? [];
        if (empty($config['token']) || $config['token'] !== $token) {
            return response()->json([
                'error' => 'Invalid webhook token',
            ], 401);
        }

        // Validate payload if validation rules are configured
        if (! empty($config['validation_rules'])) {
            $validator = Validator::make($request->all(), $config['validation_rules']);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
        }

        // Extract context from webhook
        $handler = app(WebhookTriggerHandler::class);
        $context = $handler->extractContext($request);

        // Apply payload mapping if configured
        if (! empty($config['payload_mapping'])) {
            $context = $this->applyPayloadMapping($context, $config['payload_mapping']);
        }

        // Execute workflow
        try {
            $execution = $workflow->execute($context);

            return response()->json([
                'message' => 'Workflow triggered successfully',
                'execution_id' => $execution->id,
                'workflow_id' => $workflow->id,
                'status' => $execution->status->value,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to execute workflow',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply payload mapping to context.
     *
     * @param  array<string, mixed>  $context  Original context
     * @param  array<string, string>  $mapping  Payload mapping configuration
     * @return array<string, mixed> Mapped context
     */
    private function applyPayloadMapping(array $context, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $targetKey => $sourceKey) {
            $value = data_get($context, $sourceKey);
            if ($value !== null) {
                data_set($mapped, $targetKey, $value);
            }
        }

        return array_merge($context, $mapped);
    }
}
