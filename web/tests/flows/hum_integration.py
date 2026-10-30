"""Hum a tune end to end, against the real server (API=http://127.0.0.1:8000 tool/flows.sh
hum_integration): a new, verified account hums two lines of Amazing Grace (the arranger's fixture,
played into the mic over loopback), the app writes the notes with a tempo, bars and key, suggests the
parts, saves the song to the account, and opens it in Sing and the studio. Share as an idea is tried
when the server's `ideas` flag is open, and its absence checked when it isn't.
"""
import json
import subprocess
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from accounts_integration import LOG, PASSWORD, sign_in, t  # noqa: E402

SERVER = LOG.parents[2]
AUDIO = "(await import('/js/audio.js'))"
EXPECTED = ["401 (Unauthorized) http://127.0.0.1", "404 (Not Found) http://127.0.0.1"]
HUM = """const { engine } = (await import('/js/audio.js')); const bytes = await (await fetch('/tests/fixtures/hum-two-lines.wav')).arrayBuffer();
    const decoded = await engine.ctx.decodeAudioData(bytes); engine.preview(decoded.getChannelData(0), decoded.sampleRate);"""


def journey(f):
    f.expected_errors += EXPECTED
    email = f"hum-{f.size}-{f.skin}-{int(time.time())}@example.com"
    make = f'$u = App\\Models\\User::factory()->create(["email" => "{email}", "password" => bcrypt("{PASSWORD}"), "birth_month" => 5, "birth_year" => 1990]); echo $u->id;'
    subprocess.run(["php", "artisan", "tinker", "--execute", make], cwd=SERVER, check=True, capture_output=True)
    sign_in(f, email, PASSWORD)
    f.wait(f"(await import('/js/stores/account.js')).account.me?.email === {json.dumps(email)}", timeout=20, what="signed in")
    f.mic_checked()

    # Hum it: New song's Hum a tune once it's switched on for everyone; until then, its own address.
    f.go("/create?mode=hum&api=real", "document.querySelector('[data-hum-tune]')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("document.querySelector('[data-hum-record]').click()")
    f.wait("document.querySelector('[data-hum-tune]')?.dataset.state === 'recording'", what="recording")
    f.run(HUM)
    f.js("new Promise((r) => setTimeout(r, 20500))")
    f.run("document.querySelector('[data-hum-stop]').click()")
    f.wait("document.querySelector('[data-hum-found]')", timeout=60, what="the tune written down")
    found = f.js("document.querySelector('[data-hum-found]').textContent")
    print(f"    [{f.size}, {f.skin}] found: {found}")
    f.shot("found")

    # The notes, then the parts suggested.
    f.click(t(f, "create.frame.next", {"step": t(f, "create.step.edit")}), "button")
    f.wait("!document.querySelector('[data-hum-tune]')", what="the note editor")
    f.click(t(f, "create.frame.next", {"step": t(f, "create.step.parts")}), "button")
    f.click_t("create.parts.suggest")
    f.wait_text(t(f, "create.parts.chordsTitle"))
    f.wait("(async () => (await (await import('/js/stores/templates.js')).loadDraft(location.pathname.split('/').pop())).template.parts.length === 4)()", timeout=20, what="four parts")
    f.shot("harmonised")

    # Saved to the account.
    f.click(t(f, "create.frame.next", {"step": ""}).strip(), "button")
    f.wait_text(t(f, "create.save.title"))
    f.type("form input", "My Hummed Tune")
    f.click(t(f, "create.save.mine"), "label")
    f.click_t("create.save.save")
    f.wait("location.pathname.startsWith('/t/')", timeout=30, what="the song's page")
    template_id = f.js("location.pathname.split('/')[2]")
    f.wait(f"(await (await import('/js/stores/templates.js')).ownTemplate({json.dumps(template_id)})) && (await import('/js/stores/templates.js')).mine.list.find((r) => r.id === {json.dumps(template_id)})?.state === 'saved'", timeout=60, what="saved to the account")
    f.shot("saved")

    # Ideas: shared when the flag is open here, and offered nowhere when it isn't.
    f.wait("(await import('/js/stores/ideas.js')).ideas.open !== null", timeout=20, what="Ideas asked about")
    if f.js("(await import('/js/stores/ideas.js')).ideas.open"):
        f.run("document.querySelector('[data-share-idea-open]').click()")
        f.run("document.querySelector('[data-idea-rights]').click()")
        f.run("document.querySelector('[data-share-idea] button[type=submit]').click()")
        f.wait("/^\\/ideas\\/[\\w-]+$/.test(location.pathname)", timeout=30, what="the idea's page")
        f.shot("idea")
        f.go(f"/t/{template_id}?api=real", "document.querySelector('[data-hum-tune], main h1, h1')")
    else:
        print(f"    [{f.size}, {f.skin}] ideas closed here: Share as an idea not offered")
        assert not f.js("!!document.querySelector('[data-share-idea-card]')"), "no Share as an idea while it's closed"

    # Sung: Learn the part opens Sing on the new song, then the studio.
    f.click(f.js("document.querySelector('main button.btn-primary').textContent.trim()"), "main button")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", timeout=30, what="Sing, on the hummed song")
    project = f.js("location.pathname.split('/')[2]")
    f.go(f"/p/{project}?api=real", "document.querySelectorAll('canvas').length > 1")
    assert f.js("(await import('/js/stores/session.js')).session.parts.length") == 4, "four parts in the studio"
    f.shot("studio")


if __name__ == "__main__":
    run("hum_integration", journey, sizes=("phone", "desktop"))
