"""The installed app offline, then back (the coordinator's check, 2026-11-02), against the real server:
signed in, a song opened once online (the service worker in charge, the project up in the account);
then the connection gone, as on a train: still signed in (the account kept on the device), the song opens in Sing and a lesson opens, a take is recorded
and kept on the device, waiting to go up; then the connection back, and the take reaches the account.

It runs its own static server on a free port (forwarding /api to API), so stopping it cuts off the page
and the service worker alike, as offline.py does. Needs the API server (jobs run inline or a worker):

    API=http://127.0.0.1:8371 FLOW_SERVER=~/Code/stackapella-frontend/server python3 -I tests/flows/offline_sync_integration.py
"""
import json
import os
import socket
import subprocess
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402
from accounts_integration import LOG, PASSWORD, sign_in  # noqa: E402
from help_shots import AUDIO, SESSION, recording_starts, t  # noqa: E402

WEB = Path(__file__).resolve().parents[2]
SERVER = LOG.parents[2]
API = os.environ.get("API", "http://127.0.0.1:8000")
PROJECTS = "(await import('/js/stores/projects.js'))"
CLOUD = "(await import('/js/stores/cloud.js'))"
DB = "(await import('/js/db.js'))"
GONE = ("ERR_CONNECTION_REFUSED", "Failed to fetch", "NetworkError", "net::ERR", "Load failed", "status of 503", "status of 504", "401 (Unauthorized)", "419 (")


def free_port():
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def serve(port):
    return subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "tool/serve.php"], cwd=WEB, env={**os.environ, "API": API},
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def main():
    port = free_port()
    server = serve(port)
    time.sleep(0.8)
    f = flow.Flow(f"http://127.0.0.1:{port}", "phone", "offline_sync")
    f.expected_errors += list(GONE)
    failed = None
    try:
        email = f"offline-{int(time.time())}@example.com"
        make = f'$u = App\\Models\\User::factory()->create(["email" => "{email}", "password" => bcrypt("{PASSWORD}")]); echo $u->id;'
        subprocess.run(["php", "artisan", "tinker", "--execute", make], cwd=SERVER, check=True, capture_output=True)
        sign_in(f, email, PASSWORD)
        f.wait(f"(await import('/js/stores/account.js')).account.me?.email === {json.dumps(email)}", timeout=20, what="signed in")
        f.wait("navigator.serviceWorker.controller", timeout=40, what="the service worker in charge")

        # Online once: a song started and opened in Sing (its modules and guides seen), and a lesson; the project goes up.
        song = f.js("""(async () => { const { startFromTemplate } = await import('/js/stores/projects.js'); const { loadTemplate } = await import('/js/templates.js');
            return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id; })()""")
        f.go(f"/p/{song}/sing?api=real", "document.querySelector('#app header')")
        f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
        f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
            s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); await s.savePractice();""")
        f.go("/learn/1-comfortable?api=real", "document.querySelector('#app h1')")
        f.run(f"await {CLOUD}.syncNow()")
        f.wait(f"{CLOUD}.cloud.pending === 0", timeout=60, what="the project up in the account")
        f.mic_checked()
        f.run("sessionStorage.setItem('stackapella.headphones', '1')")

        # The connection gone.
        server.terminate()
        server.wait(timeout=5)
        f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
        f.wait(f"{SESSION}.session.parts.length > 3", timeout=30, what="the song in Sing, offline")
        f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
            s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); await s.savePractice();""")
        f.wait("!document.querySelector('[data-way], [data-game-select]')", what="past the chooser")
        # Still signed in offline: the account kept on the device (Hillel, "keep it on the device").
        assert f.js(f"(await import('/js/stores/account.js')).account.me?.email") == email, "signed out while offline"
        f.shot("sing-offline")
        f.go("/learn/1-comfortable", "document.querySelector('#app h1')")
        f.shot("lesson-offline")

        # A take, recorded and kept offline (over loopback).
        f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
        f.wait(f"{SESSION}.session.parts.length > 3 && !{SESSION}.session.preparing", timeout=30, what="Sing ready offline")
        f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
            s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); await s.savePractice();""")
        f.wait("!document.querySelector('[data-way], [data-game-select]')", what="past the chooser")
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        f.click(t(f, "sing.recordPart", {"name": t(f, "parts.alto")}))
        recording_starts(f)
        f.wait(f"{AUDIO}.engine.positionInfo()?.position > 4", timeout=30, what="a few seconds recorded")
        f.click_t("sing.recordingStage.stopKeep")
        f.wait("document.querySelector('[data-take-review]')", timeout=30, what="the take kept")
        f.js(f"{AUDIO}.engine.setLoopback(false)")
        alto = f.js(f"(async () => {{ const p = (await {DB}.all('parts')).find((x) => x.projectId === {json.dumps(song)} && x.templatePartKey === 'alto'); return p ? {{ id: p.id, takeId: p.takeId, syncedTakeId: p.syncedTakeId ?? null, frames: p.frames }} : null; }})()")
        assert alto and alto["takeId"] and alto["frames"] > 0, f"the take on the device: {alto}"
        assert alto["syncedTakeId"] != alto["takeId"], "the take marked as in the account while offline"
        assert f.js(f"(await {DB}.getAudio({json.dumps(alto['id'])}))?.length > 0"), "the take's audio on the device"
        f.shot("take-offline")

        # Back online: the take goes up.
        server = serve(port)
        time.sleep(0.8)
        f.run("window.dispatchEvent(new Event('online'))")
        f.run(f"await {CLOUD}.syncNow()")
        try:
            f.wait(f"(async () => {{ const p = (await {DB}.all('parts')).find((x) => x.id === {json.dumps(alto['id'])}); return p?.syncedTakeId === {json.dumps(alto['takeId'])}; }})()", timeout=150, what="the offline take in the account")
        except AssertionError:
            print("     state:", f.js(f"""(async () => {{ const p = (await {DB}.all('parts')).find((x) => x.id === {json.dumps(alto['id'])});
                const c = {CLOUD}.cloud; return {{ scoring: p.scoring ? Date.now() - p.scoring : null, takeWaiting: p.takeWaiting ?? null, version: p.version ?? null, synced: p.syncedTakeId ?? null,
                    cloud: {{ pending: c.pending, waiting: c.waiting, offline: c.offline }}, outbox: (await {DB}.all('outbox')).map((e) => [e.kind, e.targetId?.slice(0, 8), e.tries ?? null, e.error ?? null]) }}; }})()"""))
            raise
        f.wait(f"{CLOUD}.cloud.pending === 0", timeout=60, what="nothing left to send")
        print("ok   offline_sync_integration (phone)")
    except Exception as error:  # noqa: BLE001 (reported, then the run fails)
        failed = error
        try:
            f.shot("failed")
        except Exception:  # noqa: BLE001
            pass
        print(f"FAIL offline_sync_integration (phone): {error}")
        for message in f.chrome.errors[-5:]:
            print(f"     page error: {message[:300]}")
    finally:
        server.terminate()
        f.chrome.errors.clear()
        f.chrome.close()
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()
