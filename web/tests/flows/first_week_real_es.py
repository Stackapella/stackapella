"""A brand-new singer's first week IN SPANISH, end to end against the REAL local server: first_week_real.py's walk
(arrival, lesson 1 sung over loopback, the course wall at lesson 2, sign-up verified from the server's log, lessons 2
and 3 with the session wrap, a day later, unit 1's song into Sing, Home's "1 of 4 parts is you", past week one, and
4-words' in-lesson song) as a singer whose browser speaks Spanish, phone size, in both skins:

    API=http://127.0.0.1:<port> PORT=<free> FLOW_SERVER=<checkout>/server FLOW_SKINS=game,serious FLOW_SIZES=phone \\
        bash tool/flows.sh first_week_real_es

The singer: a Spanish browser (Accept-Language and navigator.languages es-ES, Intl in es-ES), nothing chosen on the
device. With the lang_es switch on (Admin > Settings > Switches) that's how a real Spanish singer gets Spanish: the
app from auth.config's `languages`, the server (a new account's locale, the verification mail) from Accept-Language.
With the switch off the walk still runs, as a draft check: every page load adds ?lang=es, and the mail stays English.

On top of the English walk's checks (each one read through the app's own t(), never an English word), every screen
it passes is read for what isn't Spanish and shot:
- English showing: every visible text, aria-label, title, placeholder and alt, matched against en.json (the key is
  named, and why: missing from es.json, stale since its English changed, or translated but shown in English anyway,
  which means words that don't come through t() for this screen) and against English-only words (a sentence with words
  that are in en.json but in no Spanish file: hard-coded English or server words).
- Words cut or spilling at phone width (language_fit.py's check), and the page scrolling sideways.
- Note names (the found note in fixed Do: La3), and in game mode any character the pixel face doesn't have.
- The verification mail's subject and body (read from the server's log), the wall's share text and calendar entry.
Then a few of the public site's /es pages (the home, courses, a course, songs, a song and its Sing link, pricing,
tools), read the same way. Counts are read off the wire as in the English walk, to compare with its results.

The results go to tests/flows/shots/first_week_real_es-<skin>-results.json and to stdout. FIRST_WEEK_EMAIL signs in
an existing account instead of making one (sign-ups: 5 an hour from one address). FIRST_WEEK_LANG=en walks the same
screens in English, as the baseline: the counts per step must match (only open_game / open_serious follow the skin).

The local server (tool/serve.php) must pass Accept-Language on to the API, as production's nginx does: without it the
server never hears the browser's language, and a new account and its mail stay English.
"""
import json
import os
import random
import re
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run, SHOTS  # noqa: E402
from first_week_real import (  # noqa: E402
    AUDIO, LEARNING, LOGS, PASSWORD, VIEW, Report, Wire, dismiss_title, finish_scored, lesson_open, t, walk_steps,
)
from language_fit import CUT  # noqa: E402

LANG = os.environ.get("FIRST_WEEK_LANG", "es")
SITE = os.environ.get("API", "").rstrip("/")  # the real server, which serves the public site's pages
WEB = Path(__file__).resolve().parents[2]

# ---- What English looks like, from the app's own files ----------------------------------------------------------------
STAFF = ("admin.", "styleguide.", "styleguideGame.")  # English in every language by design (i18n.js ENGLISH_ONLY)


def flat(node, path=""):
    out = {}
    for key, value in node.items():
        name = f"{path}.{key}" if path else key
        if isinstance(value, dict):
            out.update(flat(value, name))
        elif isinstance(value, str):
            out[name] = value
    return out


def wildcard(message):
    """An ICU message as a regex: each top-level {...} (a value or a whole plural) matches anything."""
    out, depth, literal = "", 0, ""
    for ch in message:
        if ch == "{":
            if depth == 0:
                out += re.escape(literal) + ".+?"
                literal = ""
            depth += 1
        elif ch == "}":
            depth = max(0, depth - 1)
        elif depth == 0:
            literal += ch
    return out + re.escape(literal)


def outside(message):
    """An ICU message's own words, outside every {...}."""
    out, depth = "", 0
    for ch in message:
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth = max(0, depth - 1)
        elif depth == 0:
            out += ch
    return out


def words_of(text):
    return re.findall(r"[A-Za-zÀ-ÿ']{3,}", text)


ENGLISH = flat(json.loads((WEB / "locales/en.json").read_text()))
FOREIGN = flat(json.loads((WEB / f"locales/{LANG}.json").read_text())) if LANG != "en" else {}
SOURCE_FILE = WEB / f"locales/{LANG}.source.json"
SOURCE = flat(json.loads(SOURCE_FILE.read_text())) if LANG != "en" and SOURCE_FILE.exists() else {}
LEAKS = []  # (why, key, regex): the untranslated first, so a line is named by the key that shows it
for key, english in ENGLISH.items():
    if key.startswith(STAFF) or len(english.strip()) < 4:
        continue
    if key not in FOREIGN:
        why = f"missing from {LANG}.json"
    elif SOURCE and SOURCE.get(key) != english:
        why = f"stale in {LANG}.json (its English changed since)"
    elif FOREIGN[key].strip() == english.strip():
        continue  # the same in both (a name, "OK")
    else:
        why = "translated, but shown in English"
    if len(re.sub(r"[^A-Za-z]", "", outside(english))) >= 6:
        LEAKS.append((why, key, re.compile("^" + wildcard(english.strip()) + "$", re.S)))
LEAKS.sort(key=lambda leak: leak[0].startswith("translated"))
# The app's Spanish words (es.json alone: the course and server files quote English titles and lyrics, which would teach
# it that "the" is Spanish).
spanish_texts = list(FOREIGN.values())
# English-only words: common in en.json and (next to) never in the Spanish files. By frequency, not presence: the Spanish
# words quote a song title now and then ("Row Your Boat"), so a word used once or twice there is still English.
SPANISH_COUNT, ENGLISH_COUNT = {}, {}
for text in spanish_texts:
    for w in words_of(text):
        SPANISH_COUNT[w.lower()] = SPANISH_COUNT.get(w.lower(), 0) + 1
for k, text in ENGLISH.items():
    if not k.startswith(STAFF):
        for w in words_of(text):
            ENGLISH_COUNT[w.lower()] = ENGLISH_COUNT.get(w.lower(), 0) + 1
ENGLISH_ONLY = {w for w, n in ENGLISH_COUNT.items() if SPANISH_COUNT.get(w, 0) <= max(1, n * 0.04)}
# Names and loanwords Spanish keeps as they are.
ENGLISH_ONLY -= {"stackapella", "plus", "pro", "email", "online", "app", "apps", "ios", "android", "google", "apple", "mic",
                 "michael", "row", "boat", "ashore", "barbershop", "gospel", "pop", "jazz", "blues", "folk", "beatbox", "scat",
                 "yodel", "soprano", "alto", "tenor", "bass", "bajo", "sol", "pdf", "musicxml", "csv", "solo"}

# Every visible piece of text on the page, and the words people never see but hear or hover (aria-label, title, alt,
# placeholder), with the page's title.
TEXTS = """(() => {
    const out = new Set();
    const seen = (el) => el && el.getClientRects().length && !el.closest('[translate="no"], [hidden], script, style, noscript');
    const walk = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let node = walk.nextNode(); node; node = walk.nextNode()) {
        const text = node.textContent.replace(/\\s+/g, ' ').trim();
        if (text && /\\p{L}{2,}/u.test(text) && seen(node.parentElement)) out.add(text);
    }
    // Whole blocks too, so a sentence split over <strong> and plain text reads as one.
    for (const el of document.querySelectorAll('body p, body li, body button, body a, body h1, body h2, body h3, body label, body [role=status], body [role=alert]')) {
        const text = el.innerText?.replace(/\\s+/g, ' ').trim();
        if (text && text.length < 400 && seen(el)) out.add(text);
    }
    const attrs = [];
    for (const el of document.querySelectorAll('[aria-label], [title], [placeholder], img[alt]')) {
        if (!el.getClientRects().length && el.tagName !== 'svg') continue;
        for (const a of ['aria-label', 'title', 'placeholder', 'alt']) { const v = el.getAttribute(a); if (v && /\\p{L}{2,}/u.test(v)) attrs.push(`${a}: ${v.trim()}`); }
    }
    return { texts: [...out], attrs: [...new Set(attrs)], title: document.title, lang: document.documentElement.lang };
})()"""

PIXEL = json.loads((Path(__file__).parent / "pixel_glyphs.json").read_text())["codepoints"]
# As game_frames.py checks it: characters drawn in the pixel face that it doesn't have.
PIXEL_FACE = """((have) => {
    const has = new Set(have); const missing = new Set(); let used = false; const accents = new Set();
    const walk = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let node = walk.nextNode(); node; node = walk.nextNode()) {
        const el = node.parentElement;
        if (!el || !node.textContent.trim() || !el.getClientRects().length) continue;
        if (!getComputedStyle(el).fontFamily.includes('Stackapella Pixel')) continue;
        used = true;
        for (const ch of node.textContent) { if (/[áéíóúñüÁÉÍÓÚÑÜ¿¡]/.test(ch)) accents.add(ch); if (ch.trim() && !has.has(ch.codePointAt(0))) missing.add(ch); }
    }
    return { used, loaded: !used || document.fonts.check('16px "Stackapella Pixel"'), missing: [...missing].join(''), accents: [...accents].join('') };
})"""
NEVER_HAD = "♩♪♫♬♭♮♯▸▾▴◂✓✗✕✦✶·↗−⋯⋮●○"


def english_in(texts):
    """What reads as English in a screen's texts: [(text, why, key or words)]. Nothing in English (FIRST_WEEK_LANG=en
    walks the same screens as the baseline for the counts and the widths)."""
    if LANG == "en":
        return []
    found = []
    for text in texts:
        bare = re.sub(r"^(aria-label|title|placeholder|alt): ", "", text)
        hit = next(((why, key) for why, key, rx in LEAKS if rx.match(bare)), None)
        if hit:
            found.append({"text": text[:200], "why": hit[0], "key": hit[1]})
            continue
        english = [w for w in words_of(bare) if w.lower() in ENGLISH_ONLY]
        total = len(words_of(bare))
        if english and (len(english) >= 2 or total <= 3 and len(english) == total):
            found.append({"text": text[:200], "why": "English words", "key": " ".join(english[:8])})
    return found


class Screens:
    """Every screen the walk passes: shot, read for English, cut words and the pixel face."""

    def __init__(self, f, report):
        self.f = f
        self.report = report
        self.rows = []

    def look(self, label, scroll_top=False):
        f = self.f
        if scroll_top:
            f.run("window.scrollTo(0, 0)")
        f.shot(label)
        shot = f"{f.name}-{f.size}-{f.step:02d}-{label}.png"
        seen = f.js(TEXTS)
        english = english_in(seen["texts"] + seen["attrs"] + [f"title: {seen['title']}"])
        cut = f.js(CUT)
        over = f.js("document.documentElement.scrollWidth - document.documentElement.clientWidth")
        pixel = None
        if f.skin == "game":
            f.run("await document.fonts.ready")
            pixel = f.js(f"{PIXEL_FACE}({json.dumps(PIXEL)})")
            pixel["missing"] = "".join(ch for ch in pixel["missing"] if ch not in NEVER_HAD)
        row = {"screen": label, "shot": shot, "url": f.js("location.pathname + location.search"), "lang": seen["lang"], "english": english,
               "cut": cut, "sideways_px": over, "pixel": pixel, "texts": seen["texts"], "attrs": seen["attrs"], "title": seen["title"]}
        self.rows.append(row)
        flags = []
        if seen["lang"] != LANG:
            flags.append(f"<html lang={seen['lang']}>")
        if english:
            flags.append(f"{len(english)} English: " + " | ".join(f"{e['text'][:70]} [{e['key']}]" for e in english[:6]))
        if cut:
            flags.append(f"cut: {cut[:4]}")
        if over > 0:
            flags.append(f"sideways {over}px")
        if pixel and pixel["missing"]:
            flags.append(f"pixel face lacks {pixel['missing']!r}")
        print(f"     [{f.skin}] screen {label:22} " + ("; ".join(flags) if flags else "clean"))
        return row


def go(f, path, wait=None):
    """A page load, in Spanish: the browser's own language when the switch is on, else ?lang=es (a draft check)."""
    if getattr(f, "draft", False) and "lang=" not in path:
        path += ("&" if "?" in path else "?") + f"lang={LANG}"
    return f.go(path, wait)


def spanish_browser(f):
    """The browser speaks the language: Accept-Language, navigator.language(s) and Intl."""
    if LANG == "en":
        return
    agent = f.chrome.send("Browser.getVersion")["userAgent"].replace("HeadlessChrome", "Chrome")
    f.chrome.send("Network.setUserAgentOverride", {"userAgent": agent, "acceptLanguage": f"{LANG}-ES,{LANG}", "platform": "iPhone"})
    f.chrome.send("Network.setExtraHTTPHeaders", {"headers": {"Accept-Language": f"{LANG}-ES,{LANG};q=0.9"}})
    try:
        f.chrome.send("Emulation.setLocaleOverride", {"locale": f"{LANG}-ES"})
    except RuntimeError:
        pass


def read_mail(email, sizes):
    """The verification mail as the log mailer wrote it: subject and body (quoted-printable undone)."""
    for log, before in sizes.items():
        if not log.exists():
            continue
        text = log.read_bytes()[before:].decode(errors="replace")
        at = text.find(f"To: {email}")
        if at < 0:
            at = text.find(email)
        if at < 0:
            continue
        start = text.rfind("] local.", 0, at)
        chunk = text[start if start >= 0 else max(0, at - 3000):at + 20000]
        end = re.search(r"\n\[20\d\d-\d\d-\d\d [0-9:]+\] ", chunk[20:])
        chunk = chunk[:end.start() + 20] if end else chunk
        subject = re.search(r"^Subject: (.*)$", chunk, re.M)
        body = re.sub(r"=\r?\n", "", chunk)
        body = re.sub(r"=([0-9A-F]{2})", lambda m: chr(int(m.group(1), 16)), body)
        try:
            body = body.encode("latin-1").decode("utf-8")
        except (UnicodeEncodeError, UnicodeDecodeError):
            pass
        plain = re.search(r"Content-Type: text/plain.*?\r?\n\r?\n(.*?)(\r?\n--)", body, re.S)
        html_lang = re.search(r"<html[^>]*lang=\"([^\"]+)\"", body)
        return {"log": str(log), "subject": subject.group(1).strip() if subject else None, "plain": (plain.group(1).strip() if plain else body[:3000]),
                "html_lang": html_lang.group(1) if html_lang else None}
    return None


def sign_up(f, report, screens, email):
    """sign_up() as in first_week_real.py, the form and its sent and verified screens read for English, and the mail
    read: its subject and body should be in the singer's language."""
    sizes = {log: (log.stat().st_size if log.exists() else 0) for log in LOGS}
    f.run("document.querySelector('[data-needs-account-yes]').click()")
    f.wait("location.pathname === '/signin' && document.querySelector('form input[type=email]')", what="sign-up")
    screens.look("signup-form", scroll_top=True)
    if os.environ.get("FIRST_WEEK_EMAIL"):
        go(f, "/signin?next=/learn/1-match", "document.querySelector('form')")
        f.type("input[type=email]", email)
        f.run("document.querySelector('form').requestSubmit()")
        f.wait("document.querySelector('input[autocomplete=current-password]')", what="the password step")
        f.type("input[autocomplete=current-password]", PASSWORD)
        f.run("document.querySelector('form').requestSubmit()")
        f.wait(f"(await import('/js/stores/account.js')).account.me?.email === {email!r}", timeout=20, what="signed in (FIRST_WEEK_EMAIL)")
        report.add("sign-up and verify (skipped: FIRST_WEEK_EMAIL signed in instead)", None, email)
        return None
    if f.js("!!document.querySelector('input[autocomplete=name]')"):
        f.type("input[autocomplete=name]", "Primera Semana")
    f.type("input[type=email]", email)
    f.type("input[autocomplete=new-password]", PASSWORD)
    f.run("const s = document.querySelectorAll('form fieldset select'); if (s.length >= 2) { s[0].value = '4'; s[0].dispatchEvent(new Event('change')); s[1].value = '1990'; s[1].dispatchEvent(new Event('change')); }")
    screens.look("signup-filled")
    time.sleep(2.5)  # a form sent in under 2 seconds is taken for a bot
    f.run("document.querySelector('form').requestSubmit()")
    f.wait_text(t(f, "auth.sent.title"), timeout=20)
    screens.look("signup-sent")
    deadline = time.time() + 90
    token = None
    mail = None
    while time.time() < deadline and not token:
        for log, before in sizes.items():
            if not log.exists():
                continue
            text = log.read_bytes()[before:].decode(errors="replace")
            if email in text:
                found = re.findall(r"/verify\?token=([A-Za-z0-9._~%-]+)", text[text.index(email):])
                if found:
                    token = found[0]
                    mail = read_mail(email, sizes)
                    break
        time.sleep(0.5)
    if not token:
        report.add("verification mail written to a server log", False, f"none for {email} in 90 s")
        raise AssertionError("no verification mail")
    english = english_in([mail["subject"] or ""] + [line for line in (mail["plain"] or "").splitlines() if line.strip()]) if mail else []
    report.add(f"verification mail in {LANG} (subject and body)", (not english) if f.offered else "note",
               {"subject": mail and mail["subject"], "html_lang": mail and mail["html_lang"], "english": english[:10], "plain": mail and mail["plain"][:1200]})
    go(f, f"/verify?token={token}", "document.querySelector('#app')")
    f.wait(f"(await import('/js/stores/account.js')).account.me?.email === {email!r}", timeout=30, what="signed in, verified")
    screens.look("verified")
    locale = f.js("(await import('/js/stores/account.js')).account.me?.locale ?? null")
    report.add(f"the new account's language is {LANG} (from the browser, so emails follow)", locale == LANG if f.offered else "note", {"account.locale": locale, "switch_on": f.offered})
    return mail


def journey(f):
    report = Report(f)
    screens = Screens(f, report)
    spanish_browser(f)
    wire = Wire(f)
    f.expected_errors += ["401 (Unauthorized) http://127.0.0.1", "419 (Unknown Status Code) http://127.0.0.1", "404 (Not Found) http://127.0.0.1"]
    email = os.environ.get("FIRST_WEEK_EMAIL") or f"e2e-{LANG}-{f.skin}-{int(time.time())}-{random.randint(100, 999)}@example.com"
    steps = {}
    extra = {}

    def counts_since(mark):
        wire.flush()
        return wire.kinds(mark)

    try:
        _journey(f, report, screens, wire, email, counts_since, steps, extra)
        if SITE and LANG != "en":
            site(f, report, screens)
    finally:
        wire.flush()
        out = {"skin": f.skin, "lang": LANG, "email": email, "switch_on": getattr(f, "offered", None), "rows": report.rows, "screens": screens.rows,
               "counts_by_step": steps, "all_counts": wire.kinds(), "failed_requests": wire.failed, "console_errors": list(f.chrome.errors),
               "console_warnings": list(getattr(f.chrome, "warnings", []))[:80], **extra}
        SHOTS.mkdir(exist_ok=True)
        (SHOTS / f"first_week_real_es-{f.skin}-results.json").write_text(json.dumps(out, indent=2, ensure_ascii=False))
    broken = [r for r in report.rows if r["status"] == "broken"]
    if broken:
        raise AssertionError(f"{len(broken)} broken: " + "; ".join(r["item"] for r in broken))


def _journey(f, report, screens, wire, email, counts_since, steps, extra):
    # ---- 1. Arrive, the mic checked, lesson 1 sung --------------------------------------------------------------------
    mark = wire.mark
    f.go("/", "document.querySelector('#app header')")
    f.wait("(await import('/js/stores/account.js')).account.config", what="the server's config")
    languages = f.js("[...((await import('/js/stores/account.js')).account.config.languages ?? [])]")
    f.offered = LANG in languages or LANG == "en"
    f.draft = not f.offered
    report.add(f"switch: {LANG} offered by the server (auth.config languages)", True if f.offered else "note", {"languages": languages, "navigator.languages": f.js("navigator.languages"), "intl": f.js("Intl.DateTimeFormat().resolvedOptions().locale")})
    if f.draft:
        go(f, "/", "document.querySelector('#app header')")
    f.wait(f"document.documentElement.lang === {LANG!r}", timeout=15, what=f"the app in {LANG}")
    report.add("server config: usage counts on", f.js("(await import('/js/stores/account.js')).account.config.usageCounts") is True, "account.config.usageCounts")
    f.mic_checked()
    screens.look("home-guest")
    if f.js("!!document.querySelector('[data-press-start]')"):
        f.run("document.querySelector('[data-press-start]').click()")
    else:
        f.click_t("course.start")
    f.wait("location.pathname === '/learn/1-comfortable'", what="lesson 1")
    lesson_open(f, "1-comfortable")
    screens.look("lesson1-learn")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    walk_steps(f, "1-comfortable")
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening for the note")
    screens.look("lesson1-listening")
    f.run(f"const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js'); engine.preview(renderPart([{{ beat: 0, length: 6, pitch: 57 }}], {{ bpm: 60, sampleRate: engine.sampleRate }}), engine.sampleRate);")
    # The note in the language's naming (fixed Do in Spanish: La3), as note-names.js names it.
    a3 = f.js("(await import('/js/note-names.js')).nameNote(57)")
    f.wait(f"document.querySelector('[data-note]')?.textContent === {a3!r}", timeout=25, what=f"the note found ({a3})")
    report.add(f"the note found, named in the language ({a3})", a3 == ("La3" if LANG == "es" else "A3"), {"shown": f.js("document.querySelector('[data-note]')?.textContent")})
    screens.look("lesson1-note-found")
    f.click_t("course.finish")
    f.wait(f"!!{VIEW}?.done", what="lesson 1's end")
    time.sleep(1.5)
    screens.look("lesson1-end")
    c = counts_since(mark)
    steps["1 lesson 1"] = c
    want = {"lesson_start": 1, "fl_s:1-comfortable": 1, "fs_r:1-comfortable.0": 1, "fs_r:1-comfortable.1": 1, "fl_d:1-comfortable": 1, "ft:lesson": 1}
    report.add("lesson 1 counted (lesson_start, fl_s, fs_r per step, fl_d, ft:lesson)", all(c.get(k) == v for k, v in want.items()), {"want": want, "seen": c})
    report.add("first open counted app_open/app_day", "app_day" in c and "app_open" in c, {k: c.get(k) for k in ("app_open", "app_day", "app_week", "app_month") if k in c})

    # ---- 2. Lesson 2 as a guest: the wall, what waits, one way back --------------------------------------------------
    mark = wire.next_mark()
    f.run("(await import('/js/router.js')).navigate('/learn/1-match')")
    f.wait("location.pathname === '/learn/1-match' && document.querySelector('[data-needs-account]')", timeout=20, what="lesson 2's wall")
    dismiss_title(f)
    expected = f.js("(async () => (await import('/js/next-up.js')).nextUpLine('1-match'))()")
    seen = f.js("document.querySelector('[data-wall-next]')?.textContent.trim() ?? null")
    report.add("wall names what waits ([data-wall-next]), in the language", bool(seen) and seen == expected and not english_in([seen]), {"seen": seen, "english": english_in([seen or ""])})
    can_share = f.js("typeof navigator.share === 'function'")
    screens.look("wall")
    if can_share:
        f.run("window.__shared = null; navigator.share = (data) => { window.__shared = data; return Promise.resolve(); }")
        f.run("document.querySelector('[data-wall-send]').click()")
        f.wait("window.__shared", what="the share sheet asked")
        shared = f.js("window.__shared")
        report.add("Send myself the link: the shared title and text in the language", not english_in([shared.get("text", ""), shared.get("title", "")]), shared)
    f.run("try { delete Navigator.prototype.share; } catch (e) {} try { delete navigator.share; } catch (e) {} navigator.share = undefined;")
    f.run("(await import('/js/router.js')).navigate('/learn')")
    f.wait("location.pathname === '/learn'", what="the map")
    screens.look("course-map")
    f.run("(await import('/js/router.js')).navigate('/learn/1-match')")
    f.wait("document.querySelector('[data-needs-account] [data-wall-back]')", what="the wall again")
    cal = f.js("document.querySelector('[data-wall-calendar]')?.textContent.trim() ?? null")
    report.add("without a share sheet: Add tomorrow to my calendar", cal == t(f, "courses.wall.calendar"), cal)
    if cal:
        f.run("document.querySelector('[data-wall-calendar]').click()")
        f.wait("document.querySelectorAll('[data-wall-calendar-at]').length === 2", what="morning or evening")
        screens.look("wall-calendar")
        ics = f.js("(await import('/js/next-up.js')).calendarEntry('1-match', 'morning')")
        lines = [re.sub(r"^(SUMMARY|DESCRIPTION):", "", x).replace("\\n", " ").replace("\\,", ",") for x in ics.split("\r\n") if x.startswith(("SUMMARY:", "DESCRIPTION:"))]
        extra["calendar_entry"] = lines
        report.add("the calendar entry's title and description in the language", not english_in(lines), lines)
        f.run("document.querySelector('[data-wall-calendar-at=morning]').click()")
    c = counts_since(mark)
    steps["2 wall"] = c
    report.add("wall's way back counted (back_link_tap, back_cal_tap)", c.get("back_link_tap", 0) >= (1 if can_share else 0) and c.get("back_cal_tap", 0) >= 1, {k: c.get(k) for k in ("back_link_tap", "back_cal_tap")})

    # ---- 3. Make a free account from the wall; lesson 1 comes along ---------------------------------------------------
    mark = wire.next_mark()
    extra["mail"] = sign_up(f, report, screens, email)
    f.run("(await import('/js/router.js')).navigate('/learn/1-match')")
    f.wait(f"location.pathname === '/learn/1-match' && !document.querySelector('[data-needs-account]') && !!{VIEW}?.entry", timeout=30, what="lesson 2 open for the new member")
    dismiss_title(f)
    f.wait(f"document.documentElement.lang === {LANG!r}", timeout=10, what=f"still in {LANG} after signing up")
    f.wait("(async () => { await (await import('/js/stores/learning.js')).syncLearning(); return true; })()", timeout=30, what="the learning synced")
    theirs = f.js("(async () => { const { api } = await import('/js/api.js'); const d = (await api.account.learning.show()).data; return { lessons: Object.keys(d.progress?.lessons ?? {}), home: d.progress?.home ?? null }; })()")
    report.add("guest's lesson 1 kept on the account", "1-comfortable" in theirs["lessons"] and theirs["home"] is not None, theirs)
    screens.look("lesson2-member")
    c = counts_since(mark)
    steps["3 sign-up"] = c

    # ---- 4. Lessons 2 and 3 the same day: lesson 3's end names lesson 4 and wraps the session --------------------------
    mark = wire.next_mark()
    lesson_open(f, "1-match")
    walk_steps(f, "1-match")
    finish_scored(f)
    time.sleep(1)
    screens.look("lesson2-end")
    up = f.js("[...document.querySelectorAll('[data-end-next] p')].map((p) => p.textContent.trim()).find((x) => /\\bmin$/i.test(x)) ?? null")
    want_up = t(f, "results.upNextLesson", {"number": 3, "minutes": 0}).split("·")[0:2]
    report.add("lesson 2's end: the up-next line names lesson 3, in the language", bool(up) and not english_in([up]), {"seen": up, "t()": want_up, "english": english_in([up or ""])})
    f.run("document.querySelector('[data-end-next] .btn-primary').click()")
    lesson_open(f, "1-high-low")
    screens.look("lesson3-open")
    walk_steps(f, "1-high-low")
    finish_scored(f)
    time.sleep(1.5)
    up = f.js("[...document.querySelectorAll('[data-end-next] p')].map((p) => p.textContent.trim()).find((x) => /\\bmin$/i.test(x)) ?? null")
    report.add("lesson 3's end: the up-next line names lesson 4, in the language", bool(up) and not english_in([up]), {"seen": up, "english": english_in([up or ""])})
    push = f.js("'serviceWorker' in navigator && 'PushManager' in window && typeof Notification !== 'undefined'")
    f.wait("document.querySelector('[data-session-wrap]')", timeout=10, what="the session wrap")
    f.wait("document.querySelector('[data-wrap-keep]') || (async () => { await new Promise((r) => setTimeout(r, 4000)); return true; })()", timeout=10)
    nudge = f.js("document.querySelector('[data-wrap-nudge]')?.textContent.trim() ?? null")
    wrap = f.js("document.querySelector('[data-session-wrap]')?.innerText.replace(/\\s+/g, ' ') ?? ''")
    report.add("lesson 3's end: the session wrap, in the language", bool(wrap) and not english_in([wrap]), {"wrap": wrap, "english": english_in([wrap])})
    report.add("wrap: the nudge button (t('wrap.nudge')), in the language", (nudge == t(f, "wrap.nudge") and not english_in([nudge])) if push else None, {"button": nudge, "push_here": push})
    f.run("document.querySelector('[data-session-wrap]')?.scrollIntoView({ block: 'center' })")
    screens.look("lesson3-wrap")
    if nudge:
        f.chrome.send("Browser.grantPermissions", {"permissions": ["notifications"], "origin": f.base})
        f.run("document.querySelector('[data-wrap-nudge]').click()")
        f.wait("document.querySelector('[data-wrap-nudged], [data-wrap-keep] [role=alert]')", timeout=20, what="the nudge's answer")
        said = f.js("(document.querySelector('[data-wrap-nudged]') ?? document.querySelector('[data-wrap-keep] [role=alert]')).textContent.trim()")
        report.add("wrap: the nudge's answer, in the language", not english_in([said]), {"said": said, "english": english_in([said])})
        screens.look("lesson3-nudged")
    c = counts_since(mark)
    steps["4 lessons 2-3"] = c
    want = ["fl_s:1-match", "fl_d:1-match", "fl_s:1-high-low", "fl_d:1-high-low", "fs_r:1-match.0", "fs_r:1-high-low.0"]
    both = {k: steps["3 sign-up"].get(k, 0) + c.get(k, 0) for k in set(steps["3 sign-up"]) | set(c)}
    report.add("lessons 2 and 3 counted (fl_s, fs_r, fl_d)", all(k in both for k in want), {"want": want, "seen": both})
    report.add("wrap's ask counted shown (ask_nudge_shown)", ("ask_nudge_shown" in c) if nudge else None, {k: v for k, v in c.items() if k.startswith("ask_")})

    # ---- 5. A day later: coming back -----------------------------------------------------------------------------------
    mark = wire.next_mark()
    f.run("""const day = (d) => new Date(d - new Date(d).getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        const funnel = JSON.parse(localStorage.getItem('stackapella.funnel') || '{}');
        funnel.arrived = day(Date.now() - 86400000);
        localStorage.setItem('stackapella.funnel', JSON.stringify(funnel));
        localStorage.removeItem('stackapella.reach');
        localStorage.removeItem('stackapella.breadthDay');""")
    go(f, "/", "document.querySelector('#app header')")
    f.wait("(await import('/js/stores/account.js')).account.me", timeout=20, what="signed in after the reload")
    time.sleep(2)
    c = counts_since(mark)
    steps["5 day later"] = c
    br = {k: v for k, v in c.items() if k.startswith("br:")}
    report.add("a day later: app_day, back_d1, back_w1", all(k in c for k in ("app_day", "back_d1", "back_w1")), {k: c.get(k) for k in ("app_day", "back_d1", "back_w1", "back_d7")})
    report.add("a day later: br:<n> once", len(br) == 1 and sum(br.values()) == 1, {"br": br})
    report.add(f"a day later: still in {LANG} (reload, signed in)", f.js("document.documentElement.lang") == LANG, f.js("document.documentElement.lang"))
    screens.look("home-day2")
    f.run("(await import('/js/router.js')).navigate('/voice')")
    f.wait("location.pathname === '/voice' && !document.querySelector('[data-screen-loading]')", timeout=20, what="Your voice")
    time.sleep(1)
    screens.look("voice")
    f.run("(await import('/js/router.js')).navigate('/settings')")
    f.wait("location.pathname === '/settings' && !document.querySelector('[data-screen-loading]')", timeout=20, what="Settings")
    time.sleep(1)
    screens.look("settings")

    # ---- 6. Unit 1's end: its song --------------------------------------------------------------------------------------
    mark = wire.next_mark()
    f.run(f"""const {{ learning, finishLesson }} = {LEARNING};
        await finishLesson('1-breath', {{ percent: 92, stars: 3 }});""")
    f.run("localStorage.removeItem('stackapella.unitSong.counted')")
    f.run("(await import('/js/router.js')).navigate('/learn/1-challenge')")
    lesson_open(f, "1-challenge")
    if f.skin == "game":
        f.run("(await import('/js/game/effects.js')).effects.celebrations = false")
    walk_steps(f, "1-challenge")
    finish_scored(f)
    dismiss_title(f)
    try:
        f.wait("document.querySelector('[data-unit-song][data-place=u1]')", timeout=15, what="unit 1's song at its end")
        card = True
    except AssertionError:
        card = False
    report.add("unit 1's end: the unit's song card", card, {"end": re.sub(r"\s+", " ", f.js("document.querySelector('[data-end-next]')?.innerText ?? ''"))[:300]})
    screens.look("unit1-end", scroll_top=True)
    if card:
        f.run("document.querySelector('[data-unit-song]').scrollIntoView({ block: 'center' })")
    screens.look("unit-song-card")
    if card:
        f.run("document.querySelector('[data-unit-song-open]').click()")
        f.wait("/^\\/p\\/[^/]+\\/sing$/.test(location.pathname)", timeout=30, what="Sing")
        try:
            f.wait("(async () => { const { session } = await import('/js/stores/session.js'); return session.project?.course?.unitSong && session.focusPart?.templatePartKey === session.project.course.partKey && session.practice?.sing?.way === 'parts'; })()", timeout=30, what="Sing on the tune, part by part")
            on_part = True
        except AssertionError:
            on_part = False
        report.add("the card opens Sing straight on your part (unitSong)", on_part, None)
        time.sleep(1)
        screens.look("unit-song-sing")
        lane = f.js("[...document.querySelectorAll('canvas[aria-label], [data-lane] [aria-label], [data-lyric]')].map((e) => e.getAttribute('aria-label') || e.textContent.trim()).filter(Boolean).slice(0, 12)")
        extra["sing_lane_labels"] = lane
        had_back = f.js("!!document.querySelector('[data-lesson-back]')")
        f.run("const { session } = await import('/js/stores/session.js'); session.practice.sungSeconds = 20;")
        try:
            f.wait("document.querySelector('[data-lesson-back]')", timeout=10, what="Back to the lesson")
            back_ok = not had_back
        except AssertionError:
            back_ok = False
        report.add("Back to the lesson ([data-lesson-back]) once 20 s are sung", back_ok, {"after": f.js("document.querySelector('[data-lesson-back]')?.innerText.trim() ?? null")})
        screens.look("unit-song-back")
        kept = f.js("""(async () => { const { projects, openProject, savePart, loadProjects } = await import('/js/stores/projects.js');
            await loadProjects();
            const p = projects.all.find((x) => x.course?.unitSong && x.template?.id === 'michael-row');
            if (!p) return null;
            const { parts } = await openProject(p.id);
            const part = parts.find((x) => x.templatePartKey === p.course.partKey);
            await savePart({ ...part, frames: 48000, sampleRate: 48000 }, new Float32Array(48000).map((_, i) => Math.sin(i / 20) * 0.2));
            return { count: parts.length, key: part.templatePartKey, id: p.id }; })()""")
        report.add("a take kept on the unit song's part (savePart; not sung over loopback)", kept is not None, kept)
        go(f, "/", "document.querySelector('#app header')")
        try:
            f.wait("document.querySelector('[data-parts-you]')", timeout=20, what="Home's Your songs card with parts-you")
            line = f.js("document.querySelector('[data-parts-you]').textContent.trim()")
        except AssertionError:
            line = None
        want_line = t(f, "home.yours.partsYou", {"mine": 1, "count": (kept or {}).get("count", 4)})
        report.add("Home's Your songs: 1 of 4 parts is you (t('home.yours.partsYou')), in the language", line == want_line and not english_in([line or ""]), {"seen": line, "expected": want_line})
        if line:
            f.run("document.querySelector('[data-parts-you]').closest('li').scrollIntoView({ block: 'center', inline: 'center' })")
        screens.look("home-parts-you")
    c = counts_since(mark)
    steps["6 unit song"] = c
    report.add("unit end counted (fl_d:1-challenge, unit_done, us_shown:u1, us_tap:u1)", all(k in c for k in ("fl_d:1-challenge", "unit_done", "us_shown:u1", "us_tap:u1")), {k: v for k, v in c.items() if k.startswith(("fl_", "unit", "ft:", "lesson", "take", "song", "us_", "f_unit"))})

    # ---- 7. Past week one: Home's Today line and its picks' tags --------------------------------------------------------
    mark = wire.next_mark()
    f.run(f"""const {{ learning }} = {LEARNING}; const db = await import('/js/db.js');
        const day = (d) => new Date(d - new Date(d).getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        learning.progress.startedAt = Date.now() - 9 * 86400000;
        await db.put('learning', JSON.parse(JSON.stringify(learning.progress)), 'progress');
        const funnel = JSON.parse(localStorage.getItem('stackapella.funnel') || '{{}}');
        funnel.arrived = day(Date.now() - 9 * 86400000);
        localStorage.setItem('stackapella.funnel', JSON.stringify(funnel));""")
    go(f, "/", "document.querySelector('#app header')")
    f.wait("document.fonts.status === 'loaded' && !document.querySelector('[data-screen-loading]')", timeout=20, what="Home settled")
    time.sleep(3)
    tags = "[...document.querySelectorAll('[data-news-tag]')].map((e) => ({ kind: e.dataset.news, text: e.textContent.trim() }))"
    tag = f.js(tags)
    if not tag:
        screens.look("home-week2-no-tag", scroll_top=True)
        f.run(f"await {LEARNING}.setRange(50, 69)")
        go(f, "/", "document.querySelector('#app header')")
        f.wait("document.fonts.status === 'loaded' && !document.querySelector('[data-screen-loading]')", timeout=20, what="Home settled")
        time.sleep(3)
        tag = f.js(tags)
    today = f.js("(() => { const e = document.querySelector('[data-today]'); return e ? e.innerText.replace(/\\s+/g, ' ') : null; })()")
    report.add("past week one: Home's Today line, in the language", (bool(today) and not english_in([today])) if today else None, {"today": today, "english": english_in([today or ""])})
    report.add("past week one: a New / Carol season tag, in the language", (not english_in([x["text"] for x in tag])) if tag else None, tag)
    screens.look("home-week2", scroll_top=True)
    f.run("window.scrollTo(0, document.body.scrollHeight)")
    screens.look("home-week2-bottom")
    c = counts_since(mark)
    steps["7 past week one"] = c

    # ---- 8. Lesson 4 words: its song sung inside the lesson -------------------------------------------------------------
    mark = wire.next_mark()
    errors_before = len(f.chrome.errors)
    f.run(f"""const {{ learning, finishLesson }} = {LEARNING};
        for (const u of learning.course.units.slice(0, 3)) for (const l of u.lessons) if (!learning.progress.lessons[l.id]?.stars) await finishLesson(l.id, {{ percent: 90, stars: 3 }});""")
    f.run("(await import('/js/router.js')).navigate('/learn/4-words')")
    lesson_open(f, "4-words")
    screens.look("words-open")
    walk_steps(f, "4-words")
    try:
        f.wait(f"(() => {{ const v = {VIEW}; return v?.current?.kind === 'play' && (!v.song || !!v.stepExercise); }})()", timeout=30, what="4-words' song step ready")
        ready = True
    except AssertionError:
        ready = False
    step = f.js(f"(() => {{ const v = {VIEW}; return {{ kind: v?.current?.kind, notes: v?.stepExercise?.notes?.length ?? null, inSing: location.pathname, lyrics: (v?.stepExercise?.notes ?? []).map((n) => n.lyric).filter(Boolean).slice(0, 16) }}; }})()")
    extra["words_lyrics"] = step.get("lyrics")
    screens.look("words-song-step")
    played = False
    try:
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        f.run("document.querySelector('[data-play-disc]').closest('button').click()")
        time.sleep(0.8)
        if f.js("!!document.querySelector('[data-headphones]')"):
            screens.look("words-headphones")
            f.click_t("headphones.on")
        stop = t(f, "studio.stop")
        f.wait(f"{AUDIO}.engine.playing || document.querySelector('[data-play-disc]')?.closest('button').innerText.includes({stop!r})", timeout=15, what="the song step running")
        played = True
        time.sleep(4)
        screens.look("words-song-running")
        f.wait(f"!{AUDIO}.engine.playing && !document.querySelector('[data-play-disc]')?.closest('button').innerText.includes({stop!r})", timeout=60, what="the song step's run ended")
        played = f.js("document.querySelector('main [role=status]')?.textContent.trim() ?? 'ran'")
        screens.look("words-song-result")
    except AssertionError:
        pass
    new_errors = [e for e in f.chrome.errors[errors_before:] if not any(x in e for x in f.expected_errors)]
    report.add("4-words: the song step runs inside the lesson without errors", ready and not new_errors and not step["inSing"].endswith("/sing"), {"step": step, "played": played, "errors": new_errors})
    walk_steps(f, "4-words", stop_before_last=False)
    f.wait(f"!!{VIEW}?.done", timeout=20, what="4-words' end")
    screens.look("words-end")
    c = counts_since(mark)
    steps["8 4-words"] = c
    report.add("4-words counted (fl_s, fs_r per step)", "fl_s:4-words" in c and "fs_r:4-words.0" in c and "fs_r:4-words.1" in c, {k: v for k, v in c.items() if "4-words" in k or k == "lesson_start"})
    report.add("no failed requests other than expected 401/419/404", not [x for x in wire.failed if not re.match(r"^(401|419|404) ", x)], wire.failed)

    # ---- Every screen: English, cut words, the pixel face -----------------------------------------------------------------
    leaked = [(s["screen"], e) for s in screens.rows for e in s["english"]]
    report.add(f"no English on the {len(screens.rows)} app screens walked", not leaked or "note", leaked[:60])
    report.add("no words cut or spilling, no sideways scroll", "note" if any(s["cut"] or s["sideways_px"] > 0 for s in screens.rows) else True,
               [(s["screen"], s["cut"], s["sideways_px"]) for s in screens.rows if s["cut"] or s["sideways_px"] > 0])
    if f.skin == "game":
        lacking = {s["screen"]: s["pixel"]["missing"] for s in screens.rows if s["pixel"] and s["pixel"]["missing"]}
        accents = "".join(sorted({ch for s in screens.rows if s["pixel"] for ch in s["pixel"]["accents"]}))
        report.add("pixel face: every character drawn in it is in it (accents included)", not lacking, {"lacking": lacking, "accents_drawn_in_pixel": accents})


SITE_PAGES = [("/es", "site-home"), ("/es/courses", "site-courses"), ("/es/courses/perfect-your-pitch", "site-course"),
              ("/es/songs", "site-songs"), ("/es/songs/michael-row", "site-song"), ("/es/pricing", "site-pricing"), ("/es/tools", "site-tools")]


def site(f, report, screens):
    """A few of the public site's /es pages, on the real server, read as the app's screens are."""
    for path, label in SITE_PAGES:
        f.chrome.goto(SITE + path, "document.readyState === 'complete' && !!document.querySelector('main')")
        time.sleep(1)
        screens.look(label, scroll_top=True)
        report.add(f"site {path}: in the language", f.js("document.documentElement.lang") == LANG and not screens.rows[-1]["english"] or "note",
                   {"lang": f.js("document.documentElement.lang"), "english": screens.rows[-1]["english"][:20]})
    # The song page's Sing link: where a Spanish singer lands.
    f.chrome.goto(SITE + "/es/songs/michael-row", "document.readyState === 'complete'")
    sing = f.js("[...document.querySelectorAll('a[href*=\"song=michael-row\"]')].map((a) => ({ href: a.getAttribute('href'), text: a.innerText.trim() }))")
    if sing:
        f.chrome.goto(SITE + sing[0]["href"], "document.readyState === 'complete'")
        time.sleep(3)
        landed = f.js("({ path: location.pathname + location.search, lang: document.documentElement.lang, app: !!document.querySelector('#app'), title: document.title })")
        screens.look("site-song-sing-link")
        # The same link on the English page, to compare where it lands.
        f.chrome.goto(SITE + "/songs/michael-row", "document.readyState === 'complete'")
        english = f.js("[...document.querySelectorAll('a[href*=\"song=michael-row\"]')].map((a) => a.getAttribute('href'))")
        english_landed = None
        if english:
            f.chrome.goto(SITE + english[0], "document.readyState === 'complete'")
            time.sleep(3)
            english_landed = f.js("({ path: location.pathname + location.search, lang: document.documentElement.lang, app: !!document.querySelector('#app'), title: document.title })")
        report.add("site: the song page's Sing link opens the song (in the language), as the English one does", "note",
                   {"links": sing, "landed": landed, "english_link": english, "english_landed": english_landed})


if __name__ == "__main__":
    run("first_week_real_es", journey, sizes=("phone",))
