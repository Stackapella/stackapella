#!/usr/bin/env python3
"""The launch's smoke run (docs/launch-day.md, step 2's checks): read-only, against the real hosts, no
sign-ups and no writes. Each check prints PASS or FAIL; any FAIL exits 1.

    python3 web/tool/launch_smoke.py                 # after the switch: the site on stackapella.com
    python3 web/tool/launch_smoke.py --now           # today: the site still on the beta host, coming soon
    python3 web/tool/launch_smoke.py --public stackapella.com --app app.stackapella.com --old beta.stackapella.com --www www.stackapella.com --share <id>

After the switch: signed out, the landing renders in both skins with no console errors and its Start
reaches lesson 1 on the app with no early-access gate; /pricing, /how-it-works, /help and /courses
render; a long song page's first story picture answers as an image at its src, and the page has no
console errors scrolled to its end; www and the old public host 301 to the public site with the same path (and /h/<id> with
--share); the old host's ?app is the moving page (a fresh device goes on to the app); robots.txt allows search on the public site and
disallows it on the app and the old host; the app's /up answers 200. With --now, today's answers
instead: the apex and www 302 to the beta, which serves the site with search off and no Start yet,
and the app shows a guest the early-access gate's Coming soon.
"""
import argparse
import json
import sys
import re
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from cdp import Chrome  # noqa: E402

PAGES = ["/pricing", "/how-it-works", "/help", "/courses"]
# A long song page with story pictures: the two misses of 2026-11-13 (its pictures answered the site's
# "moving" page, and the app prefetch's blocked fetches filled the console) only show on the real hosts.
SONG = "/songs/amazing-grace"
results = []


def say(ok, what, detail=""):
    results.append(ok)
    print(f"{'PASS' if ok else 'FAIL'}  {what}{('  (' + detail + ')') if detail else ''}", flush=True)


class NoFollow(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def fetch(url):
    """(status, Location, body) without following redirects."""
    opener = urllib.request.build_opener(NoFollow)
    request = urllib.request.Request(url, headers={"User-Agent": "stackapella-launch-smoke"})
    try:
        with opener.open(request, timeout=20) as response:
            return response.status, response.headers.get("Location"), response.read(200000).decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return error.code, error.headers.get("Location"), error.read(200000).decode("utf-8", "replace")
    except Exception as error:  # a host that doesn't answer is a FAIL, said
        return None, None, str(error)


def content_type(url):
    """(status, Content-Type) of a GET, without following redirects."""
    opener = urllib.request.build_opener(NoFollow)
    request = urllib.request.Request(url, headers={"User-Agent": "stackapella-launch-smoke"})
    try:
        with opener.open(request, timeout=20) as response:
            return response.status, response.headers.get("Content-Type") or ""
    except urllib.error.HTTPError as error:
        return error.code, error.headers.get("Content-Type") or ""
    except Exception as error:
        return None, str(error)


def song_picture(public, song):
    """The song page's first story picture answers as an image at the src the page itself uses."""
    url = f"https://{public}{song}"
    status, _, body = fetch(url)
    match = re.search(r'<img[^>]*\ssrc="([^"]*song-media/[^"]*)"', body)
    if status != 200 or not match:
        say(False, f"{url} has a story picture", f"got {status}, {'a picture' if match else 'no song-media picture'}")
        return
    src = urllib.parse.urljoin(url, match.group(1).replace("&amp;", "&"))
    status, kind = content_type(src)
    say(status == 200 and kind.startswith("image/"), f"{song}'s first story picture answers as an image", f"{src}: {status} {kind}")


def redirects(url, code, to):
    status, location, _ = fetch(url)
    say(status == code and location == to, f"{url} {code}s to {to}", f"got {status} {location}")


def robots(host, allowed):
    status, _, body = fetch(f"https://{host}/robots.txt")
    blocked = any(line.strip().lower() == "disallow: /" for line in body.splitlines())
    if allowed:
        say(status == 200 and not blocked and "sitemap:" in body.lower(), f"{host}/robots.txt allows search, with the sitemap", f"{status}")
    else:
        say(status == 200 and blocked, f"{host}/robots.txt disallows search", f"{status}")


def page(chrome, url, what, wait="document.querySelector('h1')", scroll=False):
    chrome.errors.clear()
    try:
        chrome.goto(url, wait=wait, timeout=30)
        time.sleep(1.5)  # late console errors (fonts, the theme, a lazy module)
        if scroll:  # down the whole page, so every lazy picture and late fetch has its turn
            chrome.evaluate("window.scrollTo(0, document.body.scrollHeight)")
            time.sleep(3)
        # A guest's account check answers 401 by design; the browser itself logs any non-2xx response.
        errors = [e for e in chrome.errors if "favicon" not in e and not ("401" in e and "/api/account" in e)]
        say(not errors, what, "; ".join(errors[:3]))
        return True
    except Exception as error:
        say(False, what, str(error))
        return False


def choose_skin(chrome, public, skin):
    """The skin kept for `public`, set on its own origin: robots.txt loaded there first, waited for (a cold
    start just after a deploy can take seconds), and tried once more if the storage isn't ready yet."""
    origin = json.dumps(f"https://{public}")
    for attempt in (1, 2):
        try:
            chrome.goto(f"https://{public}/robots.txt", wait=f"location.origin === {origin} && document.readyState === 'complete'", timeout=60)
            chrome.evaluate(f"localStorage.setItem('stackapella.appearance', JSON.stringify({{ skin: {json.dumps(skin)}, at: Date.now() }}))")
            return
        except (RuntimeError, TimeoutError) as error:
            if attempt == 2:
                raise
            print(f"   (the {skin} skin couldn't be kept yet, trying again: {str(error)[:80]})")
            time.sleep(2)


def landing(chrome, public, app, now):
    for skin in ("game", "serious"):
        choose_skin(chrome, public, skin)
        # Until the page is complete: funnel.js, a module that runs after the HTML, writes from=start into
        # every Start. Read sooner (an h1 alone), the first check after a deploy raced it and lost.
        if not page(chrome, f"https://{public}/", f"the landing on {public} renders in the {skin} skin with no console errors",
                    wait="document.querySelector('h1') && document.readyState === 'complete'"):
            continue
        shown = chrome.evaluate("document.documentElement.dataset.skin")
        say(shown == skin, f"the landing shows the {skin} skin", f"data-skin {shown}")
    start = chrome.evaluate(f"[...document.querySelectorAll('a[href]')].map((a) => a.href).find((h) => h.startsWith('https://{app}/learn')) ?? null")
    if now:
        say(start is None, "today: no Start to lesson 1 yet (coming soon)", f"found {start}")
        # The server's early-access gate: a guest gets "Coming soon", not the app.
        status, _, body = fetch(f"https://{app}/learn/1-comfortable")
        say(status == 200 and "coming soon" in body.lower(), "today: a guest gets the early-access gate's Coming soon", f"{status}")
        return
    say(bool(start) and "from=start" in (start or ""), "the landing's Start goes to lesson 1 on the app, from=start", f"{start}")
    if start:
        page(chrome, start, "Start opens lesson 1 with no console errors", wait="document.querySelector('main h1')")
        at = chrome.evaluate("location.pathname")
        say(at.startswith("/learn/"), "no early-access gate: the lesson itself", f"at {at}")


def main():
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--public", default=None, help="the public site's host (default stackapella.com, or the beta host with --now)")
    parser.add_argument("--app", default="app.stackapella.com")
    parser.add_argument("--old", default="beta.stackapella.com", help="the old public host")
    parser.add_argument("--www", default="www.stackapella.com")
    parser.add_argument("--apex", default="stackapella.com", help="with --now: the apex that still 302s to the beta")
    parser.add_argument("--share", default=None, help="a shared harmony's id, for /h/<id> on the old host")
    parser.add_argument("--now", action="store_true", help="expect today's answers (before the switch)")
    args = parser.parse_args()
    now = args.now
    public = args.public or (args.old if now else "stackapella.com")

    print(f"Launch smoke: {'today (before the switch)' if now else 'after the switch'}; site {public}, app {args.app}, old {args.old}, www {args.www}\n", flush=True)
    status, _, _ = fetch(f"https://{args.app}/up")
    say(status == 200, f"{args.app}/up answers 200", f"got {status}")
    for path in PAGES:
        status, location, _ = fetch(f"https://{public}{path}")
        say(status == 200, f"{public}{path} renders", f"got {status} {location or ''}".strip())

    if now:
        redirects(f"https://{args.apex}/", 302, f"https://{args.old}/")
        redirects(f"https://{args.www}/pricing", 302, f"https://{args.old}/pricing")
        robots(public, allowed=False)
    else:
        redirects(f"https://{args.www}/pricing", 301, f"https://{public}/pricing")
        redirects(f"https://{args.old}/", 301, f"https://{public}/")
        redirects(f"https://{args.old}/pricing", 301, f"https://{public}/pricing")
        if args.share:
            redirects(f"https://{args.old}/h/{args.share}", 301, f"https://{public}/h/{args.share}")
        robots(public, allowed=True)
        robots(args.old, allowed=False)
    robots(args.app, allowed=False)

    if not now:
        song_picture(public, SONG)

    chrome = Chrome()
    try:
        landing(chrome, public, args.app, now)
        if not now:
            page(chrome, f"https://{public}{SONG}", f"the song page {SONG} on {public} has no console errors, scrolled to its end",
                 wait="document.querySelector('h1') && document.readyState === 'complete'", scroll=True)
        if not now:
            # The old host's app address: the moving page (for installs made there). With nothing kept
            # on this device there, it goes on to the new address at once, so a fresh browser lands on the app.
            status, _, body = fetch(f"https://{args.old}/?app")
            say(status == 200 and "data-moving" in body and f"https://{args.app}/" in body, f"{args.old}/?app is the moving page, pointing at {args.app}", f"{status}")
            chrome.goto(f"https://{args.old}/?app", timeout=30)
            host = ""
            for _ in range(30):
                try:
                    host = chrome.evaluate("location.host") or ""
                except RuntimeError:
                    host = ""
                if host == args.app:
                    break
                time.sleep(0.5)
            say(host == args.app, f"with nothing kept on {args.old}, a device goes straight on to {args.app}", f"at {host}")
    finally:
        chrome.close()

    failed = results.count(False)
    print(f"\n{len(results) - failed} passed, {failed} failed", flush=True)
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()
