# Регистрация Debezium MySQL коннектора в Kafka Connect.
# Запускать после старта стека: docker compose up -d && sleep 30 && ./docker/debezium/register-connector.sh

set -e
CONNECT_URL="${KAFKA_CONNECT_URL:-http://localhost:8083}"
CONNECTOR_JSON="$(dirname "$0")/connector-myself.json"

echo "Kafka Connect: $CONNECT_URL"
echo "Registering connector from $CONNECTOR_JSON"

curl -s -X POST -H "Content-Type: application/json" \
  --data @"$CONNECTOR_JSON" \
  "$CONNECT_URL/connectors" | jq .

echo ""
echo "Connector status:"
curl -s "$CONNECT_URL/connectors/myself-mysql-connector/status" | jq .
