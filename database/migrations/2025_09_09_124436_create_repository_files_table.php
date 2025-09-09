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
        Schema::create('repository_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('repository_id')->constrained('repositories')->onDelete('cascade');
            $table->string('name'); // file/folder name
            $table->string('path', 500); // full path within ZIP (reduced for index compatibility)
            $table->enum('type', ['file', 'folder']); // file or folder
            $table->bigInteger('size')->nullable(); // file size in bytes (null for folders)
            $table->string('mime_type')->nullable(); // MIME type for files (null for folders)
            $table->string('parent_path', 500)->nullable(); // parent folder path (reduced for index compatibility)
            $table->text('file_ref')->nullable(); // Wasabi path for individual file (null for folders)
            $table->timestamps();
            
            // Indexes for performance - simplified
            $table->index(['repository_id', 'type']);
            $table->index('repository_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repository_files');
    }
};
