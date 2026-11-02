"""Shared by run.py and probe.py: serves web/ on a free port, opens headless Chrome on it, and runs
review.js there (its imports pointed at that server), so the arranger is judged in the same browser
code the app runs. Songs come from the linked content (`sh server/tool/content.sh link` first):
web/templates (the free songs) and server/database/templates (the library)."""
import json
import socket
import subprocess
import sys
import time
from contextlib import contextmanager
from pathlib import Path

WEB = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(WEB / "tests" / "flows"))
from flow import Flow  # noqa: E402


def songs():
    """Every free and library song with parts, in name order."""
    paths = [*(WEB / "templates").glob("*.json"), *(WEB.parent / "server" / "database" / "templates").glob("*.json")]
    out = [json.loads(p.read_text()) for p in sorted(paths, key=lambda p: p.name)]
    return [s for s in out if isinstance(s, dict) and s.get("parts")]


def song(name):
    return next(s for s in songs() if s.get("id") == name)


@contextmanager
def review():
    """Yields run(js): evaluates `js` (an async function body with `m`, review.js's exports) in Chrome."""
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        port = s.getsockname()[1]
    server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "tool/serve.php"], cwd=WEB, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        time.sleep(0.5)
        base = f"http://127.0.0.1:{port}"
        src = (Path(__file__).parent / "review.js").read_text().replace("'/js/", f"'{base}/js/")
        flow = Flow(base, "desktop", "harmony-review")
        flow.go("/")

        def run(body):
            return flow.js(f"""(async () => {{ const m = await import(URL.createObjectURL(new Blob([{json.dumps(src)}], {{ type: 'text/javascript' }})));
  const tunes = (await (await fetch('/tests/fixtures/hum-tunes.json')).json()).tunes;
  {body} }})()""")

        try:
            yield run
        finally:
            flow.chrome.close()
    finally:
        server.terminate()
        server.wait()
