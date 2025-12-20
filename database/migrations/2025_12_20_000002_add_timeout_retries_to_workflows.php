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
        Schema::table('workflows', function (Blueprint $table) {
            $table->integer('timeout')
                ->nullable()
                ->after('status')
                ->comment('Default timeout for all steps in seconds (overrides global config)');

            $table->integer('max_retries')
                ->nullable()
                ->after('timeout')
                ->comment('Default max retry attempts for failed steps (overrides global config)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn(['timeout', 'max_retries']);
        });
    }
};
