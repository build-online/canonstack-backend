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
        Schema::create('experiment_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('dataset_embedding_id')->constrained('dataset_embeddings')->onDelete('cascade');
            $table->string('variant'); // 'simple' or 'complex'
            $table->timestamps();
            $table->softDeletes();
            
            // Ensure one active conversation per user per dataset_embedding per variant
            $table->unique(['user_id', 'dataset_embedding_id', 'variant'], 'unique_user_embedding_variant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiment_conversations');
    }
};
