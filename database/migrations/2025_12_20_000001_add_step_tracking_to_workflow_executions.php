<?php

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
        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->foreignId('current_step_id')
                ->nullable()
                ->after('workflow_id')
                ->comment('Currently executing step')
                ->constrained('workflow_steps')
                ->nullOnDelete();

            $table->json('completed_step_ids')
                ->nullable()
                ->after('output')
                ->comment('Array of completed step IDs for resume capability');

            $table->index('current_step_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->dropForeign(['current_step_id']);
            $table->dropIndex(['current_step_id']);
            $table->dropColumn(['current_step_id', 'completed_step_ids']);
        });
    }
};
