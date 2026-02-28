# Docker: Laravel + Debezium + Kafka + ClickHouse

Инфраструктура для потока данных **CDC (Debezium) → Kafka → ClickHouse** с материализованными представлениями и ReplacingMergeTree.

## Поток данных

1. **CDC**: Debezium в Kafka Connect следит за таблицами `availability` и `prices_by_day` в БД **myself** (MySQL) и шлёт каждое изменение в топики Kafka.
2. **SMT**: В коннекторе включён **ExtractNewRecordState** — в топике остаётся только «плоский» объект `after` (без envelope Debezium).
3. **ClickHouse**: Таблицы с движком **Kafka** читают эти топики.
4. **Обработка**: **MATERIALIZED VIEW** по каждой новой строке из Kafka-таблиц выполняют логику и пишут в финальные таблицы.
5. **Хранение**: Финальные таблицы на **ReplacingMergeTree** хранят последнее состояние по (hotel_id, date).

## Сервисы


| Сервис            | Порт | Описание                      |
| ----------------- | ---- | ----------------------------- |
| MySQL             | 3306 | БД `myself`, источник для CDC |
| Zookeeper         | 2181 | Для Kafka                     |
| Kafka             | 9092 | Брокер                        |
| Kafka Connect     | 8083 | Debezium коннектор            |
| ClickHouse HTTP   | 8123 | HTTP-интерфейс                |
| ClickHouse native | 9000 | Нативный протокол             |
| Nginx (Laravel)   | 8080 | Только при профиле `app`      |


## Быстрый старт

### 1. Только инфраструктура (без Laravel)

```bash
cp .env.example .env
docker compose up -d
```

Поднимутся: MySQL, Zookeeper, Kafka, Kafka Connect, ClickHouse, контейнер инициализации ClickHouse (`clickhouse-init`).

### 2. Регистрация коннектора Debezium

Коннектор нужно **один раз зарегистрировать** после старта Kafka Connect. Если при проверке статуса приходит `404 No status found for connector myself-mysql-connector` — коннектор ещё не зарегистрирован, выполните регистрацию ниже.

Убедитесь, что Kafka Connect уже запущен (обычно 30–60 с после `docker compose up`):

```bash
curl -s http://localhost:8083/
```

Зарегистрировать коннектор:

**Windows (PowerShell):**

```powershell
$body = Get-Content docker\debezium\connector-myself.json -Raw
Invoke-RestMethod -Uri http://localhost:8083/connectors -Method Post -Body $body -ContentType "application/json"
```

**Linux/macOS (Bash):**

```bash
curl -s -X POST -H "Content-Type: application/json" --data @docker/debezium/connector-myself.json http://localhost:8083/connectors
```

Или скрипт (если установлен jq):

```bash
./docker/debezium/register-connector.sh
```

Проверить, что коннектор есть в списке и посмотреть статус:

```bash
curl -s http://localhost:8083/connectors
curl -s http://localhost:8083/connectors/myself-mysql-connector/status | jq
```

Ожидаемо в статусе: `"state": "RUNNING"`. Если снова 404 — регистрация не прошла (проверьте логи: `docker compose logs kafka-connect`).

**Чтобы данные из Kafka попали в ClickHouse**, сделайте шаг 3 ниже (инициализация ClickHouse), затем подождите 10–30 секунд.

### 3. Инициализация ClickHouse (обязательно для потока данных)

Скрипты в `docker/clickhouse/init/` создают БД `analytics`, Kafka-таблицы, финальные таблицы и материализованные представления. Один раз после первого запуска можно выполнить вручную:

```bash
docker compose run --rm clickhouse-init
```

Либо выполнить SQL из папки `docker/clickhouse/init/` вручную в клиенте ClickHouse (порт 8123).

### 4. Запуск с Laravel (веб-морда: отели, даты, данные из ClickHouse)

В проекте уже есть Laravel-приложение с таблицей **hotels** и страницей выбора отеля и дат для просмотра доступности и цен из ClickHouse.

1. Установите зависимости и сгенерируйте ключ:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

1. Запустите стек с приложением:

```bash
docker compose --profile app up -d
```

1. Выполните миграции и сидер (таблица `hotels`, демо-отели):

```bash
docker compose exec laravel php artisan migrate --force
docker compose exec laravel php artisan db:seed --force
```

Приложение: **[http://localhost:8080](http://localhost:8080)** (или `NGINX_PORT` из `.env`).

На главной странице: выберите отель, период (дата с / дата по) и нажмите «Показать». Таблица выведет данные из ClickHouse (таблицы `availability_final` и `prices_by_day_final`) — доступность и цены по дням.

## Переменные окружения (.env)


| Переменная                  | По умолчанию                                   | Описание                |
| --------------------------- | ---------------------------------------------- | ----------------------- |
| MYSQL_ROOT_PASSWORD         | root                                           | Пароль root MySQL       |
| MYSQL_DATABASE              | myself                                         | БД для приложения и CDC |
| MYSQL_USER / MYSQL_PASSWORD | laravel / secret                               | Пользователь приложения |
| KAFKA_CONNECT_URL           | [http://localhost:8083](http://localhost:8083) | URL Kafka Connect       |
| CLICKHOUSE_DB               | analytics                                      | БД в ClickHouse         |
| NGINX_PORT                  | 8080                                           | Порт веб-сервера        |


## Таблицы MySQL (myself)

При первом запуске MySQL выполняет скрипты из `docker/mysql/init/` и создаёт:

- **hotels**: id, name, address, city, created_at, updated_at (справочник отелей для веб-морды)
- **availability**: id, hotel_id, date, available, updated_at  
- **prices_by_day**: id, hotel_id, date, price, currency, updated_at

Демо-данные (отели, доступность и цены на 2026-02-28 … 2026-03-08) заполняются через Laravel-сидер:

```bash
docker compose exec laravel php artisan db:seed --force
```

**Если MySQL уже был запущен ранее** (init не выполнится повторно), создайте таблицы вручную:

**Linux/macOS:**

```bash
docker compose exec -T mysql mysql -uroot -proot myself < docker/mysql/init/01_schema_myself.sql
```

**Windows (PowerShell):**

```powershell
Get-Content docker\mysql\init\01_schema_myself.sql | docker compose exec -T mysql mysql -uroot -proot myself
```

После этого выполните миграции и сидер Laravel (см. шаг 4 быстрого старта).

## Топики Kafka

После регистрации коннектора Debezium создаёт топики:

- `myself.myself.availability`
- `myself.myself.prices_by_day`

Формат сообщений — плоский JSON (только поля из `after`), без обёртки Debezium.

## ClickHouse

- **Kafka-таблицы**: `analytics.kafka_availability`, `analytics.kafka_prices_by_day`  
- **Финальные таблицы**: `analytics.availability_final`, `analytics.prices_by_day_final` (ReplacingMergeTree)  
- **Materialized views**: `analytics.mv_availability_to_final`, `analytics.mv_prices_to_final`

Проверка данных:

```bash
curl -s "http://localhost:8123/?query=SELECT%20*%20FROM%20analytics.availability_final%20LIMIT%2010"
```

## Полезные команды

```bash
# Логи Kafka Connect (Debezium)
docker compose logs -f kafka-connect

# Логи ClickHouse
docker compose logs -f clickhouse

# Проверить данные в ClickHouse после прохода пайплайна
curl -s "http://localhost:8123/?query=SELECT%20*%20FROM%20analytics.availability_final%20LIMIT%2010"
curl -s "http://localhost:8123/?query=SELECT%20*%20FROM%20analytics.prices_by_day_final%20LIMIT%2010"

# Остановка
docker compose --profile app down
```

## Проверка работы пайплайна

1. Запустите стек: `docker compose --profile app up -d`.
2. Дождитесь готовности Kafka Connect, зарегистрируйте коннектор Debezium (см. выше).
3. Один раз выполните инициализацию ClickHouse: `docker compose run --rm clickhouse-init`.
4. Если MySQL поднят впервые — таблицы создадутся из `docker/mysql/init/01_schema_myself.sql`. Демо-данные — через `php artisan db:seed`. Если том MySQL уже был — примените 01_schema вручную и выполните миграции и сидер.
5. Убедитесь, что данные дошли до Kafka и ClickHouse.
6. Откройте [http://localhost:8080](http://localhost:8080), выберите отель «Отель Центральный», даты 2026-02-28 … 2026-03-08, нажмите «Показать» — должны отобразиться доступность и цены из ClickHouse.

