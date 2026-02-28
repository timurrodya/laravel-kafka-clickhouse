-- Пересоздать Kafka-таблицы и MV.
-- Формат сообщений при value.converter.schemas.enable=true: {"schema":...,"payload":{...}}
-- date в payload — целое число (дней с 1970-01-01), price — число.

DROP TABLE IF EXISTS analytics.mv_availability_to_final;
DROP TABLE IF EXISTS analytics.mv_prices_to_final;
DROP TABLE IF EXISTS analytics.kafka_availability;
DROP TABLE IF EXISTS analytics.kafka_prices_by_day;

CREATE TABLE analytics.kafka_availability
(
    schema String,
    payload String
)
ENGINE = Kafka
SETTINGS
    kafka_broker_list = 'kafka:29092',
    kafka_topic_list = 'myself.myself.availability',
    kafka_group_name = 'clickhouse_availability_v4',
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
    kafka_group_name = 'clickhouse_prices_v4',
    kafka_format = 'JSONEachRow',
    kafka_num_consumers = 1,
    kafka_skip_broken_messages = 1;

-- date в payload — число дней с 1970-01-01
CREATE MATERIALIZED VIEW analytics.mv_availability_to_final
TO analytics.availability_final
AS
SELECT
    toUInt64(JSONExtractUInt(payload, 'hotel_id')) AS hotel_id,
    addDays(toDate('1970-01-01'), toInt64(JSONExtractUInt(payload, 'date'))) AS date,
    toUInt8(JSONExtractUInt(payload, 'available')) AS available,
    now64(3) AS updated_at
FROM analytics.kafka_availability;

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
