"""Game mode's server features end to end, against the real server (API=http://127.0.0.1:8000
tool/flows.sh game_integration): a new adult account in game mode finds today's challenge on Home
(the server's own pick) with yesterday's, taken, stamped from the server's `days`, opens the day's
board and takes the challenge into Sing, where the ghost of
its best take is asked for (none yet: a quiet empty state, no console error); then chooses initials
in Settings and shows them on the boards. Any console error but sign-in's own fails it, so a 500 on
the day's board or a 404 for a ghost not kept yet shows here before it reaches the live site.
"""
import json
import subprocess
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from accounts_integration import LOG, PASSWORD, sign_in  # noqa: E402

SERVER = LOG.parents[2]
TAG = "document.querySelector('[data-game-daily]')"
SHEET = "document.querySelector('[data-game-daily-sheet]')"
ROW = "document.querySelector('[data-game-initials-row]')"


def journey(f):
    if f.skin != "game":
        return  # serious mode makes none of these calls (the game flows check it against the mock)
    f.expected_errors += ["401 (Unauthorized) http://127.0.0.1", "419 (Unknown Status Code) http://127.0.0.1"]  # signed out, then a first CSRF token
    email = f"game-{f.size}-{int(time.time())}@example.com"
    make = f'$u = App\\Models\\User::factory()->create(["email" => "{email}", "password" => bcrypt("{PASSWORD}"), "birth_month" => 5, "birth_year" => 1990, "adult_confirmed_at" => now()]); echo $u->id;'  # an adult, as sign-up makes one
    made = subprocess.run(["php", "artisan", "tinker", "--execute", make], cwd=SERVER, check=True, capture_output=True, text=True)
    user_id = int(made.stdout.strip().splitlines()[-1])
    yesterday = time.strftime("%Y-%m-%d", time.gmtime(time.time() - 86400))
    # The server counts a day only while the part score behind it is on the part board: both.
    seed = (f'$d = App\\Models\\DailyScore::factory()->create(["user_id" => {user_id}, "date" => "{yesterday}"]); '
            f'App\\Models\\PartScore::factory()->create(["user_id" => {user_id}, "template_id" => $d->template_id, "template_version_id" => $d->template_version_id, "part_key" => $d->part_key, "scoring_version" => $d->scoring_version]);')
    subprocess.run(["php", "artisan", "tinker", "--execute", seed], cwd=SERVER, check=True, capture_output=True)
    sign_in(f, email, PASSWORD)
    f.wait(f"(await import('/js/stores/account.js')).account.me?.email === {json.dumps(email)}", timeout=20, what="signed in")
    f.mic_checked()

    # Today's challenge: the server's pick, on its song's card.
    f.go("/?api=real", "document.querySelector('#app header')")
    f.wait(TAG, timeout=20, what="today's challenge on its card")
    pick = f.js("JSON.parse(JSON.stringify((await import('/js/game/daily.js')).daily.today))")
    assert pick["date"] == f.js("new Date().toISOString().slice(0, 10)"), f"today's date: {pick['date']}"
    # Yesterday's challenge, taken (a score on its board): the server's `days` stamps it.
    assert yesterday in pick["days"], f"the server's days: {pick['days']}"
    assert f.js(f"(await import('/js/game/daily.js')).dailyDays.has({json.dumps(yesterday)})"), "stamped from the server"
    f.run(f"{TAG}.closest('[data-song-card]').scrollIntoView({{ block: 'center' }})")
    f.shot("home")
    f.run(f"{TAG}.click()")
    f.wait(f"{SHEET} && !document.querySelector('[data-daily-take]').disabled", timeout=20, what="the day's board, its song loaded")
    f.shot("board")

    # Take the challenge: into Sing on the day's part, where the ghost is asked for.
    f.run("document.querySelector('[data-daily-take]').click()")
    f.wait("location.pathname.endsWith('/sing')", timeout=20, what="into the challenge")
    f.run("""const s = await import('/js/stores/session.js'); s.session.practice.sing = { ...(s.session.practice.sing ?? {}), way: 'whole', fitSaid: true, keyChosen: true }; s.savePractice();""")
    f.go(f"{f.js('location.pathname')}?api=real", "document.querySelector('#app header')")
    f.wait("performance.getEntriesByType('resource').some((r) => /\\/api\\/boards\\/[^/]+\\/[^/]+\\/ghost/.test(r.name))", timeout=20, what="the ghost asked for")
    time.sleep(1.0)
    f.shot("sing")

    # Initials on the boards, saved to the account.
    f.go("/settings?api=real", "document.querySelector('#game-heading')")
    f.wait(ROW, timeout=15, what="the initials rows")
    f.run(f"{ROW}.querySelector('[role=switch]').click()")
    f.wait("document.querySelector('[data-game-initials]')", what="the letter picker")
    for i, letter in enumerate("ADA"):
        f.run(f"const s = document.querySelectorAll('[data-game-initials] input')[{i}]; s.value = {json.dumps(letter)}; s.dispatchEvent(new Event('input'));")
    f.run("document.querySelector('[data-initials-save]').click()")
    f.wait("!document.querySelector('[data-game-initials]')", timeout=15, what="saved")
    me = f.js("JSON.parse(JSON.stringify((await import('/js/stores/account.js')).account.me))")
    assert me["initials"] == "ADA" and me["boardsName"] == "initials", f"kept: {me.get('initials')} {me.get('boardsName')}"
    f.shot("initials")
    assert not [e for e in f.chrome.errors if not any(x in e for x in f.expected_errors)], f"console errors: {f.chrome.errors[-3:]}"


if __name__ == "__main__":
    run("game_integration", journey)
