<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('action_schemas', function (Blueprint $table) {
            $table->id();

            // Action identification
            $table->string('action_class')->comment('Fully qualified class name (e.g., App\Actions\ProcessOrder)');
            $table->string('action_type')->default('internal')->comment('internal, external, webhook, api');
            $table->string('method')->nullable()->comment('Method name if applicable');

            // OpenAPI schema (stored as JSON)
            $table->json('schema')->comment('Complete OpenAPI 3.0 operation schema');

            // Metadata
            $table->string('name')->comment('Human-readable action name');
            $table->text('description')->nullable();
            $table->string('category')->default('general')->comment('Action category for grouping');
            $table->json('tags')->nullable()->comment('Tags for filtering/searching');

            // Status and visibility
            $table->boolean('is_active')->default(true)->comment('Whether action is available for use');
            $table->boolean('is_external')->default(false)->comment('Is this an external service/API');

            // Source tracking
            $table->string('source')->default('manual')->comment('manual, code, imported, generated');
            $table->integer('version')->default(1)->comment('Schema version number');

            // Configuration
            $table->json('config')->nullable()->comment('Additional configuration (timeout, retries, etc.)');

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['action_class', 'method']);
            $table->index('action_type');
            $table->index('category');
            $table->index('is_active');
            $table->index('is_external');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_schemas');
    }
};
