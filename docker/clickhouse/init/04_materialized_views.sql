-- MV разбирают payload: date — число дней с 1970-01-01, остальное по типам.

CREATE MATERIALIZED VIEW IF NOT EXISTS analytics.mv_availability_to_final
TO analytics.availability_final
AS
SELECT
    toUInt64(JSONExtractUInt(payload, 'hotel_id')) AS hotel_id,
    addDays(toDate('1970-01-01'), toInt64(JSONExtractUInt(payload, 'date'))) AS date,
    toUInt8(JSONExtractUInt(payload, 'available')) AS available,
    now64(3) AS updated_at
FROM analytics.kafka_availability;

CREATE MATERIALIZED VIEW IF NOT EXISTS analytics.mv_prices_to_final
TO analytics.prices_by_day_final
AS
SELECT
    toUInt64(JSONExtractUInt(payload, 'hotel_id')) AS hotel_id,
    addDays(toDate('1970-01-01'), toInt64(JSONExtractUInt(payload, 'date'))) AS date,
    toDecimal64(coalesce(nullIf(JSONExtractString(payload, 'price'), ''), toString(JSONExtractFloat(payload, 'price'))), 2) AS price,
    coalesce(JSONExtractString(payload, 'currency'), 'RUB') AS currency,
    now64(3) AS updated_at
FROM analytics.kafka_prices_by_day;
