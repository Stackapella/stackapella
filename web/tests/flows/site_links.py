"""Every page in the sitemap is a link away (the internal-link audit, 2026-11-13): from / it follows the public site's own
links (no app, API or asset paths) and checks that each sitemap page is reached, that no page we link to answers with a
redirect or an error, and that none is more than 3 clicks from /. Needs the real server (it reads the sitemap):

    python3 -I tests/flows/site_links.py http://127.0.0.1:8412
"""
import re
import sys
import urllib.error
import urllib.request
from concurrent.futures import ThreadPoolExecutor

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8081").rstrip("/")
# Not pages: assets, the API, media, embeds and the app's own routes, which the sitemap never lists.
SKIP = re.compile(r"\.(css|js|json|xml|txt|png|jpe?g|webp|svg|ico|mp3|m4a|ogg|wav|pdf|zip|woff2?|webmanifest)$|^/(css|js|icons|fonts|course-css|site-css|api|log|song-media|media|embed|learn|tools-sw|sw)\b")
DEEPEST = 3


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


OPENER = urllib.request.build_opener(NoRedirect)


def fetch(path):
    try:
        with OPENER.open(BASE + path, timeout=60) as response:
            return path, response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return path, error.code, ""


def links(html):
    found = set()
    for href in re.findall(r'href="([^"#]+)', html):
        href = href.replace("&amp;", "&")
        if href.startswith(BASE):
            href = href[len(BASE):]
        if not href.startswith("/") or href.startswith("//"):
            continue
        href = href.split("?")[0] or "/"
        if not SKIP.search(href):
            found.add(href)
    return found


def main():
    depth = {"/": 0}
    status = {}
    linked_from = {}
    frontier = ["/"]
    with ThreadPoolExecutor(6) as pool:
        while frontier:
            following = []
            for path, code, html in pool.map(fetch, frontier):
                status[path] = code
                if code != 200:
                    continue
                for link in links(html):
                    linked_from.setdefault(link, path)
                    if link not in depth:
                        depth[link] = depth[path] + 1
                        following.append(link)
            frontier = following
    with urllib.request.urlopen(BASE + "/sitemap.xml", timeout=60) as response:
        sitemap = [re.sub(r"^https?://[^/]+", "", url) or "/" for url in re.findall(r"<loc>([^<]+)</loc>", response.read().decode())]

    failures = []
    unlinked = [path for path in sitemap if path not in depth]
    if unlinked:
        failures.append(f"{len(unlinked)} sitemap pages nothing links to: {unlinked[:12]}")
    bad = {path: code for path, code in status.items() if code != 200}
    if bad:
        failures.append(f"links to redirects or errors: {[(p, c, linked_from.get(p)) for p, c in list(bad.items())[:12]]}")
    deep = sorted((d, p) for p, d in depth.items() if d > DEEPEST and status.get(p) == 200)
    if deep:
        failures.append(f"{len(deep)} pages more than {DEEPEST} clicks from /: {deep[:12]}")
    if failures:
        for failure in failures:
            print(f"FAIL site_links: {failure}")
        sys.exit(1)
    print(f"ok   site_links ({len(status)} pages crawled, all {len(sitemap)} sitemap pages linked, none deeper than {DEEPEST})")


if __name__ == "__main__":
    main()
