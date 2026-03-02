-- Финальные таблицы с ReplacingMergeTree (последнее состояние по ключу)

-- Справочник вариантов поиска: размещение + взрослые + возрасты детей (джойн при чтении)
CREATE TABLE IF NOT EXISTS analytics.placement_variants
(
    placement_id UInt64,
    adults UInt8,
    children_ages String,
    updated_at DateTime64(3)
)
ENGINE = ReplacingMergeTree(updated_at)
ORDER BY (placement_id, adults, children_ages);

-- Итоговая доступность по размещению и дате (последнее значение по updated_at)
CREATE TABLE IF NOT EXISTS analytics.availability_final
(
    placement_id UInt64,
    date Date,
    available UInt8,
    updated_at DateTime64(3),
    _version UInt64 MATERIALIZED toUnixTimestamp64Milli(updated_at)
)
ENGINE = ReplacingMergeTree(_version)
ORDER BY (placement_id, date);

-- Итоговые цены по размещению и дате
CREATE TABLE IF NOT EXISTS analytics.prices_by_day_final
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
