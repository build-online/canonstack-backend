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
        Schema::create('dataset_embeddings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dataset_id')->constrained()->onDelete('cascade');
            $table->string('qdrant_collection_name')->index();
            $table->integer('total_chunks')->default(0);
            $table->integer('total_points')->default(0);
            $table->string('embedding_model')->default('text-embedding-3-small');
            $table->integer('chunk_size')->default(1000);
            $table->integer('chunk_overlap')->default(200);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('processing_stats')->nullable(); // Store stats like processing time, file counts, etc.
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['status']);
            $table->index(['qdrant_collection_name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_embeddings');
    }
};