<?php
/**
 * Заглушка до установки Laravel.
 * После: composer create-project laravel/laravel . --no-install && composer install
 */
header('Content-Type: application/json');
echo json_encode([
    'app' => 'laravel-kafka-clickhouse',
    'status' => 'ok',
    'message' => 'Install Laravel or replace this file with Laravel public/index.php',
]);
