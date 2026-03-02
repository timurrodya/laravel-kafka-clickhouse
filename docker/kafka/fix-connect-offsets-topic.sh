#!/bin/sh
# Исправить топики Kafka Connect: для offset и config требуется cleanup.policy=compact.
# Запускать один раз, если Connect падает с ConfigException про connect-offsets.
# Из хоста: docker compose exec kafka /bin/sh /path/to/fix-connect-offsets-topic.sh
# Или: docker exec laravel-kafka-broker kafka-configs --bootstrap-server localhost:9092 --alter --entity-type topics --entity-name connect-offsets --add-config cleanup.policy=compact

set -e
BOOTSTRAP="${KAFKA_BOOTSTRAP:-localhost:9092}"

for topic in connect-offsets connect-configs; do
  echo "Altering topic $topic to cleanup.policy=compact ..."
  kafka-configs --bootstrap-server "$BOOTSTRAP" --alter --entity-type topics --entity-name "$topic" --add-config cleanup.policy=compact
done

echo "Done. Restart Kafka Connect: docker compose restart kafka-connect"
