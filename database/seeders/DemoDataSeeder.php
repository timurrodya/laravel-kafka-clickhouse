<?php

namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /**
     * Демо-данные для проверки пайплайна MySQL → Debezium → Kafka → ClickHouse.
     */
    public function run(): void
    {
        $this->call(HotelSeeder::class);

        $hotel = Hotel::where('name', 'Отель Центральный')->first();
        if (!$hotel) {
            return;
        }

        $now = now();
        $dates = [
            '2026-02-28' => ['available' => 1, 'price' => 51500.00],
            '2026-03-01' => ['available' => 1, 'price' => 61200.00],
            '2026-03-02' => ['available' => 0, 'price' => 51800.00],
            '2026-03-03' => ['available' => 1, 'price' => 51900.00],
            '2026-03-04' => ['available' => 1, 'price' => 61100.00],
            '2026-03-05' => ['available' => 1, 'price' => 61000.00],
            '2026-03-06' => ['available' => 1, 'price' => 61300.00],
            '2026-03-07' => ['available' => 1, 'price' => 61400.00],
            '2026-03-08' => ['available' => 1, 'price' => 61500.00],
        ];

        foreach ($dates as $date => $row) {
            DB::table('availability')->updateOrInsert(
                [
                    'hotel_id' => $hotel->id,
                    'date' => $date,
                ],
                [
                    'available' => $row['available'],
                    'updated_at' => $now,
                ]
            );
            DB::table('prices_by_day')->updateOrInsert(
                [
                    'hotel_id' => $hotel->id,
                    'date' => $date,
                ],
                [
                    'price' => $row['price'],
                    'currency' => 'RUB',
                    'updated_at' => $now,
                ]
            );
        }
    }
}
