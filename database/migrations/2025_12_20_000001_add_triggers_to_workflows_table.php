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
        Schema::table('workflows', function (Blueprint $table) {
            // Trigger configuration for the workflow
            $table->json('trigger_config')->nullable()->after('configuration');
            $table->string('trigger_type')->nullable()->after('trigger_config')->index();
            $table->boolean('auto_trigger_enabled')->default(false)->after('trigger_type')->index();
            
            // Index for finding workflows with specific trigger types
            $table->index(['trigger_type', 'auto_trigger_enabled', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropIndex(['trigger_type', 'auto_trigger_enabled', 'status']);
            $table->dropColumn(['trigger_config', 'trigger_type', 'auto_trigger_enabled']);
        });
    }
};
