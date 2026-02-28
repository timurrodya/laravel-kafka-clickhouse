<?php

namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    public function run(): void
    {
        $hotels = [
            ['name' => 'Отель Центральный', 'city' => 'Москва', 'address' => 'ул. Тверская, 1'],
            ['name' => 'Гранд Отель', 'city' => 'Санкт-Петербург', 'address' => 'Невский пр., 10'],
            ['name' => 'Морской Бриз', 'city' => 'Сочи', 'address' => 'ул. Курортная, 5'],
        ];

        foreach ($hotels as $h) {
            Hotel::firstOrCreate(
                ['name' => $h['name']],
                ['city' => $h['city'], 'address' => $h['address']]
            );
        }
    }
}
