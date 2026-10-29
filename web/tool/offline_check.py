"""Checks that every file the app needs offline is in sw.js's list (run by tool/test.sh): every
module in js/ and engine/, the locales, the templates, the course and the CSS; that every pinned CDN
file is precached or carried over from one version to the next; and that production serves every one
of them (WebAppController::SERVED): a file it doesn't serve fails the new version's
install. Python standard library only."""
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parent.parent
shell = re.search(r"const SHELL = \[(.*?)\];", (WEB / "sw.js").read_text(), re.S).group(1)
listed = set(re.findall(r"'(/[^']+)'", shell))
needed = [f"/{p.relative_to(WEB).as_posix()}" for pattern in ("js/**/*.js", "engine/*.js", "locales/*.json", "templates/*.json", "course/*.json") for p in WEB.glob(pattern)]
# The admin's code is never precached (docs/beta-singers-plan.md, "Admin code isn't precached").
needed = [path for path in needed if not path.startswith("/js/admin/")]
# The public pages' scripts (/h/<id>) belong to those server pages, not to the app.
needed = [path for path in needed if not path.startswith("/js/public/")]
# The embedded lane (/js/embed) is for public pages too (the landing's hero), not the app.
needed = [path for path in needed if not path.startswith("/js/embed/")]
missing = sorted(set(needed) - listed)
# Pinned CDN files (index.html's import map, MP3 export's encoder): precached, or carried over from
# one version to the next (sw.js CARRIED), so an update never leaves the app without one offline.
carried = set(re.findall(r"'(https://[^']+)'", re.search(r"const CARRIED = \[(.*?)\];", (WEB / "sw.js").read_text(), re.S).group(1)))
pinned = set(re.findall(r'"(https://cdn\.jsdelivr\.net/[^"]+)"', re.search(r'<script type="importmap">(.*?)</script>', (WEB / "index.html").read_text(), re.S).group(1)))
pinned |= set(re.findall(r"'(https://cdn\.jsdelivr\.net/[^']+)'", (WEB / "js" / "mp3.js").read_text()))
precached = set(re.findall(r"'(https://[^']+)'", shell))
unkept = sorted(url for url in pinned if url not in precached and url not in carried)
gone = sorted(path for path in listed if path.startswith("/") and path != "/" and not (WEB / path.lstrip("/")).exists())
controller = WEB.parent / "server/app/Http/Controllers/WebAppController.php"
served = re.findall(r"'([^']+)'", re.search(r"SERVED\s*=\s*\[(.*?)\];", controller.read_text(), re.S).group(1))
unserved = sorted(path for path in listed if path not in ("/", "/index.html") and not any(path.lstrip("/").startswith(e) if e.endswith("/") else path.lstrip("/") == e for e in served))
for path in missing:
    print(f"sw.js: {path} is missing from the offline list")
for path in gone:
    print(f"sw.js: {path} is listed but doesn't exist")
for url in unkept:
    print(f"sw.js: {url} is pinned in the app, but neither precached nor carried over (CARRIED)")
for path in unserved:
    print(f"sw.js: {path} is precached, but production doesn't serve it (WebAppController::SERVED, .do/nginx.conf)")
sys.exit(1 if missing or gone or unserved or unkept else 0)
