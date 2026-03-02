-- Миграция: пересоздать availability_final и prices_by_day_final с placement_id.
-- Запускать один раз, если таблицы были созданы со старой схемой (hotel_id).
-- Данные в финальных таблицах будут потеряны; заново нальются из Kafka после перезапуска консьюмеров.

DROP TABLE IF EXISTS analytics.mv_availability_to_final;
DROP TABLE IF EXISTS analytics.mv_prices_to_final;

DROP TABLE IF EXISTS analytics.availability_final;
DROP TABLE IF EXISTS analytics.prices_by_day_final;

CREATE TABLE analytics.availability_final
(
    placement_id UInt64,
    date Date,
    available UInt8,
    updated_at DateTime64(3),
    _version UInt64 MATERIALIZED toUnixTimestamp64Milli(updated_at)
)
ENGINE = ReplacingMergeTree(_version)
ORDER BY (placement_id, date);

CREATE TABLE analytics.prices_by_day_final
(
    placement_id UInt64,
    date Date,
    price Decimal(12, 2),
    currency String,
    updated_at DateTime64(3),
    _version UInt64 MATERIALIZED toUnixTimestamp64Milli(updated_at)
)
ENGINE = ReplacingMergeTree(_version)
ORDER BY (placement_id, date);

CREATE MATERIALIZED VIEW analytics.mv_availability_to_final
TO analytics.availability_final
AS
SELECT
    toUInt64(JSONExtractUInt(payload, 'placement_id')) AS placement_id,
    addDays(toDate('1970-01-01'), toInt64(JSONExtractUInt(payload, 'date'))) AS date,
    toUInt8(JSONExtractUInt(payload, 'available')) AS available,
    now64(3) AS updated_at
FROM analytics.kafka_availability;

CREATE MATERIALIZED VIEW analytics.mv_prices_to_final
TO analytics.prices_by_day_final
AS
SELECT
    toUInt64(JSONExtractUInt(payload, 'placement_id')) AS placement_id,
    addDays(toDate('1970-01-01'), toInt64(JSONExtractUInt(payload, 'date'))) AS date,
    toDecimal64(coalesce(nullIf(JSONExtractString(payload, 'price'), ''), toString(JSONExtractFloat(payload, 'price'))), 2) AS price,
    coalesce(JSONExtractString(payload, 'currency'), 'RUB') AS currency,
    now64(3) AS updated_at
FROM analytics.kafka_prices_by_day;
