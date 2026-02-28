<?php

namespace App\Services;

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
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line, "\t");
            $result[] = array_combine($header, $row);
        }
        return $result;
    }

    /**
     * Доступность и цены по отелю за период (финальные таблицы, ReplacingMergeTree).
     */
    public function getAvailabilityAndPrices(int $hotelId, string $dateFrom, string $dateTo): array
    {
        $db = $this->database;
        $query = "
            SELECT
                a.date AS date,
                a.available AS available,
                p.price AS price,
                p.currency AS currency
            FROM {$db}.availability_final AS a
            FINAL
            LEFT JOIN {$db}.prices_by_day_final AS p
            FINAL
                ON a.hotel_id = p.hotel_id AND a.date = p.date
            WHERE a.hotel_id = {hotelId:UInt64}
              AND a.date >= '{dateFrom:Date}'
              AND a.date <= '{dateTo:Date}'
            ORDER BY a.date
        ";

        $query = str_replace(
            ['{hotelId:UInt64}', '{dateFrom:Date}', '{dateTo:Date}'],
            [(string) $hotelId, $dateFrom, $dateTo],
            $query
        );

        return $this->select($query);
    }

    private function baseUrl(): string
    {
        $scheme = (int) $this->port === 443 ? 'https' : 'http';
        return sprintf('%s://%s:%s/', $scheme, $this->host, $this->port);
    }
}
