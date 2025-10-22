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
        Schema::table('dataset_embeddings', function (Blueprint $table) {
            $table->text('structure_description')->nullable()->after('processing_stats');
            $table->text('system_prompt')->nullable()->after('structure_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dataset_embeddings', function (Blueprint $table) {
            $table->dropColumn(['structure_description', 'system_prompt']);
        });
    }
};
