<?php

namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /**
     * 3 отеля для демо.
     */
    public function run(): void
    {
        $cities = [
            'Москва', 'Санкт-Петербург', 'Сочи',
        ];

        $hotels = [];
        for ($i = 1; $i <= 100; $i++) {
            $hotels[] = [
                'name' => 'Отель ' . $i,
                'city' => $cities[rand(0, count($cities)-1)],
                'address' => 'ул. Демо, ' . $i,
            ];
        }

        foreach ($hotels as $h) {
            Hotel::firstOrCreate(
                ['name' => $h['name']],
                ['city' => $h['city'], 'address' => $h['address']]
            );
        }
    }
}
