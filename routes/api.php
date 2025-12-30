<?php

use AlizHarb\ForgePulse\Http\Controllers\Api\ExecutionApiController;
use AlizHarb\ForgePulse\Http\Controllers\Api\TriggerApiController;
use AlizHarb\ForgePulse\Http\Controllers\Api\WebhookTriggerController;
use AlizHarb\ForgePulse\Http\Controllers\Api\WorkflowApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ForgePulse API Routes
|--------------------------------------------------------------------------
|
| API routes for workflow monitoring and management.
| These routes are prefixed with 'api/forgepulse' by default.
|
*/

Route::prefix('api/forgepulse')
    ->middleware(config('forgepulse.api.middleware', ['api']))
    ->group(function () {
        // Workflow routes
        Route::get('/workflows', [WorkflowApiController::class, 'index'])->name('forgepulse.api.workflows.index');
        Route::post('/workflows', [WorkflowApiController::class, 'store'])->name('forgepulse.api.workflows.store');
        Route::get('/workflows/{workflow}', [WorkflowApiController::class, 'show'])->name('forgepulse.api.workflows.show');
        Route::put('/workflows/{workflow}', [WorkflowApiController::class, 'update'])->name('forgepulse.api.workflows.update');
        Route::delete('/workflows/{workflow}', [WorkflowApiController::class, 'destroy'])->name('forgepulse.api.workflows.destroy');

        // Trigger routes
        Route::get('/triggers/types', [TriggerApiController::class, 'types'])->name('forgepulse.api.triggers.types');
        Route::get('/triggers/types/{type}/context-schema', [TriggerApiController::class, 'contextSchema'])->name('forgepulse.api.triggers.context-schema');
        Route::post('/triggers/validate-cron', [TriggerApiController::class, 'validateCron'])->name('forgepulse.api.triggers.validate-cron');
        Route::post('/triggers/preview-mapping', [TriggerApiController::class, 'previewMapping'])->name('forgepulse.api.triggers.preview-mapping');

        Route::prefix('/workflows/{workflow}/triggers')->group(function () {
            Route::get('/', [TriggerApiController::class, 'index'])->name('forgepulse.api.triggers.index');
            Route::post('/', [TriggerApiController::class, 'store'])->name('forgepulse.api.triggers.store');
            Route::get('/{trigger}', [TriggerApiController::class, 'show'])->name('forgepulse.api.triggers.show');
            Route::put('/{trigger}', [TriggerApiController::class, 'update'])->name('forgepulse.api.triggers.update');
            Route::delete('/{trigger}', [TriggerApiController::class, 'destroy'])->name('forgepulse.api.triggers.destroy');
            Route::post('/{trigger}/toggle', [TriggerApiController::class, 'toggle'])->name('forgepulse.api.triggers.toggle');
        });

        // Execution routes
        Route::get('/executions', [ExecutionApiController::class, 'index'])->name('forgepulse.api.executions.index');
        Route::get('/executions/{execution}', [ExecutionApiController::class, 'show'])->name('forgepulse.api.executions.show');
        Route::post('/executions/{execution}/pause', [ExecutionApiController::class, 'pause'])->name('forgepulse.api.executions.pause');
        Route::post('/executions/{execution}/resume', [ExecutionApiController::class, 'resume'])->name('forgepulse.api.executions.resume');
    });

/*
|--------------------------------------------------------------------------
| Webhook Trigger Endpoint
|--------------------------------------------------------------------------
|
| This endpoint receives incoming webhook requests to trigger workflows.
| It uses token-based authentication and does not require session auth.
|
*/

Route::prefix('api/forgepulse')
    ->middleware(['api'])
    ->group(function () {
        Route::post('/webhook/{token}', [WebhookTriggerController::class, 'handle'])
            ->name('forgepulse.api.webhook.trigger');
    });
