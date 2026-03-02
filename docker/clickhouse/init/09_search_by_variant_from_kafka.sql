-- Дополнительные Kafka-таблицы (отдельные consumer group) для записи в search_by_variant напрямую из топиков availability и prices_by_day.
-- Payload: поддержка плоского формата и payload.after.*; date — строка YYYY-MM-DD или число (дней с эпохи).

CREATE TABLE IF NOT EXISTS analytics.kafka_availability_for_search
(
    schema String,
    payload String
)
ENGINE = Kafka
SETTINGS
    kafka_broker_list = 'kafka:29092',
    kafka_topic_list = 'myself.myself.availability',
    kafka_group_name = 'clickhouse_availability_search_v1',
    kafka_format = 'JSONEachRow',
    kafka_num_consumers = 1,
    kafka_skip_broken_messages = 1;

CREATE TABLE IF NOT EXISTS analytics.kafka_prices_by_day_for_search
(
    schema String,
    payload String
)
ENGINE = Kafka
SETTINGS
    kafka_broker_list = 'kafka:29092',
    kafka_topic_list = 'myself.myself.prices_by_day',
    kafka_group_name = 'clickhouse_prices_search_v1',
    kafka_format = 'JSONEachRow',
    kafka_num_consumers = 1,
    kafka_skip_broken_messages = 1;

-- Извлечение полей: поддержка плоского payload и вложенного payload.after (зависит от конфига Connect).
-- placement_id: payload.placement_id или payload.after.placement_id
-- date: строка YYYY-MM-DD или число (дни с 1970-01-01)
DROP TABLE IF EXISTS analytics.mv_kafka_availability_to_search_by_variant;
CREATE MATERIALIZED VIEW analytics.mv_kafka_availability_to_search_by_variant
TO analytics.search_by_variant
AS
SELECT
    toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) AS placement_id,
    coalesce(toDateOrNull(JSONExtractString(payload, 'date')), toDateOrNull(JSONExtractString(payload, 'after.date')), addDays(toDate('1970-01-01'), toInt64(coalesce(JSONExtractUInt(payload, 'date'), JSONExtractUInt(payload, 'after.date'), 0)))) AS date,
    pv.adults,
    pv.children_ages,
    toUInt8(coalesce(JSONExtractUInt(payload, 'available'), JSONExtractUInt(payload, 'after.available'))) AS available,
    coalesce(p.price, toDecimal64(0, 2)) AS price,
    coalesce(p.currency, 'RUB') AS currency,
    now64(3) AS updated_at
FROM analytics.kafka_availability_for_search
ALL INNER JOIN (SELECT * FROM analytics.placement_variants FINAL WHERE adults > 0) AS pv ON pv.placement_id = toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id')))
LEFT JOIN analytics.prices_by_day_final AS p ON p.placement_id = toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id')))
    AND p.date = coalesce(toDateOrNull(JSONExtractString(payload, 'date')), toDateOrNull(JSONExtractString(payload, 'after.date')), addDays(toDate('1970-01-01'), toInt64(coalesce(JSONExtractUInt(payload, 'date'), JSONExtractUInt(payload, 'after.date'), 0))));

DROP TABLE IF EXISTS analytics.mv_kafka_prices_to_search_by_variant;
CREATE MATERIALIZED VIEW analytics.mv_kafka_prices_to_search_by_variant
TO analytics.search_by_variant
AS
SELECT
    toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) AS placement_id,
    coalesce(toDateOrNull(JSONExtractString(payload, 'date')), toDateOrNull(JSONExtractString(payload, 'after.date')), addDays(toDate('1970-01-01'), toInt64(coalesce(JSONExtractUInt(payload, 'date'), JSONExtractUInt(payload, 'after.date'), 0)))) AS date,
    pv.adults,
    pv.children_ages,
    coalesce(a.available, toUInt8(0)) AS available,
    toDecimal64(coalesce(nullIf(coalesce(JSONExtractString(payload, 'price'), JSONExtractString(payload, 'after.price')), ''), toString(coalesce(JSONExtractFloat(payload, 'price'), JSONExtractFloat(payload, 'after.price')))), 2) AS price,
    coalesce(JSONExtractString(payload, 'currency'), JSONExtractString(payload, 'after.currency'), 'RUB') AS currency,
    now64(3) AS updated_at
FROM analytics.kafka_prices_by_day_for_search
ALL INNER JOIN (SELECT * FROM analytics.placement_variants FINAL WHERE adults > 0) AS pv ON pv.placement_id = toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id')))
LEFT JOIN analytics.availability_final AS a ON a.placement_id = toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id')))
    AND a.date = coalesce(toDateOrNull(JSONExtractString(payload, 'date')), toDateOrNull(JSONExtractString(payload, 'after.date')), addDays(toDate('1970-01-01'), toInt64(coalesce(JSONExtractUInt(payload, 'date'), JSONExtractUInt(payload, 'after.date'), 0))));
