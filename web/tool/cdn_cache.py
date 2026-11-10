"""The CDN libraries the app pins (Vue, lamejs, the QR code), kept on this machine for the flow harness, so a run never
waits on jsDelivr (the coordinator, 2026-11-02: ERR_TIMED_OUT in 3 of 4 gate runs under parallel Chromes). Each pinned
URL and its integrity hash come from our own sources (index.html's import map, js/mp3.js, js/qr.js); a file is
downloaded once into web/tool/cache/ (gitignored), kept only if its bytes match that hash, and handed to the page as
the CDN's answer (tool/cdp.py, Fetch interception). The page still checks the hash itself (SRI), so nothing about the
app or its CSP changes. FLOW_CDN_CACHE=0 turns it off.
"""
import base64
import hashlib
import os
import re
import urllib.request
from pathlib import Path

WEB = Path(__file__).resolve().parent.parent
CACHE = WEB / "tool" / "cache"
ON = os.environ.get("FLOW_CDN_CACHE", "1") != "0"


def pinned():
    """{ url: integrity } for every CDN file the app loads with a hash."""
    found = {}
    html = (WEB / "index.html").read_text(encoding="utf-8")
    found.update(dict(re.findall(r'"(https://cdn\.jsdelivr\.net/[^"]+)":\s*"(sha(?:256|384|512)-[^"]+)"', html)))
    for path in (WEB / "js").glob("*.js"):
        text = path.read_text(encoding="utf-8")
        for name, url in re.findall(r"const (\w+) = '(https://cdn\.jsdelivr\.net/[^']+)'", text):
            sri = re.search(rf"const {name}_SRI = '(sha(?:256|384|512)-[^']+)'", text)
            if sri:
                found[url] = sri.group(1)
    return found


def matches(body, integrity):
    algorithm, expected = integrity.split("-", 1)
    return base64.b64encode(hashlib.new(algorithm, body).digest()).decode() == expected


def body_for(url, integrity):
    """The file's bytes from the cache (downloaded and checked the first time), or None to let the request through."""
    CACHE.mkdir(parents=True, exist_ok=True)
    kept = CACHE / hashlib.sha256(url.encode()).hexdigest()
    if kept.exists():
        body = kept.read_bytes()
        if matches(body, integrity):
            return body
        kept.unlink()
    try:
        with urllib.request.urlopen(url, timeout=30) as response:
            body = response.read()
    except Exception:  # noqa: BLE001 (offline or the CDN down: the page asks the CDN itself)
        return None
    if not matches(body, integrity):
        return None
    tmp = kept.with_suffix(".part")
    tmp.write_bytes(body)
    tmp.replace(kept)
    return body
