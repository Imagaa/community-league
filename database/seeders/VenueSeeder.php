<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    public function run(): void
    {
        $venues = [
            ['Surakarta', 'Nusukan Sport Center', 'Jl. Kapten Piere Tendean, Nusukan, Banjarsari, Surakarta'],
            ['Surakarta', 'Manahan Mini Soccer', 'Jl. Adi Sucipto, Manahan, Banjarsari, Surakarta'],
            ['Surakarta', 'Gelora Mojosongo Arena', 'Jl. Brigjen Katamso, Mojosongo, Jebres, Surakarta'],
            ['Sukoharjo', 'Solo Baru Soccer Field', 'Jl. Ir. Soekarno, Solo Baru, Grogol, Sukoharjo'],
            ['Karanganyar', 'Palur Sport Arena', 'Jl. Raya Palur, Ngringo, Jaten, Karanganyar'],
        ];

        foreach ($venues as [$cityName, $name, $address]) {
            $city = City::where('name', $cityName)->first();

            if (! $city) {
                continue;
            }

            Venue::firstOrCreate(
                ['city_id' => $city->id, 'name' => $name],
                ['address' => $address, 'is_active' => true],
            );
        }
    }
}
