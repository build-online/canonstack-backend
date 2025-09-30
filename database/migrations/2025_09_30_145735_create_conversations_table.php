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
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('dataset_embedding_id')->constrained('dataset_embeddings')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
            
            // Ensure one active conversation per user per dataset_embedding
            $table->unique(['user_id', 'dataset_embedding_id'], 'unique_user_dataset_embedding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};