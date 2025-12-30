<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->index(); // event, schedule, webhook, model, manual
            $table->boolean('is_active')->default(true)->index();
            $table->json('configuration'); // Type-specific settings
            $table->json('conditions')->nullable(); // Additional trigger conditions
            $table->json('context_mapping')->nullable(); // How to build execution context
            $table->integer('priority')->default(0); // For ordering multiple triggers
            $table->integer('max_executions')->nullable(); // Rate limiting
            $table->string('max_executions_period')->nullable(); // per_minute, per_hour, per_day
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedInteger('trigger_count')->default(0);

            // Multi-tenancy support (inherits from workflow but cached for performance)
            $table->unsignedBigInteger('team_id')->nullable()->index();

            $table->timestamps();

            // Performance indexes
            $table->index(['workflow_id', 'is_active']);
            $table->index(['type', 'is_active']);
            $table->index(['team_id', 'type', 'is_active']);
            $table->unique(['workflow_id', 'type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_triggers');
    }
};
