-- Таблицы для CDC (Debezium) и приложения в БД myself
CREATE DATABASE IF NOT EXISTS myself;
USE myself;

-- Справочник отелей (для веб-морды)
CREATE TABLE IF NOT EXISTS hotels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS availability (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hotel_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    available TINYINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    UNIQUE KEY uq_hotel_date (hotel_id, date)
);

CREATE TABLE IF NOT EXISTS prices_by_day (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hotel_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'RUB',
    updated_at TIMESTAMP(3) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    UNIQUE KEY uq_hotel_date (hotel_id, date)
);

-- Права для Debezium (чтение binlog требует REPLICATION SLAVE, REPLICATION CLIENT)
-- От имени root коннектор уже имеет доступ; для отдельного пользователя:
-- GRANT SELECT, RELOAD, SHOW DATABASES, REPLICATION SLAVE, REPLICATION CLIENT ON *.* TO 'debezium'@'%';
