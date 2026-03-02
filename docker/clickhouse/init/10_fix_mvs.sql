-- Пересоздание только Materialized Views; Kafka-таблицы и оффсеты consumer group не трогаются.
-- Содержимое: плоский разбор payload (placement_id, adults, children_ages, date, price, available, currency), фильтр placement_id > 0 и adults > 0, явные алиасы колонок в SELECT для корректной записи в target-таблицы.

-- placement_variants
DROP TABLE IF EXISTS analytics.mv_placement_variants_to_final;
CREATE MATERIALIZED VIEW analytics.mv_placement_variants_to_final
TO analytics.placement_variants
AS
SELECT
    toUInt64(JSONExtractUInt(payload, 'placement_id'))    AS placement_id,
    toUInt8(JSONExtractUInt(payload, 'adults'))           AS adults,
    coalesce(nullIf(JSONExtractString(payload, 'children_ages'), ''), '') AS children_ages,
    now64(3)                                              AS updated_at
FROM analytics.kafka_placement_variants
WHERE toUInt64(JSONExtractUInt(payload, 'placement_id')) > 0
  AND toUInt8(JSONExtractUInt(payload, 'adults')) > 0;

-- availability_final → search_by_variant (MV при INSERT в availability_final)
-- КРИТИЧНО: явные AS-алиасы на всех колонках — иначе CH хранит 'a.placement_id', 'pv.adults' как имена
-- и при INSERT INTO search_by_variant name-mapping не находит эти колонки → вставляет default=0
DROP TABLE IF EXISTS analytics.mv_availability_to_search_by_variant;
CREATE MATERIALIZED VIEW analytics.mv_availability_to_search_by_variant
TO analytics.search_by_variant
AS
SELECT
    a.placement_id                              AS placement_id,
    a.date                                      AS date,
    pv.adults                                   AS adults,
    pv.children_ages                            AS children_ages,
    a.available                                 AS available,
    coalesce(p.price, toDecimal64(0, 2))        AS price,
    coalesce(p.currency, 'RUB')                 AS currency,
    a.updated_at                                AS updated_at
FROM analytics.availability_final AS a
ALL INNER JOIN (
    SELECT placement_id, adults, children_ages
    FROM analytics.placement_variants FINAL
    WHERE adults > 0
) AS pv ON pv.placement_id = a.placement_id
LEFT JOIN analytics.prices_by_day_final AS p
    ON p.placement_id = a.placement_id AND p.date = a.date;

-- prices_by_day_final → search_by_variant (MV при INSERT в prices_by_day_final)
DROP TABLE IF EXISTS analytics.mv_prices_to_search_by_variant;
CREATE MATERIALIZED VIEW analytics.mv_prices_to_search_by_variant
TO analytics.search_by_variant
AS
SELECT
    p.placement_id                              AS placement_id,
    p.date                                      AS date,
    pv.adults                                   AS adults,
    pv.children_ages                            AS children_ages,
    coalesce(a.available, toUInt8(0))           AS available,
    p.price                                     AS price,
    p.currency                                  AS currency,
    p.updated_at                                AS updated_at
FROM analytics.prices_by_day_final AS p
ALL INNER JOIN (
    SELECT placement_id, adults, children_ages
    FROM analytics.placement_variants FINAL
    WHERE adults > 0
) AS pv ON pv.placement_id = p.placement_id
LEFT JOIN analytics.availability_final AS a
    ON a.placement_id = p.placement_id AND a.date = p.date;

-- kafka_availability → search_by_variant (subquery парсит payload в src, JOIN по src.placement_id и placement_variants/prices_by_day_final; явные алиасы AS для колонок target)
DROP TABLE IF EXISTS analytics.mv_kafka_availability_direct_to_search;
CREATE MATERIALIZED VIEW analytics.mv_kafka_availability_direct_to_search
TO analytics.search_by_variant
AS
SELECT
    src.placement_id                            AS placement_id,
    src.date                                    AS date,
    pv.adults                                   AS adults,
    pv.children_ages                            AS children_ages,
    src.available                               AS available,
    coalesce(p.price, toDecimal64(0, 2))        AS price,
    coalesce(p.currency, 'RUB')                 AS currency,
    now64(3)                                    AS updated_at
FROM (
    SELECT
        toUInt64(JSONExtractUInt(payload, 'placement_id'))               AS placement_id,
        addDays(toDate('1970-01-01'), toInt64(JSONExtractUInt(payload, 'date'))) AS date,
        toUInt8(JSONExtractUInt(payload, 'available'))                   AS available
    FROM analytics.kafka_availability
    WHERE toUInt64(JSONExtractUInt(payload, 'placement_id')) > 0
) AS src
ALL INNER JOIN (
    SELECT placement_id, adults, children_ages
    FROM analytics.placement_variants FINAL
    WHERE adults > 0
) AS pv ON pv.placement_id = src.placement_id
LEFT JOIN analytics.prices_by_day_final AS p
    ON p.placement_id = src.placement_id AND p.date = src.date;

-- kafka_prices_by_day → search_by_variant (subquery разбирает payload, JOIN с placement_variants и availability_final)
DROP TABLE IF EXISTS analytics.mv_kafka_prices_direct_to_search;
CREATE MATERIALIZED VIEW analytics.mv_kafka_prices_direct_to_search
TO analytics.search_by_variant
AS
SELECT
    src.placement_id                            AS placement_id,
    src.date                                    AS date,
    pv.adults                                   AS adults,
    pv.children_ages                            AS children_ages,
    coalesce(a.available, toUInt8(0))           AS available,
    src.price                                   AS price,
    src.currency                                AS currency,
    now64(3)                                    AS updated_at
FROM (
    SELECT
        toUInt64(JSONExtractUInt(payload, 'placement_id'))               AS placement_id,
        addDays(toDate('1970-01-01'), toInt64(JSONExtractUInt(payload, 'date'))) AS date,
        toDecimal64(coalesce(
            nullIf(JSONExtractString(payload, 'price'), ''),
            toString(JSONExtractFloat(payload, 'price'))
        ), 2)                                                            AS price,
        coalesce(JSONExtractString(payload, 'currency'), 'RUB')         AS currency
    FROM analytics.kafka_prices_by_day
    WHERE toUInt64(JSONExtractUInt(payload, 'placement_id')) > 0
) AS src
ALL INNER JOIN (
    SELECT placement_id, adults, children_ages
    FROM analytics.placement_variants FINAL
    WHERE adults > 0
) AS pv ON pv.placement_id = src.placement_id
LEFT JOIN analytics.availability_final AS a
    ON a.placement_id = src.placement_id AND a.date = src.date;

-- kafka_availability_for_search → search_by_variant (отдельный consumer group)
DROP TABLE IF EXISTS analytics.mv_kafka_availability_to_search_by_variant;
CREATE MATERIALIZED VIEW analytics.mv_kafka_availability_to_search_by_variant
TO analytics.search_by_variant
AS
SELECT
    src.placement_id                            AS placement_id,
    src.date                                    AS date,
    pv.adults                                   AS adults,
    pv.children_ages                            AS children_ages,
    src.available                               AS available,
    coalesce(p.price, toDecimal64(0, 2))        AS price,
    coalesce(p.currency, 'RUB')                 AS currency,
    now64(3)                                    AS updated_at
FROM (
    SELECT
        toUInt64(JSONExtractUInt(payload, 'placement_id'))               AS placement_id,
        addDays(toDate('1970-01-01'), toInt64(JSONExtractUInt(payload, 'date'))) AS date,
        toUInt8(JSONExtractUInt(payload, 'available'))                   AS available
    FROM analytics.kafka_availability_for_search
    WHERE toUInt64(JSONExtractUInt(payload, 'placement_id')) > 0
) AS src
ALL INNER JOIN (
    SELECT placement_id, adults, children_ages
    FROM analytics.placement_variants FINAL
    WHERE adults > 0
) AS pv ON pv.placement_id = src.placement_id
LEFT JOIN analytics.prices_by_day_final AS p
    ON p.placement_id = src.placement_id AND p.date = src.date;

-- kafka_prices_by_day_for_search → search_by_variant (отдельный consumer group)
DROP TABLE IF EXISTS analytics.mv_kafka_prices_to_search_by_variant;
CREATE MATERIALIZED VIEW analytics.mv_kafka_prices_to_search_by_variant
TO analytics.search_by_variant
AS
SELECT
    src.placement_id                            AS placement_id,
    src.date                                    AS date,
    pv.adults                                   AS adults,
    pv.children_ages                            AS children_ages,
    coalesce(a.available, toUInt8(0))           AS available,
    src.price                                   AS price,
    src.currency                                AS currency,
    now64(3)                                    AS updated_at
FROM (
    SELECT
        toUInt64(JSONExtractUInt(payload, 'placement_id'))               AS placement_id,
        addDays(toDate('1970-01-01'), toInt64(JSONExtractUInt(payload, 'date'))) AS date,
        toDecimal64(coalesce(
            nullIf(JSONExtractString(payload, 'price'), ''),
            toString(JSONExtractFloat(payload, 'price'))
        ), 2)                                                            AS price,
        coalesce(JSONExtractString(payload, 'currency'), 'RUB')         AS currency
    FROM analytics.kafka_prices_by_day_for_search
    WHERE toUInt64(JSONExtractUInt(payload, 'placement_id')) > 0
) AS src
ALL INNER JOIN (
    SELECT placement_id, adults, children_ages
    FROM analytics.placement_variants FINAL
    WHERE adults > 0
) AS pv ON pv.placement_id = src.placement_id
LEFT JOIN analytics.availability_final AS a
    ON a.placement_id = src.placement_id AND a.date = src.date;
