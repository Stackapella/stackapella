"""Writes server/resources/site-css-families.json, the classes each public page family can show (docs/css-split-plan.md,
step 4; App\\Support\\Site\\SiteCss), from a running site: every page of its sitemap and every public page they link to
(the languages' too, each page once, its query left off), rendered as a visitor gets it, each counted in the family its
layout names (<meta name="css-family">). The layout and the public JS add their own words on the server, so only what
the pages' markup shows is written here. Run it when SiteCssPagesTest asks (a page shows a class its family's sheet
might lack), against a site serving this checkout with the content linked:

    python3 -I tool/site_families.py http://127.0.0.1:8422
"""
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from html import unescape
from pathlib import Path

OUT = Path(__file__).resolve().parents[2] / "server/resources/site-css-families.json"
# Pages the sitemap doesn't list, and the variants an arrival link shows (mudeo's letter on the landing and /about: by its
# ?ref= or the cookie it sets), each read with its query.
SEEDS = ["/", "/songbook", "/compare", "/note-frequencies", "/?ref=mudeo", "/about?ref=mudeo"]
CLASS_ATTR = re.compile(r"""(?<=\s)class=(?:"([^"]*)"|'([^']*)')""")
FAMILY = re.compile(r'<meta name="css-family" content="([a-z]+)">')
HREF = re.compile(r'''\bhref="([^"#]+)''')
SKIP = re.compile(r"\.(css|js|json|xml|txt|png|jpe?g|webp|svg|ico|mp3|m4a|ogg|wav|pdf|zip|woff2?)$|^/(css|js|icons|fonts|course-css|site-css|api|log)/")


def fetch(site, path):
    try:
        with urllib.request.urlopen(urllib.request.Request(site + path, headers={"Accept": "text/html"}), timeout=60) as r:
            if "text/html" not in (r.headers.get("Content-Type") or ""):
                return path, None
            return path, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return path, None if error.code in (401, 403, 404, 410) else error  # a link to nothing (or to an account's page)
    except Exception as error:  # a page that won't render is named, never counted
        return path, error


def main():
    site = sys.argv[1].rstrip("/")
    host = urllib.parse.urlparse(site).netloc
    sitemap = urllib.request.urlopen(site + "/sitemap.xml", timeout=60).read().decode()
    locs = re.findall(r"<loc>([^<]+)</loc>", sitemap)
    hosts = {host} | {urllib.parse.urlparse(u).netloc for u in locs}  # the site's own address may differ from the one asked
    todo = set(SEEDS) | {urllib.parse.urlparse(u).path or "/" for u in locs}
    seen, families, failed = set(), {}, []
    with ThreadPoolExecutor(4) as pool:
        while todo:
            batch = sorted(todo - seen)
            seen |= set(batch)
            todo = set()
            for path, html in pool.map(lambda p: fetch(site, p), batch):
                if not isinstance(html, str):
                    if html is not None:
                        failed.append(f"{path}: {html}")
                    continue
                family = FAMILY.search(html)
                if not family:
                    continue  # not a public page (the app, an embed)
                words = families.setdefault(family.group(1), set())
                for a, b in CLASS_ATTR.findall(html):
                    words.update(unescape(w) for w in (a or b).split())
                for href in HREF.findall(html):
                    url = urllib.parse.urlparse(urllib.parse.urljoin(site + path, unescape(href)))
                    if url.scheme in ("http", "https") and url.netloc in hosts and not SKIP.search(url.path):
                        todo.add(url.path or "/")
            print(f"site families: {len(seen)} pages read", file=sys.stderr)
    if failed:
        raise SystemExit("site families: these pages failed, so the manifest isn't written:\n" + "\n".join(failed[:20]))
    OUT.write_text(json.dumps({"written by": "web/tool/site_families.py", "pages": len(seen),
                               "families": {k: sorted(v) for k, v in sorted(families.items())}}, indent=1) + "\n")
    print(f"site families: {OUT.name} written from {len(seen)} pages: " + ", ".join(f"{k} {len(v)} classes" for k, v in sorted(families.items())))


if __name__ == "__main__":
    main()
