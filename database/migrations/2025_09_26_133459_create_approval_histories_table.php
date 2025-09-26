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
        Schema::create('approval_histories', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('repository_id')->constrained('repositories')->onDelete('cascade');
            $table->foreignId('approver_id')->constrained('users')->onDelete('cascade');
            $table->enum('action', ['APPROVE', 'REJECT']);
            $table->enum('previous_status', ['PENDING_REVIEW', 'ACCEPTED', 'DECLINED']);
            $table->enum('new_status', ['ACCEPTED', 'DECLINED']);
            $table->text('comment')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['repository_id', 'created_at']);
            $table->index(['approver_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_histories');
    }
};
