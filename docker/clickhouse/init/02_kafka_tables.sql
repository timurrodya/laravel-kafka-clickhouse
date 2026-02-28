-- Таблицы с движком Kafka читают топики после SMT ExtractNewRecordState (плоский after).
-- Имена топиков по умолчанию: {server_name}.{database}.{table}
-- Для коннектора "myself-mysql" топики: myself.myself.availability, myself.myself.prices_by_day

CREATE DATABASE IF NOT EXISTS analytics;

-- Сырые данные из топика availability (плоский after от Debezium)
CREATE TABLE IF NOT EXISTS analytics.kafka_availability
(
    id UInt64,
    hotel_id UInt64,
    date Date,
    available UInt8,
    updated_at Nullable(DateTime64(3))
)
ENGINE = Kafka
SETTINGS
    kafka_broker_list = 'kafka:29092',
    kafka_topic_list = 'myself.myself.availability',
    kafka_group_name = 'clickhouse_availability',
    kafka_format = 'JSONEachRow',
    kafka_num_consumers = 1;

-- Сырые данные из топика prices_by_day
CREATE TABLE IF NOT EXISTS analytics.kafka_prices_by_day
(
    id UInt64,
    hotel_id UInt64,
    date Date,
    price Decimal(12, 2),
    currency String,
    updated_at Nullable(DateTime64(3))
)
ENGINE = Kafka
SETTINGS
    kafka_broker_list = 'kafka:29092',
    kafka_topic_list = 'myself.myself.prices_by_day',
    kafka_group_name = 'clickhouse_prices',
    kafka_format = 'JSONEachRow',
    kafka_num_consumers = 1;
