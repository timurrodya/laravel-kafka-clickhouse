# Docker: Laravel + Debezium + Kafka + ClickHouse

Инфраструктура для потока данных **CDC (Debezium) → Kafka → ClickHouse** с материализованными представлениями и ReplacingMergeTree.

## Поток данных

1. **CDC**: Debezium в Kafka Connect следит за таблицами `availability`, `prices_by_day` и `placement_variants` в БД **myself** (MySQL) и шлёт каждое изменение в топики Kafka.
2. **SMT**: В коннекторе включён **ExtractNewRecordState** — в топике остаётся только «плоский» объект `after`.
3. **ClickHouse**: Таблицы с движком **Kafka** читают эти топики.
4. **Обработка**: **MATERIALIZED VIEW** по каждой новой строке пишут в финальные таблицы.
5. **Хранение**: Финальные таблицы на **ReplacingMergeTree** по (placement_id, date); справочник вариантов поиска — **placement_variants** (placement_id, adults, children_ages). Таблица **search_by_variant** хранит уже размноженные по вариантам строки (placement_id, date, adults, children_ages, available, price, currency); заполняется автоматически через Materialized View при вставке в финальные таблицы. **Поиск** читает из неё простым `SELECT ... WHERE` без JOIN.

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

Поднимутся: MySQL, Zookeeper, Kafka, **connect-setup** (создаёт топики `connect-offsets`, `connect-configs`, `connect-status` с `cleanup.policy=compact`), Kafka Connect, **connect-register** (регистрирует коннектор Debezium), ClickHouse и контейнер инициализации ClickHouse (`clickhouse-init`). Коннектор регистрируется автоматически при первом запуске; при следующих запусках контейнер `connect-register` завершится с «Connector already exists».

**Если Kafka Connect уже создавал топики ранее** (до появления сервиса `connect-setup`) и падает с ошибкой про `cleanup.policy=compact`, один раз выполните и перезапустите Connect:
```bash
docker exec laravel-kafka-broker kafka-configs --bootstrap-server localhost:9092 --alter --entity-type topics --entity-name connect-offsets --add-config cleanup.policy=compact
docker exec laravel-kafka-broker kafka-configs --bootstrap-server localhost:9092 --alter --entity-type topics --entity-name connect-configs --add-config cleanup.policy=compact
docker compose restart kafka-connect
```

### 2. Регистрация коннектора Debezium (опционально)

Коннектор регистрируется автоматически сервисом **connect-register** после старта Kafka Connect. Если нужно зарегистрировать или обновить вручную:

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

Ожидаемо в статусе: `"state": "RUNNING"`. Если снова 404 — регистрация не прошла (проверьте логи: `docker compose logs kafka-connect`). Если при запросе `/connectors/myself-mysql-connector` приходит **500 Request timed out** — коннектор часто занят первичным снимком (snapshot) и не успевает ответить за 90 с; в `docker-compose` для Connect задан таймаут 5 мин (`CONNECT_REST_REQUEST_TIMEOUT_MS`). Подождите завершения snapshot или посмотрите логи.

**Пересоздание коннектора для полного снимка (re-snapshot)**  
Если коннектор уже работал (snapshot выполнен по пустым или старым данным), а вы заново заполнили MySQL (например, сидером), Debezium по умолчанию не делает повторный снимок. Чтобы заново отправить все строки из MySQL в Kafka и ClickHouse:

1. **Удалить коннектор** (Kafka Connect забудет его, но топик с офсетами останется):

   **PowerShell:**
   ```powershell
   Invoke-RestMethod -Uri http://localhost:8083/connectors/myself-mysql-connector -Method Delete
   ```

   **Bash:**
   ```bash
   curl -s -X DELETE http://localhost:8083/connectors/myself-mysql-connector
   ```

2. **Удалить топик с офсетами коннекторов** — тогда при новой регистрации коннектор не найдёт сохранённую позицию и выполнит полный snapshot (`snapshot.mode=initial`).  
   Внимание: удаляется топик **connect-offsets** — все зарегистрированные коннекторы потеряют сохранённую позицию (для демо-окружения обычно допустимо).

   ```bash
   docker compose exec kafka kafka-topics --bootstrap-server localhost:29092 --delete --topic connect-offsets
   ```

3. **Создать топик заново с политикой compact** (Kafka Connect требует `cleanup.policy=compact` для топика офсетов; иначе при перезапуске Connect падает с ConfigException):

   ```bash
   docker compose exec kafka kafka-topics --bootstrap-server localhost:29092 --create --topic connect-offsets --config cleanup.policy=compact --partitions 1 --replication-factor 1
   ```

4. **Перезапустить Kafka Connect**:

   ```bash
   docker compose restart kafka-connect
   ```

5. **Подождать 20–30 секунд**, затем снова зарегистрировать коннектор (шаг «Зарегистрировать коннектор» выше — PowerShell или curl). В конфиге уже указано `"snapshot.mode": "initial"` — коннектор сделает полный снимок таблиц `availability`, `prices_by_day`, `placement_variants`.

6. **Подождать 1–2 минуты** (snapshot может занять время), проверить статус и страницу «Статистика» в приложении — объёмы в ClickHouse должны вырасти. Затем при необходимости выполнить:
   ```bash
   docker compose exec laravel php artisan clickhouse:refresh-search-table
   ```

**Чтобы данные из Kafka попали в ClickHouse**, сделайте шаг 3 ниже (инициализация ClickHouse), затем подождите 10–30 секунд.

### 3. Инициализация ClickHouse (обязательно для потока данных)

Скрипты в `docker/clickhouse/init/` создают БД `analytics`, Kafka-таблицы, финальные таблицы и материализованные представления. Один раз после первого запуска можно выполнить вручную:

```bash
docker compose run --rm clickhouse-init
```

Либо выполнить SQL из папки `docker/clickhouse/init/` вручную в клиенте ClickHouse (порт 8123).

**Если финальные таблицы были созданы со старой схемой (hotel_id)** и в логах ошибка `There's no column 'a.placement_id'`, выполните один раз миграцию (скрипт выполняется внутри контейнера, без пайпа с хоста):

```bash
docker compose exec clickhouse sh -c "clickhouse-client -q --multiquery < /scripts/07_migrate_final_tables_to_placement_id.sql"
```

Для этого в `docker-compose.yml` в сервис `clickhouse` добавлен volume `./docker/clickhouse/init:/scripts:ro`. Если контейнер уже запущен без него, перезапустите: `docker compose up -d clickhouse`.

**Если таблица `analytics.placement_variants` в ClickHouse пустая** (например, добавили `placement_variants` в Debezium позже и snapshot уже прошёл), выполните разовую синхронизацию из MySQL:

```bash
docker compose exec laravel php artisan clickhouse:sync-placement-variants
```

**Таблица `search_by_variant`** заполняется автоматически при поступлении данных в финальные таблицы (через MV). Для первоначальной заливки или ручной пересборки после массовых изменений:

```bash
docker compose exec laravel php artisan clickhouse:refresh-search-table
```

### 4. Запуск с Laravel (веб-морда: отели, даты, данные из ClickHouse)

В проекте уже есть Laravel-приложение с таблицами **hotels**, **placements**, **placement_variants** и страницей выбора отеля и дат для просмотра доступности и цен из ClickHouse.

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

1. Выполните миграции и сидер (таблицы `hotels`, `placements`, `placement_variants`, демо-данные):

```bash
docker compose exec laravel php artisan migrate --force
docker compose exec laravel php artisan db:seed --force
```

Приложение: **[http://localhost:8080](http://localhost:8080)** (или `NGINX_PORT` из `.env`).

**API и Swagger:** данные по доступности и ценам можно отправлять через REST API. Документация и «Try it out»: [http://localhost:8080/api/docs](http://localhost:8080/api/docs).

- `POST /api/availability` — одна дата: `{ "placement_id": 1, "date": "2026-03-01", "available": true }`; диапазон: `{ "placement_id": 1, "date_from": "2026-03-01", "date_to": "2026-03-10", "available": true }`
- `POST /api/prices` — одна дата: `{ "placement_id": 1, "date": "2026-03-01", "price": 5500 }`; диапазон: `{ "placement_id": 1, "date_from": "2026-03-01", "date_to": "2026-03-10", "price": 6000, "currency": "RUB" }`
- `GET /api/search?placement_id=1&date_from=2026-03-01&date_to=2026-03-10&adults=2&children_ages[]=5&children_ages[]=10` — поиск по размещению и составу гостей (данные из `search_by_variant`, без JOIN)

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

При первом запуске MySQL скрипт `docker/mysql/init/01_schema_myself.sql` создаёт только базу **myself**. Таблицы создаются **миграциями Laravel**:

```bash
docker compose exec laravel php artisan migrate --force
```

Миграции создают:

- **hotels** — справочник отелей (id, name, address, city, timestamps)
- **placements** — размещения у отеля (id, hotel_id, name, timestamps)
- **placement_variants** — варианты поиска: взрослые + возрасты детей (placement_id, adults, children_ages)
- **availability** — доступность по размещению и дате (placement_id, date, available)
- **prices_by_day** — цены по размещению и дате (placement_id, date, price, currency)

Демо-данные (отель, размещение, варианты, доступность и цены на 2026-02-28 … 2026-03-08) — через сидер:

```bash
docker compose exec laravel php artisan db:seed --force
```

**Если MySQL уже был запущен ранее** (init не выполнится повторно): при необходимости создайте БД вручную (`CREATE DATABASE IF NOT EXISTS myself;`), затем выполните миграции и сидер (см. шаг 4 быстрого старта).

## Топики Kafka

После регистрации коннектора Debezium создаёт топики:

- `myself.myself.availability`
- `myself.myself.prices_by_day`

Формат сообщений — плоский JSON (только поля из `after`), без обёртки Debezium.

## ClickHouse

- **Kafka-таблицы**: `analytics.kafka_availability`, `analytics.kafka_prices_by_day`, `analytics.kafka_placement_variants`
- **Финальные таблицы**: `analytics.availability_final`, `analytics.prices_by_day_final` (по placement_id, date), `analytics.placement_variants` (справочник вариантов поиска)
- **Таблица поиска**: `analytics.search_by_variant` — размноженные по вариантам (placement_id, date, adults, children_ages, available, price, currency). **Обновляется только из MySQL через Kafka**: при любом изменении в таблицах availability или prices_by_day Debezium шлёт сообщение в топик → MV в ClickHouse (скрипт `09_search_by_variant_from_kafka.sql`) дописывают строки в search_by_variant. Консольная команда `clickhouse:refresh-search-table` не нужна для обычных обновлений — только для первичной заливки после сида или восстановления. Чтобы при первом сиде в search_by_variant было полное размножение (21 900 × 20), сначала выполните `clickhouse:sync-placement-variants` (чтобы в CH были все варианты), затем при необходимости один раз `clickhouse:refresh-search-table`.
- **Materialized views**: `analytics.mv_availability_to_final`, `analytics.mv_prices_to_final`, `analytics.mv_placement_variants_to_final`; для поиска (на финальных таблицах): `mv_availability_to_search_by_variant`, `mv_prices_to_search_by_variant`; для поиска (на Kafka-таблицах, надёжное обновление при новых сообщениях): `mv_kafka_availability_to_search_by_variant`, `mv_kafka_prices_to_search_by_variant`

Проверка данных:

```bash
curl -s "http://localhost:8123/?query=SELECT%20*%20FROM%20analytics.availability_final%20LIMIT%2010"
```

## Проверка работы пайплайна

1. Запустите стек: `docker compose --profile app up -d`.
2. Дождитесь готовности Kafka Connect, зарегистрируйте коннектор Debezium (см. выше).
3. Один раз выполните инициализацию ClickHouse: `docker compose run --rm clickhouse-init`.
4. Выполните миграции и сидер Laravel — таблицы создадутся в БД myself, демо-данные заполнятся. Если MySQL поднят впервые, init-скрипт уже создал только базу; если том MySQL был с другой схемой — миграции приведут схему к актуальному виду.
5. Убедитесь, что данные дошли до Kafka и ClickHouse.
6. Откройте [http://localhost:8080](http://localhost:8080), выберите отель и размещение «Стандарт двуместный», даты 2026-02-28 … 2026-03-08, нажмите «Показать» — отобразятся доступность и цены из ClickHouse.

