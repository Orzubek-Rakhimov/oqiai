<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $parent = User::factory()->create([
            'name' => 'Test User',
            'phone' => '+998901234567',
        ]);

        Child::factory()->for($parent, 'parent')->create(['name' => 'Ali', 'grade' => 1, 'avatar' => 'fox']);
        Child::factory()->for($parent, 'parent')->create(['name' => 'Madina', 'grade' => 3, 'avatar' => 'owl']);
    }
}
