-- Пересоздать только MV для цен (price из payload как строка).
DROP TABLE IF EXISTS analytics.mv_prices_to_final;

CREATE MATERIALIZED VIEW analytics.mv_prices_to_final
TO analytics.prices_by_day_final
AS
SELECT
    toUInt64(JSONExtractUInt(payload, 'hotel_id')) AS hotel_id,
    addDays(toDate('1970-01-01'), toInt64(JSONExtractUInt(payload, 'date'))) AS date,
    toDecimal64(coalesce(nullIf(JSONExtractString(payload, 'price'), ''), toString(JSONExtractFloat(payload, 'price'))), 2) AS price,
    coalesce(JSONExtractString(payload, 'currency'), 'RUB') AS currency,
    now64(3) AS updated_at
FROM analytics.kafka_prices_by_day;
