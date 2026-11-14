"""Every CDN library the app's page loads is pinned to an exact version with an integrity hash (CLAUDE.md: no npm, CDN
libraries pinned with SRI). Run by the public CI and by tool/test.sh, so a land chain meets it before a deploy does.
A preconnect or a dns-prefetch loads no code, so it needs neither (2026-11-14: index.html's preconnect to jsDelivr,
for lesson 1's speed, blocked a deploy). Its own cases are checked on every run.

    python3 -I web/tool/cdn_pins_check.py [index.html]
"""
import re
import sys
from pathlib import Path

CDN = r"https://cdn\.jsdelivr\.net"
TAG = re.compile(rf"<(?:script|link)\b[^>]*{CDN}[^>]*>")
VERSION = re.compile(r"@\d+\.\d+\.\d+/")
CONNECT_ONLY = re.compile(r"""\brel=["']?(?:preconnect|dns-prefetch)\b""")


def unpinned(html):
    """The script and link tags (and quoted URLs) that load CDN code without an exact version and an integrity hash."""
    bad = []
    for tag in TAG.findall(html):
        if tag.startswith("<link") and CONNECT_ONLY.search(tag):
            continue
        if not VERSION.search(tag) or 'integrity="sha' not in tag or 'crossorigin="anonymous"' not in tag:
            bad.append(tag)
    for url in re.findall(rf'"({CDN}/[^"]+)"', html):
        if not VERSION.search(url):
            bad.append(url)
    return bad


def self_check():
    pinned = '<script type="module" src="https://cdn.jsdelivr.net/npm/vue@3.5.44/dist/vue.js" integrity="sha384-x" crossorigin="anonymous"></script>'
    assert unpinned(pinned) == [], "a pinned script passes"
    assert unpinned('<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>') == [], "a preconnect loads nothing"
    assert unpinned('<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">') == [], "a dns-prefetch loads nothing"
    assert unpinned('<script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.js" integrity="sha384-x" crossorigin="anonymous"></script>'), "a script without an exact version fails"
    assert unpinned('<script src="https://cdn.jsdelivr.net/npm/vue@3.5.44/dist/vue.js"></script>'), "a script without integrity fails"
    assert unpinned('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/x@1.2.3/x.css">'), "a stylesheet without integrity fails"
    assert unpinned('<link rel="modulepreload" href="https://cdn.jsdelivr.net/npm/vue@3.5.44/dist/vue.js" crossorigin>'), "a preload of code without integrity fails"
    assert unpinned('{"vue": "https://cdn.jsdelivr.net/npm/vue/dist/vue.js"}'), "an import map's URL without a version fails"


def main():
    self_check()
    path = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(__file__).resolve().parents[1] / "index.html"
    html = path.read_text()
    bad = unpinned(html)
    if bad:
        print("Not pinned to an exact version with an integrity hash:\n" + "\n".join(bad))
        sys.exit(1)
    print(f"cdn pins: {len(TAG.findall(html))} CDN tags, every library pinned")


if __name__ == "__main__":
    main()
