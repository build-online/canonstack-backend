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
            $table->string('name');
            $table->string('path', 500);
            $table->enum('type', ['file', 'folder']);
            $table->bigInteger('size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('parent_path', 500)->nullable();
            $table->text('file_ref')->nullable(); 
            $table->timestamps();
            
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
