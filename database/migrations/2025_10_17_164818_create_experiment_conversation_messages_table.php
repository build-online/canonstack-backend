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
        Schema::create('experiment_conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('experiment_conversation_id');
            $table->foreign('experiment_conversation_id', 'exp_conv_msg_conv_id_foreign')
                ->references('id')
                ->on('experiment_conversations')
                ->onDelete('cascade');
            $table->enum('role', ['user', 'assistant']);
            $table->longText('original_content')->nullable();
            $table->text('summarized_content');
            $table->json('rag_metadata')->nullable();
            $table->integer('token_count')->default(0);
            $table->timestamps();
            
            $table->index(['experiment_conversation_id', 'created_at'], 'exp_conv_msg_conv_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiment_conversation_messages');
    }
};
