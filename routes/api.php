<?php

use AlizHarb\ForgePulse\Http\Controllers\Api\ActionSchemaController;
use AlizHarb\ForgePulse\Http\Controllers\Api\ActionSchemaManagementController;
use AlizHarb\ForgePulse\Http\Controllers\Api\ExecutionApiController;
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

        // Execution routes
        Route::get('/executions', [ExecutionApiController::class, 'index'])->name('forgepulse.api.executions.index');
        Route::get('/executions/{execution}', [ExecutionApiController::class, 'show'])->name('forgepulse.api.executions.show');
        Route::post('/executions/{execution}/pause', [ExecutionApiController::class, 'pause'])->name('forgepulse.api.executions.pause');
        Route::post('/executions/{execution}/resume', [ExecutionApiController::class, 'resume'])->name('forgepulse.api.executions.resume');

        // Action schema routes
        Route::get('/actions/schema', [ActionSchemaController::class, 'show'])->name('forgepulse.api.actions.schema');
        Route::post('/actions/schema/bulk', [ActionSchemaController::class, 'bulk'])->name('forgepulse.api.actions.schema.bulk');
        Route::post('/actions/openapi', [ActionSchemaController::class, 'openapi'])->name('forgepulse.api.actions.openapi');
        Route::post('/actions/validate', [ActionSchemaController::class, 'validate'])->name('forgepulse.api.actions.validate');

        // Action schema management (CRUD)
        Route::get('/action-schemas', [ActionSchemaManagementController::class, 'index'])->name('forgepulse.api.action-schemas.index');
        Route::post('/action-schemas', [ActionSchemaManagementController::class, 'store'])->name('forgepulse.api.action-schemas.store');
        Route::get('/action-schemas/{actionSchema}', [ActionSchemaManagementController::class, 'show'])->name('forgepulse.api.action-schemas.show');
        Route::put('/action-schemas/{actionSchema}', [ActionSchemaManagementController::class, 'update'])->name('forgepulse.api.action-schemas.update');
        Route::delete('/action-schemas/{actionSchema}', [ActionSchemaManagementController::class, 'destroy'])->name('forgepulse.api.action-schemas.destroy');
        Route::post('/action-schemas/sync', [ActionSchemaManagementController::class, 'sync'])->name('forgepulse.api.action-schemas.sync');
        Route::post('/action-schemas/import', [ActionSchemaManagementController::class, 'import'])->name('forgepulse.api.action-schemas.import');
    });

