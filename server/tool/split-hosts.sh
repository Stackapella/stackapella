#!/bin/sh
# The site and the app on two hosts, as in production: a real server on PORT (8476 by default) whose site is
# http://localhost:PORT and whose app is http://127.0.0.1:PORT, then web/tool/split_hosts.py against it (no console
# errors, every picture shown). Stops its own server, whatever happens. Uses the dev database and the linked content.
#   sh server/tool/split-hosts.sh
set -u
cd "$(dirname "$0")/.."
PORT=${PORT:-8476}
if lsof -nP -iTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
    echo "Port $PORT is in use: set PORT to a free one." >&2
    exit 1
fi
APP_URL="http://127.0.0.1:$PORT" PUBLIC_URL="http://localhost:$PORT" PHP_CLI_SERVER_WORKERS=4 \
    php -S "127.0.0.1:$PORT" tool/serve.php >/dev/null 2>&1 &
SERVER=$!
stop() {
    kill "$SERVER" 2>/dev/null
    # The built-in server's workers can outlive their parent: only those on our port.
    for pid in $(lsof -nP -t -iTCP:"$PORT" -sTCP:LISTEN 2>/dev/null); do kill "$pid" 2>/dev/null; done
}
trap stop EXIT INT TERM
i=0
until curl -s -o /dev/null "http://127.0.0.1:$PORT/up"; do
    i=$((i + 1))
    [ $i -gt 60 ] && { echo "The server on $PORT didn't start." >&2; exit 1; }
    sleep 0.5
done
python3 -I ../web/tool/split_hosts.py --site "http://localhost:$PORT" --app "http://127.0.0.1:$PORT"
