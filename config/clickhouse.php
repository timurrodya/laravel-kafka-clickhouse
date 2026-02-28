<?php

return [
    'host' => env('CLICKHOUSE_HOST', '127.0.0.1'),
    'port' => env('CLICKHOUSE_PORT', '8123'),
    'database' => env('CLICKHOUSE_DATABASE', 'analytics'),
    'username' => env('CLICKHOUSE_USER', 'default'),
    'password' => env('CLICKHOUSE_PASSWORD', ''),
    'timeout' => (float) env('CLICKHOUSE_TIMEOUT', 10),
];
