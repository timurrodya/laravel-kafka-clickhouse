<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Привести availability и prices_by_day к схеме с placement_id,
     * если таблицы были созданы со старыми колонками (hotel_id).
     */
    public function up(): void
    {
        $this->convertAvailability();
        $this->convertPricesByDay();
    }

    private function convertAvailability(): void
    {
        if (! Schema::hasTable('availability') || ! Schema::hasColumn('availability', 'hotel_id')) {
            return;
        }

        Schema::table('availability', function (Blueprint $table) {
            $table->unsignedBigInteger('placement_id')->nullable()->after('id');
        });

        foreach (DB::table('hotels')->pluck('id') as $hotelId) {
            $placement = DB::table('placements')->where('hotel_id', $hotelId)->first();
            if (! $placement) {
                $placementId = DB::table('placements')->insertGetId([
                    'hotel_id' => $hotelId,
                    'name' => 'Размещение по умолчанию',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $placementId = $placement->id;
            }
            DB::table('availability')->where('hotel_id', $hotelId)->update(['placement_id' => $placementId]);
        }

        $this->dropUniqueIndex('availability', ['hotel_id', 'date']);
        Schema::table('availability', function (Blueprint $table) {
            $table->dropColumn('hotel_id');
        });
        DB::statement('ALTER TABLE availability MODIFY placement_id BIGINT UNSIGNED NOT NULL');
        Schema::table('availability', function (Blueprint $table) {
            $table->unique(['placement_id', 'date']);
            $table->foreign('placement_id')->references('id')->on('placements');
        });
    }

    private function convertPricesByDay(): void
    {
        if (! Schema::hasTable('prices_by_day') || ! Schema::hasColumn('prices_by_day', 'hotel_id')) {
            return;
        }

        Schema::table('prices_by_day', function (Blueprint $table) {
            $table->unsignedBigInteger('placement_id')->nullable()->after('id');
        });

        foreach (DB::table('hotels')->pluck('id') as $hotelId) {
            $placement = DB::table('placements')->where('hotel_id', $hotelId)->first();
            if (! $placement) {
                $placementId = DB::table('placements')->insertGetId([
                    'hotel_id' => $hotelId,
                    'name' => 'Размещение по умолчанию',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $placementId = $placement->id;
            }
            DB::table('prices_by_day')->where('hotel_id', $hotelId)->update(['placement_id' => $placementId]);
        }

        $this->dropUniqueIndex('prices_by_day', ['hotel_id', 'date']);
        Schema::table('prices_by_day', function (Blueprint $table) {
            $table->dropColumn('hotel_id');
        });
        DB::statement('ALTER TABLE prices_by_day MODIFY placement_id BIGINT UNSIGNED NOT NULL');
        Schema::table('prices_by_day', function (Blueprint $table) {
            $table->unique(['placement_id', 'date']);
            $table->foreign('placement_id')->references('id')->on('placements');
        });
    }

    public function down(): void
    {
        // Откат не восстанавливает hotel_id и старые данные — только для dev.
        if (Schema::hasColumn('availability', 'placement_id')) {
            Schema::table('availability', function (Blueprint $table) {
                $table->dropForeign(['placement_id']);
                $table->dropUnique(['placement_id', 'date']);
            });
        }
        if (Schema::hasColumn('prices_by_day', 'placement_id')) {
            Schema::table('prices_by_day', function (Blueprint $table) {
                $table->dropForeign(['placement_id']);
                $table->dropUnique(['placement_id', 'date']);
            });
        }
    }

    private function dropUniqueIndex(string $tableName, array $columns): void
    {
        $db = DB::connection()->getDatabaseName();
        $cols = implode(',', $columns);
        $index = DB::selectOne(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME != 'PRIMARY'
             GROUP BY INDEX_NAME 
             HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) = ?",
            [$db, $tableName, $cols]
        );
        if ($index) {
            DB::statement("ALTER TABLE {$tableName} DROP INDEX `{$index->INDEX_NAME}`");
        }
    }
};
