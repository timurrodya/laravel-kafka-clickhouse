-- Таблица поиска: по одной строке на (placement_id, date, adults, children_ages); заполняется MV при INSERT в availability_final и prices_by_day_final.

DROP TABLE IF EXISTS analytics.mv_availability_to_search_by_variant;
DROP TABLE IF EXISTS analytics.mv_prices_to_search_by_variant;
DROP TABLE IF EXISTS analytics.search_by_variant;

CREATE TABLE analytics.search_by_variant
(
    placement_id UInt64,
    date Date,
    adults UInt8,
    children_ages String,
    available UInt8,
    price Decimal(12, 2),
    currency String,
    updated_at DateTime64(3)
)
ENGINE = ReplacingMergeTree(updated_at)
ORDER BY (placement_id, date, adults, children_ages);

-- При INSERT в availability_final: JOIN с placement_variants (FINAL, adults>0) и LEFT JOIN с prices_by_day_final; COALESCE для price/currency — колонки non-Nullable.
CREATE MATERIALIZED VIEW analytics.mv_availability_to_search_by_variant
TO analytics.search_by_variant
AS
SELECT
    a.placement_id,
    a.date,
    pv.adults,
    pv.children_ages,
    a.available,
    coalesce(p.price, toDecimal64(0, 2)) AS price,
    coalesce(p.currency, 'RUB') AS currency,
    a.updated_at
FROM analytics.availability_final AS a
ALL INNER JOIN (SELECT * FROM analytics.placement_variants FINAL WHERE adults > 0) AS pv ON a.placement_id = pv.placement_id
LEFT JOIN analytics.prices_by_day_final AS p ON p.placement_id = a.placement_id AND p.date = a.date;

-- При INSERT в prices_by_day_final: JOIN с placement_variants (FINAL, adults>0) и LEFT JOIN с availability_final; COALESCE для available — колонка non-Nullable.
CREATE MATERIALIZED VIEW analytics.mv_prices_to_search_by_variant
TO analytics.search_by_variant
AS
SELECT
    p.placement_id,
    p.date,
    pv.adults,
    pv.children_ages,
    coalesce(a.available, toUInt8(0)) AS available,
    p.price,
    p.currency,
    p.updated_at
FROM analytics.prices_by_day_final AS p
ALL INNER JOIN (SELECT * FROM analytics.placement_variants FINAL WHERE adults > 0) AS pv ON p.placement_id = pv.placement_id
LEFT JOIN analytics.availability_final AS a ON a.placement_id = p.placement_id AND a.date = p.date;
