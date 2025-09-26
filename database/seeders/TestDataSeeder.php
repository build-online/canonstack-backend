<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds for testing the API.
     */
    public function run(): void
    {
        $this->seedCategories();
        $this->seedTags();
        $this->seedUsers();

        $this->command->info('Test data seeded successfully!');
        $this->command->info('Categories: ' . Category::count());
        $this->command->info('Tags: ' . Tag::count());
        $this->command->info('Users: ' . User::count());
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Biblical Resources'],
            ['name' => 'Sermon Tools'],
            ['name' => 'Prayer & Worship'],
            ['name' => 'Youth Ministry'],
            ['name' => 'Pastoral Care'],
            ['name' => 'Church Administration'],
            ['name' => 'Discipleship'],
            ['name' => 'Evangelism']
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']], $category);
        }
    }

    private function seedTags(): void
    {
        $tags = [
            ['name' => 'Scripture'],
            ['name' => 'Bible Study'],
            ['name' => 'Sermon'],
            ['name' => 'Prayer'],
            ['name' => 'Worship'],
            ['name' => 'Discipleship'],
            ['name' => 'Evangelism'],
            ['name' => 'Church'],
            ['name' => 'Pastor'],
            ['name' => 'Youth'],
            ['name' => 'Small Groups'],
            ['name' => 'Devotional'],
            ['name' => 'Theology'],
            ['name' => 'Ministry'],
            ['name' => 'Sunday School'],
            ['name' => 'Christianity'],
            ['name' => 'Gospel'],
            ['name' => 'Faith'],
            ['name' => 'Baptism'],
            ['name' => 'Communion'],
            
            ['name' => 'Machine Learning'],
            ['name' => 'Deep Learning'],
            ['name' => 'Neural Networks'],
            ['name' => 'NLP'],
            ['name' => 'Computer Vision'],
            ['name' => 'TensorFlow'],
            ['name' => 'PyTorch'],
            ['name' => 'OpenAI'],
            ['name' => 'GPT'],
            ['name' => 'Transformer'],
            ['name' => 'Classification'],
            ['name' => 'Regression'],
            ['name' => 'Clustering'],
            ['name' => 'Reinforcement Learning'],
            ['name' => 'Data Science'],
            ['name' => 'Analytics'],
            ['name' => 'AI Ethics'],
            ['name' => 'Automation'],
            ['name' => 'Chatbot'],
            ['name' => 'Speech Processing'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['name' => $tag['name']], $tag);
        }
    }

    private function seedUsers(): void
    {
        $regularUsers = [
            [
                'name' => 'Pastor John Williams',
                'username' => 'pastorjohn',
                'email' => 'pastor.john@canonstack.com',
                'password' => bcrypt('password123'),
                'role' => 'REGULAR',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dr. Sarah Chen',
                'username' => 'drsarahchen',
                'email' => 'dr.sarah@canonstack.com',
                'password' => bcrypt('password123'),
                'role' => 'REGULAR',
                'email_verified_at' => now(),
            ],
        ];

        $approverUsers = [
            [
                'name' => 'Admin Bishop',
                'username' => 'adminbishop',
                'email' => 'bishop.admin@canonstack.org',
                'password' => bcrypt('password123'),
                'role' => 'APPROVER',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Admin Admin',
                'username' => 'adminadmin',
                'email' => 'admin.admin@canonstack.org',
                'password' => bcrypt('password123'),
                'role' => 'APPROVER',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($regularUsers as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        foreach ($approverUsers as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
