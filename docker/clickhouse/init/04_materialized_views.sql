-- Материализованные представления: при появлении строки в Kafka-таблице
-- обрабатываем её и вставляем в финальную таблицу (ReplacingMergeTree сам схлопнет дубликаты).

CREATE MATERIALIZED VIEW IF NOT EXISTS analytics.mv_availability_to_final
TO analytics.availability_final
AS
SELECT
    hotel_id,
    date,
    available,
    coalesce(updated_at, now64(3)) AS updated_at
FROM analytics.kafka_availability;

CREATE MATERIALIZED VIEW IF NOT EXISTS analytics.mv_prices_to_final
TO analytics.prices_by_day_final
AS
SELECT
    hotel_id,
    date,
    price,
    coalesce(currency, 'RUB') AS currency,
    coalesce(updated_at, now64(3)) AS updated_at
FROM analytics.kafka_prices_by_day;
