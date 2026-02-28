-- Финальные таблицы с ReplacingMergeTree (последнее состояние по ключу)

-- Итоговая доступность по отелю и дате (последнее значение по updated_at)
CREATE TABLE IF NOT EXISTS analytics.availability_final
(
    hotel_id UInt64,
    date Date,
    available UInt8,
    updated_at DateTime64(3),
    _version UInt64 MATERIALIZED toUnixTimestamp64Milli(updated_at)
)
ENGINE = ReplacingMergeTree(_version)
ORDER BY (hotel_id, date);

-- Итоговые цены по отелю и дате
CREATE TABLE IF NOT EXISTS analytics.prices_by_day_final
(
    hotel_id UInt64,
    date Date,
    price Decimal(12, 2),
    currency String,
    updated_at DateTime64(3),
    _version UInt64 MATERIALIZED toUnixTimestamp64Milli(updated_at)
)
ENGINE = ReplacingMergeTree(_version)
ORDER BY (hotel_id, date);
