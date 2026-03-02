<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Placement;
use App\Models\PlacementVariant;
use App\Services\ClickHouseService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoDataSeeder extends Seeder
{
    private const HOTELS_COUNT = 100;
    private const PLACEMENTS_PER_HOTEL = 20;
    private const VARIANTS_PER_PLACEMENT = 20;
    private const DAYS_COUNT = 365;

    /**
     * Демо-данные: отели, размещения, варианты (adults × children_ages), доступность и цены на 365 дней.
     * Перед заполнением очищаются таблицы MySQL: availability, prices_by_day, placements, hotels (placement_variants не трогаем для сохранения вариантов).
     */
    public function run(): void
    {
        $this->clearMysql();

        $this->call(HotelSeeder::class);

        $hotels = Hotel::orderBy('id')->limit(self::HOTELS_COUNT)->get();
        if ($hotels->isEmpty()) {
            return;
        }

        $variantTemplates = $this->variantTemplates();
        $dateFrom = Carbon::parse('2026-01-01');
        $now = $dateFrom->format('Y-m-d H:i:s');

        $allPlacementIds = [];

        foreach ($hotels as $hotel) {
            for ($p = 1; $p <= self::PLACEMENTS_PER_HOTEL; $p++) {
                $placement = Placement::firstOrCreate(
                    ['hotel_id' => $hotel->id, 'name' => "Размещение {$p}"],
                    []
                );
                $allPlacementIds[] = $placement->id;

                foreach ($variantTemplates as $v) {
                    PlacementVariant::updateOrCreate(
                        [
                            'placement_id' => $placement->id,
                            'adults' => $v['adults'],
                            'children_ages' => $v['children_ages'],
                        ],
                        []
                    );
                }
            }
        }

        try {
            $synced = ClickHouseService::fromConfig()->syncPlacementVariantsFromDb();
            $this->command?->info("Synced {$synced} placement_variants to ClickHouse.");
        } catch (\Throwable $e) {
            $this->command?->warn('ClickHouse sync-placement-variants skipped: ' . $e->getMessage());
            $this->command?->info('After seed run: php artisan clickhouse:sync-placement-variants');
        }

        $this->command?->info('Placements: ' . count($allPlacementIds) . ', filling availability and prices for ' . self::DAYS_COUNT . ' days...');

        $chunkSize = 5000;
        $availabilityChunk = [];
        $pricesChunk = [];

        foreach ($allPlacementIds as $placementId) {
            for ($d = 0; $d < self::DAYS_COUNT; $d++) {
                $date = $dateFrom->copy()->addDays($d)->format('Y-m-d');
                $available = (int) (random_int(1, 100) <= 80); // 80% доступно
                $price = 5000 + ($d % 30) * 100 + (rand(0, 20) * 50);

                $availabilityChunk[] = [
                    'placement_id' => $placementId,
                    'date' => $date,
                    'available' => $available,
                    'updated_at' => $now,
                ];
                $pricesChunk[] = [
                    'placement_id' => $placementId,
                    'date' => $date,
                    'price' => $price,
                    'currency' => 'RUB',
                    'updated_at' => $now,
                ];

                if (count($availabilityChunk) >= $chunkSize) {
                    DB::table('availability')->insertOrIgnore($availabilityChunk);
                    DB::table('prices_by_day')->insertOrIgnore($pricesChunk);
                    $availabilityChunk = [];
                    $pricesChunk = [];
                }
            }
        }

        if (! empty($availabilityChunk)) {
            DB::table('availability')->insertOrIgnore($availabilityChunk);
            DB::table('prices_by_day')->insertOrIgnore($pricesChunk);
        }

        $this->command?->info('Done. Для пересборки search_by_variant из финальных таблиц: php artisan clickhouse:refresh-search-table');
    }

    /**
     * Очистка таблиц MySQL, используемых как источник для CDC (Debezium).
     */
    private function clearMysql(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('availability')->truncate();
        DB::table('prices_by_day')->truncate();
      //  DB::table('placement_variants')->truncate();
        DB::table('placements')->truncate();
        DB::table('hotels')->truncate();

        Schema::enableForeignKeyConstraints();

        $this->command?->info('MySQL tables cleared.');
    }

    /**
     * Шаблоны вариантов поиска: 4 значения adults (1–4) × 5 значений children_ages (пусто, 5, 10, 5,10, 3,7) = 20 комбинаций.
     */
    private function variantTemplates(): array
    {
        $childrenOptions = ['', '5', '10', '5,10', '3,7'];
        $templates = [];
        for ($adults = 1; $adults <= 4; $adults++) {
            foreach ($childrenOptions as $childrenAges) {
                $templates[] = ['adults' => $adults, 'children_ages' => $childrenAges];
            }
        }
        return $templates;
    }
}
