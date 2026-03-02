-- БД для CDC (Debezium) и приложения.
-- Таблицы создаются миграциями Laravel: php artisan migrate
-- (hotels, placements, placement_variants, availability, prices_by_day).

CREATE DATABASE IF NOT EXISTS myself;
USE myself;

-- Права для Debezium (чтение binlog):
-- GRANT SELECT, RELOAD, SHOW DATABASES, REPLICATION SLAVE, REPLICATION CLIENT ON *.* TO 'debezium'@'%';
