<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing data to uppercase
        DB::statement("UPDATE dataset_embeddings SET status = UPPER(status)");
        
        // Then, update the enum definition to only allow uppercase values
        DB::statement("ALTER TABLE dataset_embeddings MODIFY COLUMN status ENUM('PENDING', 'PROCESSING', 'COMPLETED', 'FAILED') DEFAULT 'PENDING'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to lowercase values
        DB::statement("UPDATE dataset_embeddings SET status = LOWER(status)");
        
        // Revert the enum definition to lowercase values
        DB::statement("ALTER TABLE dataset_embeddings MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending'");
    }
};