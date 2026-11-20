#!/bin/sh
# The AI voices dry run (docs/ai-voices-build-plan.md, phase 1c; checklist: docs/ai-voices-dry-run.md): the whole loop
# on a dev server of its own, with synthetic voices and no singer's audio. Its own database (stackapella_aidry), its
# own port, and a worker token kept only in ~/.config/stackapella/ai-worker-dev.env (chmod 600, never in the repo, never
# the production one), so it never touches the shared dev databases or another agent's server.
#
#   sh server/tool/ai-dry-run.sh setup   a fresh database: migrated, the demo seeded, its Silent Night AI guides taken
#                                        away (so its parts are the runner's to-do), three synthetic voices added and
#                                        consented, and the token file made if missing. Prints the voices' ids.
#   sh server/tool/ai-dry-run.sh start   the server on 127.0.0.1:8361 and a queue worker (long, ai, default)
#   sh server/tool/ai-dry-run.sh stop    stops exactly what start started (its own pids, never by pattern)
#   sh server/tool/ai-dry-run.sh on      ai_voices on in this database only, to hear approved guides in the app
set -eu
cd "$(dirname "$0")/.."
DB=stackapella_aidry
PORT=8361
ENVF="$HOME/.config/stackapella/ai-worker-dev.env"
PIDS="$HOME/.config/stackapella/ai-dry-run.pids"

token() {
    if [ ! -f "$ENVF" ]; then
        umask 077
        printf 'AI_WORKER_URL=http://127.0.0.1:%s\nAI_WORKER_TOKEN=%s\n' "$PORT" "$(openssl rand -hex 32)" > "$ENVF"
        chmod 600 "$ENVF"
        echo "Made $ENVF (chmod 600)."
    fi
    AI_WORKER_TOKEN=$(sed -n 's/^AI_WORKER_TOKEN=//p' "$ENVF")
}

# This database, this port, this token: never the shared ones in server/.env.
env_here() {
    token
    export DB_DATABASE="$DB" APP_URL="http://127.0.0.1:$PORT" MEDIA_URL="http://127.0.0.1:$PORT/media" AI_WORKER_TOKEN AI_GUIDES_QUEUE=ai
}

case "${1:-}" in
setup)
    env_here
    php -r '$p = new PDO("mysql:host=".(getenv("DB_HOST") ?: "127.0.0.1").";port=".(getenv("DB_PORT") ?: "3307"), getenv("DB_USERNAME") ?: "root", getenv("DB_PASSWORD") ?: ""); $p->exec("CREATE DATABASE IF NOT EXISTS stackapella_aidry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");'
    # Never a shared database: migrate:fresh empties whatever it's pointed at.
    [ "$(php artisan tinker --execute='echo DB::connection()->getDatabaseName();')" = "$DB" ] || { echo "Not pointed at $DB: stopping."; exit 1; }
    php artisan migrate:fresh --force
    php artisan db:seed --class=DemoSeeder --force
    php artisan tinker --execute='
        foreach (App\Models\TemplateGuide::withAi()->where("source", "ai")->get() as $g) { Illuminate\Support\Facades\Storage::disk("media")->delete($g->media_key); App\Models\TemplateGuide::withAi()->whereKey($g->id)->delete(); }
        foreach (["Synthetic High", "Synthetic Mid", "Synthetic Low"] as $name) {
            $v = App\Models\AiVoice::query()->firstOrCreate(["name" => $name]);
            $v->forceFill(["consent_date" => now()->toDateString(), "consent_version" => "dry-run", "credited" => false])->save();
            echo $name.": ".$v->sqid().PHP_EOL;
        }'
    echo "Ready: AI_WORKER_URL and AI_WORKER_TOKEN are in $ENVF. Next: sh server/tool/ai-dry-run.sh start"
    ;;
start)
    env_here
    [ ! -f "$PIDS" ] || { echo "Already started ($PIDS); stop it first."; exit 1; }
    if lsof -nP -iTCP:"$PORT" -sTCP:LISTEN > /dev/null 2>&1; then echo "Port $PORT is in use."; exit 1; fi
    # One process (no PHP_CLI_SERVER_WORKERS: its workers can outlive a stop).
    (exec php -S "127.0.0.1:$PORT" tool/serve.php > storage/logs/ai-dry-run-server.log 2>&1) &
    server=$!
    (exec php artisan queue:work --queue=long,ai,default --timeout=300 --tries=3 > storage/logs/ai-dry-run-queue.log 2>&1) &
    queue=$!
    umask 077
    printf '%s %s\n' "$server" "$queue" > "$PIDS"
    echo "Server on http://127.0.0.1:$PORT (pid $server), queue worker (pid $queue). Logs: server/storage/logs/ai-dry-run-*.log"
    ;;
stop)
    [ -f "$PIDS" ] || { echo "Not started."; exit 0; }
    for pid in $(cat "$PIDS"); do kill "$pid" 2> /dev/null || true; done
    rm -f "$PIDS"
    echo "Stopped."
    ;;
on)
    env_here
    php artisan tinker --execute='app(App\Support\Flags::class)->set("ai_voices", "on", null, "AI voices dry run, dev only");'
    echo "ai_voices is on in $DB only."
    ;;
*)
    echo "Usage: sh server/tool/ai-dry-run.sh setup|start|stop|on"
    exit 1
    ;;
esac
