# Деплой Evoroom на домен evo-room.com
#
# DNS (уже должны указывать на IP VDS):
#   A     evo-room.com           → IP
#   A     www.evo-room.com       → IP   (опционально)
#   A     livekit.evo-room.com   → IP   (обязательно для видео)
#
# Порты firewall / security group:
#   80/tcp, 443/tcp
#   7881/tcp
#   50000-50100/udp

set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> git pull"
git pull

echo "==> npm install"
npm install

echo "==> build web"
npm run build:web

echo "==> infra (LiveKit + Caddy + …)"
docker compose -f infra/docker-compose.yml up -d

echo ""
echo "Готово. Дальше вручную:"
echo "  1. В apps/api/.env выставьте:"
echo "       APP_URL=https://evo-room.com"
echo "       LIVEKIT_PUBLIC_URL=wss://livekit.evo-room.com"
echo "       LIVEKIT_API_URL=http://127.0.0.1:7880"
echo "  2. Перезапустите API (npm run dev:api или systemd)."
echo "  3. Откройте https://evo-room.com"
echo ""
echo "Проверка DNS: dig +short evo-room.com livekit.evo-room.com"
echo "Логи Caddy:   docker logs evoroom-caddy --tail 50"
