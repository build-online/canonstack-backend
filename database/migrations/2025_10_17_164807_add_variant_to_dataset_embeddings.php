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
            // Add nullable variant column after dataset_id
            $table->enum('variant', ['simple', 'complex'])->nullable()->after('dataset_id');
            
            // Drop the old unique constraint on dataset_id (if exists)
            // Note: Laravel's hasOne relationship only allowed one embedding per dataset before
            
            // Add composite unique index for dataset_id + variant combination
            $table->unique(['dataset_id', 'variant'], 'dataset_embeddings_dataset_variant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dataset_embeddings', function (Blueprint $table) {
            // Drop the composite unique index
            $table->dropUnique('dataset_embeddings_dataset_variant_unique');
            
            // Drop the variant column
            $table->dropColumn('variant');
        });
    }
};
