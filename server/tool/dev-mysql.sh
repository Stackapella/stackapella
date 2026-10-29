#!/bin/sh
# Starts the dev database after a restart: the system MySQL's mysqld on its own data folder, on
# 127.0.0.1:3307 (server/.env's DB_PORT), separate from the system MySQL on 3306. Does nothing
# when it's already running. `sh server/tool/dev-mysql.sh`
set -u
D="$HOME/Library/Application Support/stackapella-mysql"
pid=$(cat "$D/mysqld.pid" 2> /dev/null)
if [ -n "$pid" ] && kill -0 "$pid" 2> /dev/null; then
    echo "The dev database is already running (pid $pid)."
    exit 0
fi
rm -f "$D/mysql.sock.lock" "$D/mysql.sock" "$D/mysqld.pid"
nohup /usr/local/mysql/bin/mysqld --no-defaults --datadir="$D/data" --port=3307 --bind-address=127.0.0.1 \
    --socket="$D/mysql.sock" --pid-file="$D/mysqld.pid" --log-error="$D/error.log" --mysqlx=OFF > /dev/null 2>&1 &
for _ in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do
    nc -z 127.0.0.1 3307 2> /dev/null && { echo "The dev database is up on 127.0.0.1:3307."; exit 0; }
    sleep 1
done
echo "The dev database didn't start: see $D/error.log" >&2
exit 1
