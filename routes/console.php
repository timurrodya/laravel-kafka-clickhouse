<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Services\ClickHouseService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('clickhouse:sync-placement-variants', function () {
    @ini_set('memory_limit', '512M');
    $service = ClickHouseService::fromConfig();
    $count = $service->syncPlacementVariantsFromDb();
    $this->info("Synced {$count} placement_variants rows to ClickHouse.");
})->purpose('Sync placement_variants to ClickHouse');

Artisan::command('clickhouse:refresh-search-table', function () {
    @ini_set('memory_limit', '512M');
    $service = ClickHouseService::fromConfig();
    $service->refreshSearchByVariant();
    $this->info('Refreshed search_by_variant table from final tables.');
})->purpose('Full rebuild of search_by_variant from placement_variants, availability_final, prices_by_day_final');

Artisan::command('clickhouse:fix-mvs', function () {
    @ini_set('memory_limit', '512M');
    $service = ClickHouseService::fromConfig();

    // Применить SQL: пересоздать только MV (Kafka-таблицы и оффсеты не меняются).
    $this->info('Applying MV fixes to running ClickHouse...');
    $sql = file_get_contents(base_path('docker/clickhouse/init/10_fix_mvs.sql'));
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function (string $s): bool {
            foreach (explode("\n", $s) as $line) {
                $line = trim($line);
                if ($line !== '' && !str_starts_with($line, '--')) {
                    return true;
                }
            }
            return false;
        }
    );
    foreach ($statements as $stmt) {
        $service->execute($stmt);
        foreach (explode("\n", $stmt) as $line) {
            if (trim($line) !== '' && !str_starts_with(trim($line), '--')) {
                $this->line('  OK: ' . trim($line));
                break;
            }
        }
    }
    $this->info('MVs recreated.');

    // DETACH/ATTACH Kafka-таблиц, чтобы консьюмеры переподключились к новым MV; оффсеты в Kafka сохраняются.
    $this->info('Restarting Kafka consumers...');
    foreach (['kafka_availability', 'kafka_availability_for_search', 'kafka_prices_by_day', 'kafka_prices_by_day_for_search', 'kafka_placement_variants'] as $t) {
        $service->execute("DETACH TABLE analytics.{$t}");
        $service->execute("ATTACH TABLE analytics.{$t}");
        $this->line("  Restarted: analytics.{$t}");
    }

    $this->info('Syncing placement_variants from MySQL...');
    $count = $service->syncPlacementVariantsFromDb();
    $this->info("  Synced {$count} rows.");

    $this->info('Refreshing search_by_variant from final tables...');
    $service->refreshSearchByVariant();
    $this->info('  Done.');

    $counts = $service->getTableCounts();
    $this->table(
        ['Table', 'Rows'],
        array_map(fn($k, $v) => [$k, $v ?? 'error'], array_keys($counts), array_values($counts))
    );
})->purpose('Recreate MVs from 10_fix_mvs.sql, restart Kafka consumers, sync placement_variants, refresh search_by_variant');

Artisan::command('clickhouse:diagnose', function () {
    $service = ClickHouseService::fromConfig();

    $this->info('--- placement_variants ---');
    $rows = $service->select('SELECT adults, count() AS cnt FROM analytics.placement_variants FINAL WHERE adults > 0 GROUP BY adults ORDER BY adults LIMIT 10');
    if (empty($rows)) {
        $this->error('  EMPTY or no rows with adults > 0 — JOIN in MVs will produce no rows for search_by_variant');
    } else {
        $this->table(['adults', 'count'], $rows);
    }

    $this->info('--- availability_final (placement_id=1, 3 days) ---');
    $rows = $service->select("SELECT date, available, updated_at FROM analytics.availability_final FINAL WHERE placement_id = 1 ORDER BY date LIMIT 3");
    $this->table(['date', 'available', 'updated_at'], $rows ?: [['—', '—', '—']]);

    $this->info('--- search_by_variant (placement_id=1, adults > 0) ---');
    $rows = $service->select("SELECT adults, available, count() AS cnt FROM analytics.search_by_variant FINAL WHERE placement_id = 1 AND adults > 0 GROUP BY adults, available ORDER BY adults LIMIT 10");
    $this->table(['adults', 'available', 'count'], $rows ?: [['—', '—', '0']]);

    $this->info('--- ClickHouse query_log errors (last 10) ---');
    $rows = $service->select("SELECT event_time, left(query, 120) AS query, exception FROM system.query_log WHERE exception != '' AND (query LIKE '%search_by_variant%' OR query LIKE '%kafka_availability%') ORDER BY event_time DESC LIMIT 10");
    if (empty($rows)) {
        $this->line('  No errors found in query_log.');
    } else {
        foreach ($rows as $r) {
            $this->error("  [{$r['event_time']}] {$r['query']}");
            $this->line("    Exception: {$r['exception']}");
        }
    }

    $this->info('--- Kafka consumers ---');
    try {
        $rows = $service->select("SELECT * FROM system.kafka_consumers WHERE database = 'analytics'");
        if (empty($rows)) {
            $this->warn('  No active Kafka consumers found for database analytics.');
        } else {
            $headers = array_keys($rows[0]);
            $this->table($headers, array_map('array_values', $rows));
        }
    } catch (\Throwable $e) {
        $this->warn('  Could not query system.kafka_consumers: ' . $e->getMessage());
    }

    $this->info('--- Materialized Views in analytics ---');
    $rows = $service->select("SELECT name, engine FROM system.tables WHERE database = 'analytics' AND engine = 'MaterializedView' ORDER BY name");
    $this->table(['name', 'engine'], $rows ?: [['—', '—']]);
})->purpose('Show placement_variants stats, sample data from availability_final/search_by_variant, query_log errors, Kafka consumers, MV list');

Artisan::command('db:truncate-cdc-tables', function () {
    $this->warn('Truncating availability and prices_by_day.');
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    DB::table('availability')->truncate();
    DB::table('prices_by_day')->truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    $this->info('Done. Restart or re-create the Debezium connector to trigger a fresh snapshot.');
})->purpose('Truncate availability and prices_by_day (restart Debezium connector to trigger fresh snapshot if needed)');
