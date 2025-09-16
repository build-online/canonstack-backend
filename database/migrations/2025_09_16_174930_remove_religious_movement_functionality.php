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
        // Remove religious_movement_id column from repositories table
        if (Schema::hasColumn('repositories', 'religious_movement_id')) {
            Schema::table('repositories', function (Blueprint $table) {
                $table->dropForeign(['religious_movement_id']);
                $table->dropColumn('religious_movement_id');
            });
        }

        // Remove religious_movement_id column from users table
        if (Schema::hasColumn('users', 'religious_movement_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['religious_movement_id']);
                $table->dropColumn('religious_movement_id');
            });
        }

        // Drop religious_movements table
        Schema::dropIfExists('religious_movements');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate religious_movements table
        Schema::create('religious_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('main_religion');
            $table->string('branch');
            $table->timestamps();
        });

        // Add religious_movement_id back to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('religious_movement_id')->nullable()->constrained('religious_movements')->onDelete('cascade');
        });

        // Add religious_movement_id back to repositories table
        Schema::table('repositories', function (Blueprint $table) {
            $table->foreignId('religious_movement_id')->nullable()->constrained('religious_movements')->onDelete('cascade');
        });
    }
};
