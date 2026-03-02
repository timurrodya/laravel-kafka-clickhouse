<?php

namespace App\Services;

use App\Models\PlacementVariant;
use Illuminate\Support\Facades\Http;

class ClickHouseService
{
    public function __construct(
        protected string $host,
        protected string $port,
        protected string $database,
        protected string $username = 'default',
        protected string $password = '',
        protected float $timeout = 10,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('clickhouse');
        return new self(
            host: $config['host'],
            port: $config['port'],
            database: $config['database'],
            username: $config['username'],
            password: $config['password'],
            timeout: $config['timeout'],
        );
    }

    /**
     * Выполнить DDL или DML без возврата данных (DROP, CREATE, INSERT, TRUNCATE).
     */
    public function execute(string $sql): void
    {
        $response = Http::timeout(max(30, $this->timeout))
            ->withBasicAuth($this->username, $this->password)
            ->withBody($sql, 'text/plain; charset=utf-8')
            ->post($this->baseUrl());

        if (!$response->successful()) {
            throw new \RuntimeException(
                'ClickHouse execute error: ' . $response->body() . ' (query: ' . substr($sql, 0, 200) . ')'
            );
        }
    }

    /**
     * Выполнить SELECT и вернуть массив строк.
     */
    public function select(string $query): array
    {
        $url = $this->baseUrl();
        $response = Http::timeout($this->timeout)
            ->withBasicAuth($this->username, $this->password)
            ->get($url, ['query' => $query . ' FORMAT TabSeparatedWithNames']);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'ClickHouse error: ' . $response->body() . ' (query: ' . substr($query, 0, 200) . '...)'
            );
        }

        $body = trim($response->body());
        if ($body === '') {
            return [];
        }

        $lines = explode("\n", $body);
        $header = str_getcsv(array_shift($lines), "\t");
        $result = [];
        $headerCount = count($header);
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line, "\t");
            // Выравниваем длину строки под заголовок (нет цен — меньше колонок в ответе и т.п.)
            if (count($row) !== $headerCount) {
                $row = array_pad(array_slice($row, 0, $headerCount), $headerCount, '');
            }
            $result[] = array_combine($header, $row);
        }
        return $result;
    }

    /**
     * Доступность и цены по размещению за период (финальные таблицы, ReplacingMergeTree).
     * GROUP BY + argMax убирает дубли (одна дата — одна строка).
     */
    public function getAvailabilityAndPrices(int $placementId, string $dateFrom, string $dateTo): array
    {
        $db = $this->database;
        $query = "
            SELECT
                a.date AS date,
                any(a.available) AS available,
                argMax(p.price, p.price) AS price,
                argMax(p.currency, p.price) AS currency
            FROM {$db}.availability_final AS a
            FINAL
            LEFT JOIN {$db}.prices_by_day_final AS p
            FINAL
                ON a.placement_id = p.placement_id AND a.date = p.date
            WHERE a.placement_id = {placementId:UInt64}
              AND a.date >= '{dateFrom:Date}'
              AND a.date <= '{dateTo:Date}'
            GROUP BY a.placement_id, a.date
            ORDER BY a.date
        ";

        $query = str_replace(
            ['{placementId:UInt64}', '{dateFrom:Date}', '{dateTo:Date}'],
            [(string) $placementId, $dateFrom, $dateTo],
            $query
        );

        return $this->select($query);
    }

    /**
     * Поиск по размещению и гостям из таблицы search_by_variant (без JOIN).
     *
     * @param  array<int>  $childrenAges  Возрасты детей, отсортированные (например [5, 10])
     */
    public function searchByPlacementAndGuests(
        int $placementId,
        string $dateFrom,
        string $dateTo,
        int $adults,
        array $childrenAges = []
    ): array {
        $childrenAgesStr = PlacementVariant::normalizeChildrenAges($childrenAges);
        $db = $this->database;
        $query = "
            SELECT
                date,
                available,
                price,
                currency
            FROM {$db}.search_by_variant
            FINAL
            WHERE placement_id = {placementId:UInt64}
              AND date >= '{dateFrom:Date}'
              AND date <= '{dateTo:Date}'
              AND adults = {adults:UInt8}
              AND children_ages = '{childrenAges:String}'
            ORDER BY date
        ";

        $query = str_replace(
            [
                '{placementId:UInt64}',
                '{adults:UInt8}',
                '{childrenAges:String}',
                '{dateFrom:Date}',
                '{dateTo:Date}',
            ],
            [
                (string) $placementId,
                (string) $adults,
                addslashes($childrenAgesStr),
                $dateFrom,
                $dateTo,
            ],
            $query
        );

        return $this->select($query);
    }

    /**
     * Поиск по всем размещениям из таблицы search_by_variant (без JOIN).
     * Возвращает placement_id, date, available, price, currency для каждого размещения с подходящим вариантом.
     *
     * @param  array<int>  $childrenAges
     */
    public function searchByGuestsOnly(
        string $dateFrom,
        string $dateTo,
        int $adults,
        array $childrenAges = []
    ): array {
        $childrenAgesStr = PlacementVariant::normalizeChildrenAges($childrenAges);
        $db = $this->database;
        $where = "adults = " . (int) $adults
            . " AND children_ages = '" . addslashes($childrenAgesStr) . "'"
            . " AND date >= '" . addslashes($dateFrom) . "'"
            . " AND date <= '" . addslashes($dateTo) . "'";
        $query = "SELECT placement_id, date, available, price, currency FROM {$db}.search_by_variant FINAL WHERE {$where} ORDER BY placement_id, date";
        return $this->select($query);
    }

    /**
     * Поиск по размещениям с агрегацией в ClickHouse: сумма цен за период и доступность (min(available) — доступно только если все дни в периоде доступны).
     * Один ряд на размещение — без разбивки по датам.
     *
     * @param  array<int>  $childrenAges
     * @return array{placement_id: string, total_price: string, available: string, currency: string}[]
     */
    public function searchByGuestsOnlyAggregated(
        string $dateFrom,
        string $dateTo,
        int $adults,
        array $childrenAges = []
    ): array {
        $childrenAgesStr = PlacementVariant::normalizeChildrenAges($childrenAges);
        $db = $this->database;
        $query = "SELECT placement_id, sum(price) AS total_price, min(available) AS available, any(currency) AS currency "
            . "FROM {$db}.search_by_variant FINAL WHERE adults = " . (int) $adults
            . " AND children_ages = '" . addslashes($childrenAgesStr) . "'"
            . " AND date >= '" . addslashes($dateFrom) . "' AND date <= '" . addslashes($dateTo) . "' "
            . "GROUP BY placement_id ORDER BY placement_id";
        return $this->select($query);
    }

    /**
     * Полная пересборка search_by_variant: TRUNCATE и INSERT из placement_variants, availability_final, prices_by_day_final (FINAL).
     * Применяется для первичной заливки или восстановления таблицы; в рабочем режиме данные подтягиваются через Kafka MV.
     */
    public function refreshSearchByVariant(): void
    {
        $db = $this->database;
        $url = $this->baseUrl();

        $truncate = "TRUNCATE TABLE {$db}.search_by_variant";
        $r1 = Http::timeout($this->timeout)
            ->withBasicAuth($this->username, $this->password)
            ->withBody($truncate, 'text/plain; charset=utf-8')
            ->post($url);
        if (! $r1->successful()) {
            throw new \RuntimeException('ClickHouse refreshSearchByVariant TRUNCATE error: ' . $r1->body());
        }

        $insert = "INSERT INTO {$db}.search_by_variant (placement_id, date, adults, children_ages, available, price, currency, updated_at) "
            . "SELECT pv.placement_id, a.date, pv.adults, pv.children_ages, a.available, "
            . "coalesce(p.price, toDecimal64(0, 2)), coalesce(p.currency, 'RUB'), "
            . "if(p.updated_at IS NULL, a.updated_at, greatest(a.updated_at, p.updated_at)) "
            . "FROM (SELECT * FROM {$db}.placement_variants FINAL WHERE adults > 0) AS pv "
            . "INNER JOIN (SELECT * FROM {$db}.availability_final FINAL) AS a ON a.placement_id = pv.placement_id "
            . "LEFT JOIN (SELECT * FROM {$db}.prices_by_day_final FINAL) AS p ON p.placement_id = a.placement_id AND p.date = a.date";
        $response = Http::timeout(max(60, $this->timeout))
            ->withBasicAuth($this->username, $this->password)
            ->withBody($insert, 'text/plain; charset=utf-8')
            ->post($url);
        if (! $response->successful()) {
            throw new \RuntimeException('ClickHouse refreshSearchByVariant INSERT error: ' . $response->body());
        }
    }

    /**
     * Вставка в search_by_variant строк по одному placement_id и диапазону дат из финальных таблиц (FINAL).
     * ReplacingMergeTree(updated_at) оставляет последнюю версию по (placement_id, date, adults, children_ages).
     */
    public function refreshSearchByVariantForPlacementAndDates(int $placementId, string $dateFrom, string $dateTo): void
    {
        $db = $this->database;
        $url = $this->baseUrl();
        $insert = "INSERT INTO {$db}.search_by_variant (placement_id, date, adults, children_ages, available, price, currency, updated_at) "
            . "SELECT pv.placement_id, a.date, pv.adults, pv.children_ages, a.available, "
            . "coalesce(p.price, toDecimal64(0, 2)), coalesce(p.currency, 'RUB'), "
            . "if(p.updated_at IS NULL, a.updated_at, greatest(a.updated_at, p.updated_at)) "
            . "FROM (SELECT * FROM {$db}.placement_variants FINAL WHERE adults > 0) AS pv "
            . "INNER JOIN (SELECT * FROM {$db}.availability_final FINAL) AS a ON a.placement_id = pv.placement_id "
            . "LEFT JOIN (SELECT * FROM {$db}.prices_by_day_final FINAL) AS p ON p.placement_id = a.placement_id AND p.date = a.date "
            . "WHERE pv.placement_id = " . (int) $placementId . " AND a.date >= '" . addslashes($dateFrom) . "' AND a.date <= '" . addslashes($dateTo) . "'";
        $response = Http::timeout(max(60, $this->timeout))
            ->withBasicAuth($this->username, $this->password)
            ->withBody($insert, 'text/plain; charset=utf-8')
            ->post($url);
        if (! $response->successful()) {
            throw new \RuntimeException('ClickHouse refreshSearchByVariantForPlacementAndDates error: ' . $response->body());
        }
    }

    private const SYNC_PLACEMENT_VARIANTS_CHUNK = 5000;

    /**
     * Копирует справочник placement_variants из MySQL в ClickHouse пакетами (INSERT FORMAT JSONEachRow).
     * Размер пакета задаётся SYNC_PLACEMENT_VARIANTS_CHUNK для избежания таймаутов и обрезки больших запросов.
     */
    public function syncPlacementVariantsFromDb(): int
    {
        $now = now()->format('Y-m-d H:i:s') . '.000';
        $total = 0;
        $db = $this->database;

        PlacementVariant::orderBy('id')->chunk(self::SYNC_PLACEMENT_VARIANTS_CHUNK, function ($rows) use ($now, $db, &$total) {
            $lines = [];
            foreach ($rows as $v) {
                $lines[] = json_encode([
                    'placement_id' => (int) $v->placement_id,
                    'adults' => (int) $v->adults,
                    'children_ages' => (string) $v->children_ages,
                    'updated_at' => $v->updated_at ? (\Carbon\Carbon::parse($v->updated_at)->format('Y-m-d H:i:s') . '.000') : $now,
                ], JSON_UNESCAPED_UNICODE);
            }

            $query = "INSERT INTO {$db}.placement_variants (placement_id, adults, children_ages, updated_at) FORMAT JSONEachRow";
            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->withBody(implode("\n", $lines), 'application/json')
                ->post($this->baseUrl() . '?' . http_build_query(['query' => $query]));

            if (! $response->successful()) {
                throw new \RuntimeException('ClickHouse INSERT placement_variants error: ' . $response->body());
            }
            $total += $rows->count();
        });

        return $total;
    }

    private function baseUrl(): string
    {
        $scheme = (int) $this->port === 443 ? 'https' : 'http';
        return sprintf('%s://%s:%s/', $scheme, $this->host, $this->port);
    }

    /**
     * Количество записей в таблицах ClickHouse (логический подсчёт с FINAL для ReplacingMergeTree).
     * Возвращает массив [ 'table_name' => count ] или пустой массив при ошибке.
     */
    public function getTableCounts(): array
    {
        $db = $this->database;
        $tables = ['placement_variants', 'availability_final', 'prices_by_day_final', 'search_by_variant'];
        $result = [];
        foreach ($tables as $table) {
            try {
                $rows = $this->select("SELECT count() AS cnt FROM {$db}.{$table} FINAL");
                $result[$table] = isset($rows[0]['cnt']) ? (int) $rows[0]['cnt'] : 0;
            } catch (\Throwable $e) {
                $result[$table] = null;
            }
        }
        return $result;
    }
}
