-- Kafka-таблицы и MV: потребление топиков myself.myself.{availability,prices_by_day,placement_variants}.
-- Ожидаемый формат: JSONEachRow, payload с полями placement_id, date (дни с 1970-01-01), available/price/currency/adults/children_ages; поддерживается вложенный payload.after.*.

DROP TABLE IF EXISTS analytics.mv_availability_to_final;
DROP TABLE IF EXISTS analytics.mv_prices_to_final;
DROP TABLE IF EXISTS analytics.mv_placement_variants_to_final;
DROP TABLE IF EXISTS analytics.kafka_availability;
DROP TABLE IF EXISTS analytics.kafka_prices_by_day;
DROP TABLE IF EXISTS analytics.kafka_placement_variants;

CREATE TABLE analytics.kafka_availability
(
    schema String,
    payload String
)
ENGINE = Kafka
SETTINGS
    kafka_broker_list = 'kafka:29092',
    kafka_topic_list = 'myself.myself.availability',
    kafka_group_name = 'clickhouse_availability_v5',
    kafka_format = 'JSONEachRow',
    kafka_num_consumers = 1,
    kafka_skip_broken_messages = 1;

CREATE TABLE analytics.kafka_prices_by_day
(
    schema String,
    payload String
)
ENGINE = Kafka
SETTINGS
    kafka_broker_list = 'kafka:29092',
    kafka_topic_list = 'myself.myself.prices_by_day',
    kafka_group_name = 'clickhouse_prices_v5',
    kafka_format = 'JSONEachRow',
    kafka_num_consumers = 1,
    kafka_skip_broken_messages = 1;

CREATE TABLE analytics.kafka_placement_variants
(
    schema String,
    payload String
)
ENGINE = Kafka
SETTINGS
    kafka_broker_list = 'kafka:29092',
    kafka_topic_list = 'myself.myself.placement_variants',
    kafka_group_name = 'clickhouse_placement_variants_v1',
    kafka_format = 'JSONEachRow',
    kafka_num_consumers = 1,
    kafka_skip_broken_messages = 1;

-- Исключаем сообщения с невалидным placement_id (0 или не извлечён) — такие записи не попадают в финальные таблицы.

--
CREATE MATERIALIZED VIEW analytics.mv_availability_to_final
TO analytics.availability_final
AS
SELECT
    toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) AS placement_id,
    addDays(toDate('1970-01-01'), toInt64(coalesce(JSONExtractUInt(payload, 'date'), JSONExtractUInt(payload, 'after.date'), 0))) AS date,
    toUInt8(coalesce(JSONExtractUInt(payload, 'available'), JSONExtractUInt(payload, 'after.available'))) AS available,
    now64(3) AS updated_at
FROM analytics.kafka_availability
WHERE toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) > 0;

CREATE MATERIALIZED VIEW analytics.mv_prices_to_final
TO analytics.prices_by_day_final
AS
SELECT
    toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) AS placement_id,
    addDays(toDate('1970-01-01'), toInt64(coalesce(JSONExtractUInt(payload, 'date'), JSONExtractUInt(payload, 'after.date'), 0))) AS date,
    toDecimal64(coalesce(nullIf(JSONExtractString(payload, 'price'), ''), nullIf(JSONExtractString(payload, 'after.price'), ''), toString(JSONExtractFloat(payload, 'price')), toString(JSONExtractFloat(payload, 'after.price'))), 2) AS price,
    coalesce(JSONExtractString(payload, 'currency'), JSONExtractString(payload, 'after.currency'), 'RUB') AS currency,
    now64(3) AS updated_at
FROM analytics.kafka_prices_by_day
WHERE toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) > 0;

-- placement_variants: извлечение placement_id, adults, children_ages (плоский payload и payload.after.*).
CREATE MATERIALIZED VIEW analytics.mv_placement_variants_to_final
TO analytics.placement_variants
AS
SELECT
    toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) AS placement_id,
    toUInt8(coalesce(JSONExtractUInt(payload, 'adults'), JSONExtractUInt(payload, 'after.adults'))) AS adults,
    coalesce(
        nullIf(JSONExtractString(payload, 'children_ages'), ''),
        nullIf(JSONExtractString(payload, 'after.children_ages'), ''),
        ''
    ) AS children_ages,
    now64(3) AS updated_at
FROM analytics.kafka_placement_variants
WHERE toUInt64(coalesce(JSONExtractUInt(payload, 'placement_id'), JSONExtractUInt(payload, 'after.placement_id'))) > 0
  AND toUInt8(coalesce(JSONExtractUInt(payload, 'adults'), JSONExtractUInt(payload, 'after.adults'))) > 0;
