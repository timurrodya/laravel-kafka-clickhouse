-- v_placement_variants_stats: агрегаты по placement_variants FINAL (число вариантов, размещений, диапазоны placement_id и adults).
-- debug_zero_placement_log: сообщения из Kafka, у которых извлечённый placement_id = 0 (для диагностики; в финальные таблицы такие записи не пишутся).

-- Представление: сводка по placement_variants
DROP VIEW IF EXISTS analytics.v_placement_variants_stats;
CREATE VIEW analytics.v_placement_variants_stats AS
SELECT
    count() AS variants_cnt,
    uniq(placement_id) AS placement_ids_cnt,
    min(placement_id) AS min_placement_id,
    max(placement_id) AS max_placement_id,
    min(adults) AS min_adults,
    max(adults) AS max_adults
FROM analytics.placement_variants FINAL;

-- Таблица лога и MV: запись в лог только при извлечённом placement_id = 0 (то же выражение извлечения, что в 05).
DROP TABLE IF EXISTS analytics.mv_log_zero_placement_availability;
DROP TABLE IF EXISTS analytics.mv_log_zero_placement_prices;
DROP TABLE IF EXISTS analytics.mv_log_zero_placement_variants;
DROP TABLE IF EXISTS analytics.debug_zero_placement_log;

CREATE TABLE analytics.debug_zero_placement_log
(
    source String COMMENT 'availability | prices_by_day | placement_variants',
    placement_id UInt64,
    payload String,
    ts DateTime64(3)
)
ENGINE = MergeTree
ORDER BY (source, ts);

CREATE MATERIALIZED VIEW analytics.mv_log_zero_placement_availability
TO analytics.debug_zero_placement_log
AS
SELECT
    'availability' AS source,
    toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) AS placement_id,
    payload,
    now64(3) AS ts
FROM analytics.kafka_availability
WHERE toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) = 0;

CREATE MATERIALIZED VIEW analytics.mv_log_zero_placement_prices
TO analytics.debug_zero_placement_log
AS
SELECT
    'prices_by_day' AS source,
    toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) AS placement_id,
    payload,
    now64(3) AS ts
FROM analytics.kafka_prices_by_day
WHERE toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) = 0;

CREATE MATERIALIZED VIEW analytics.mv_log_zero_placement_variants
TO analytics.debug_zero_placement_log
AS
SELECT
    'placement_variants' AS source,
    toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) AS placement_id,
    payload,
    now64(3) AS ts
FROM analytics.kafka_placement_variants
WHERE toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) = 0;
