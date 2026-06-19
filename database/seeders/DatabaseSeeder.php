<?php

namespace Database\Seeders;

use App\Models\BoatSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() === 0) {
            User::factory()->create([
                'name' => 'Jessica Smith',
                'email' => 'jess@mintopia.net',
            ]);
        }

        BoatSetting::setValue('boat_name', config('scarlet.name'));
        BoatSetting::setValue('mmsi', config('scarlet.mmsi'));
        BoatSetting::setValue('passage_from', '');
        BoatSetting::setValue('passage_to', '');
        BoatSetting::setValue('port_name', '');

        $this->call(CanonicalCatalogSeeder::class);
    }
}
