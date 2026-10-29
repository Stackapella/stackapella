"""M6's sharing, against the real server (API=http://127.0.0.1:8000 tool/flows.sh share_integration):
a new account records a part, Share sends the project and its mix (Make it sound good), the server
makes the link, and the link's page plays it; then Share as a video renders an MP4. Needs the queue
worker (the take, the harmony and the video are jobs) and ffmpeg on the server.
"""
import json
import subprocess
import sys
import time
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from accounts_integration import LOG, PASSWORD, sign_in, t  # noqa: E402

SERVER = LOG.parents[2]


def journey(f):
    f.expected_errors += ["401 (Unauthorized) http://127.0.0.1", "419 (Unknown Status Code) http://127.0.0.1", "404 (Not Found) http://127.0.0.1", "409 (Conflict) http://127.0.0.1"]  # 409: the take still processing, waited for
    email = f"share-{int(time.time())}@example.com"
    make = f'$u = App\\Models\\User::factory()->create(["email" => "{email}", "password" => bcrypt("{PASSWORD}")]); echo $u->id;'
    subprocess.run(["php", "artisan", "tinker", "--execute", make], cwd=SERVER, check=True, capture_output=True)
    sign_in(f, email, PASSWORD)
    f.wait(f"(await import('/js/stores/account.js')).account.me?.email === {json.dumps(email)}", timeout=20, what="signed in")
    project_id = f.js("""(async () => {
        const store = await import('/js/stores/projects.js');
        const { peaks } = await import('/js/dsp.js');
        const project = await store.createProject({ title: 'Share flow', bpm: 100 });
        const samples = new Float32Array(48000 * 4).map((_, i) => 0.3 * Math.sin((2 * Math.PI * 220 * i) / 48000));
        await store.savePart({ id: crypto.randomUUID(), projectId: project.id, name: 'Lead', color: '#f2a06b', order: 0, volume: 1, pan: 0, muted: false, solo: false, createdAt: Date.now(),
            offsetSeconds: 0, nudgeSeconds: 0, sampleRate: 48000, frames: samples.length, framesPerPeak: 512, peaks: peaks(samples, 512) }, samples);
        return project.id;
    })()""")
    f.go(f"/p/{project_id}?api=real", "document.querySelector('#app')")
    f.wait("(await import('/js/stores/session.js')).session.parts.length === 1 && document.querySelector('header')", what="the studio")
    f.js("(await import('/js/audio.js')).unlock()")
    f.click_t("share.title")
    f.wait_text(t(f, "share.who"))
    f.shot("share-sheet")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait_text(t(f, "share.done"), timeout=180)
    url = f.js("document.querySelector('[role=dialog] input[readonly]').value")
    f.shot("shared")
    assert f.js(f"document.querySelector('#share-guides')?.innerText.includes({json.dumps(t(f, 'share.guides'))})"), "featuring says its voices may become guides"
    page = urllib.request.urlopen(url.replace("https://", "http://").replace(url.split('/h/')[0], "http://127.0.0.1:8000")).read().decode()
    assert "<audio" in page and "Share flow" in page, "the link's page plays it"
    f.click_t("share.video.make")
    f.wait(f"document.body.innerText.includes({json.dumps(t(f, 'share.video.download'))}) || document.body.innerText.includes({json.dumps(t(f, 'share.video.failed'))})", timeout=300, what="the video")
    f.shot("video")
    assert f.js(f"document.body.innerText.includes({json.dumps(t(f, 'share.video.download'))})"), "the video was made"


if __name__ == "__main__":
    run("share_integration", journey, sizes=("desktop",))
