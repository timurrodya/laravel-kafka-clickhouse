-- Таблицы с движком Kafka. Формат при value.converter.schemas.enable=true: {"schema":...,"payload":{...}}
-- Топики: myself.myself.availability, myself.myself.prices_by_day

CREATE DATABASE IF NOT EXISTS analytics;

CREATE TABLE IF NOT EXISTS analytics.kafka_availability
(
    schema String,
    payload String
)
ENGINE = Kafka
SETTINGS
    kafka_broker_list = 'kafka:29092',
    kafka_topic_list = 'myself.myself.availability',
    kafka_group_name = 'clickhouse_availability',
    kafka_format = 'JSONEachRow',
    kafka_num_consumers = 1,
    kafka_skip_broken_messages = 1;

CREATE TABLE IF NOT EXISTS analytics.kafka_prices_by_day
(
    schema String,
    payload String
)
ENGINE = Kafka
SETTINGS
    kafka_broker_list = 'kafka:29092',
    kafka_topic_list = 'myself.myself.prices_by_day',
    kafka_group_name = 'clickhouse_prices',
    kafka_format = 'JSONEachRow',
    kafka_num_consumers = 1,
    kafka_skip_broken_messages = 1;
