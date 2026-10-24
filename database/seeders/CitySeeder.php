<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Surakarta', 'Sukoharjo', 'Karanganyar'] as $name) {
            City::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
