<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() === 0) {
            User::factory()->owner()->create([
                'name' => 'Jessica Smith',
                'email' => 'jess@mintopia.net',
            ]);
        }
    }
}
