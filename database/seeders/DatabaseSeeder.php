<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with a demo family and their events.
     */
    public function run(): void
    {
        $family = collect([
            ['name' => 'Test User', 'email' => 'test@example.com', 'color' => '#2563eb'],
            ['name' => 'Sam', 'email' => 'sam@example.com', 'color' => '#db2777'],
            ['name' => 'Jordan', 'email' => 'jordan@example.com', 'color' => '#16a34a'],
            ['name' => 'Riley', 'email' => 'riley@example.com', 'color' => '#ea580c'],
        ])->map(fn (array $member) => User::factory()->create($member));

        Event::factory()
            ->count(40)
            ->recycle($family)
            ->create();
    }
}
