<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Tag;
use App\Models\ReligiousMovement;
use App\Models\User;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds for testing the API.
     */
    public function run(): void
    {
        // Create test categories
        $categories = [
            ['name' => '3D Models'],
            ['name' => 'AI/ML Models'],
            ['name' => 'Computer Vision'],
            ['name' => 'Natural Language Processing'],
            ['name' => 'Audio Processing'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate($category);
        }

        // Create test tags
        $tags = [
            ['name' => 'Pastor'],
            ['name' => 'Bible'],
            ['name' => 'Church'],
            ['name' => 'Sermon'],
            ['name' => 'Devotional'],
            ['name' => 'Bible Study'],
            ['name' => 'Prayer'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate($tag);
        }

        // Create test religious movements
        $movements = [
            ['main_religion' => 'Christianity', 'branch' => 'Catholic'],
            ['main_religion' => 'Christianity', 'branch' => 'Protestant'],
            ['main_religion' => 'Christianity', 'branch' => 'Orthodox'],
            ['main_religion' => 'Christianity', 'branch' => 'Anglican'],
        ];

        foreach ($movements as $movement) {
            ReligiousMovement::firstOrCreate($movement);
        }

        $religiousMovement = ReligiousMovement::first();

        // Create a test user if it doesn't exist
        User::firstOrCreate(
            ['email' => 'user@faithtech.com'],
            [
                'name' => 'Test User',
                'username' => 'testuser',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Create a test approver if it doesn't exist
        User::firstOrCreate(
            ['email' => 'approver@faithtech.com'],
            [
                'name' => 'Test Approver',
                'username' => 'testapprover',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
                'role' => 'APPROVER',
                'religious_movement_id' => $religiousMovement->id,
            ]
        );

        $this->command->info('Test data seeded successfully!');
        $this->command->info('Categories: ' . Category::count());
        $this->command->info('Tags: ' . Tag::count());
        $this->command->info('Religious Movements: ' . ReligiousMovement::count());
        $this->command->info('Users: ' . User::count());
        $this->command->info('');
        $this->command->info('Test user credentials:');
        $this->command->info('Email: user@faithtech.com');
        $this->command->info('Password: password123');
        $this->command->info('Test approver credentials:');
        $this->command->info('Email: approve@faithtech.com');
        $this->command->info('Password: password123');
    }
}
