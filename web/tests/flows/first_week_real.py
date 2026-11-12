"""A brand-new guest's first week, end to end against the REAL local server (API=http://127.0.0.1:<port>
tool/flows.sh first_week_real, with FLOW_SERVER set to that server's folder and its queue worker running): the
first-week changes of 2026-11-11 and 12 walked as one singer, phone size, in both skins (FLOW_SKINS=game,serious).

1. Lesson 1 sung (a tone over loopback finds the note): lesson_start, fl_s, fs_r per step, fl_d, ft:lesson counted.
2. Lesson 2 as a guest: the course wall names what waits ("Next: Lesson 2, ...") and offers one quiet way back
   (Send myself the link where the device can share, else Add tomorrow to my calendar), counted back_link_tap and
   back_cal_tap.
3. Make a free account from the wall, verified from the mail in the server's log: lesson 1 comes along to the account.
4. Lessons 2 and 3 as the new member, the same day: lesson 3's end names the next lesson ("Up next . Lesson 4") and
   carries the first session's wrap with "Nudge me tomorrow at about this time" (where push works).
5. A day later (the arrival day moved back, the day's reach and breadth records cleared): app_day, back_d1, back_w1,
   br:<n> once.
6. Unit 1's challenge with stars: its end offers Michael, Row the Boat Ashore; Sing opens on the tune; Back to the
   lesson once 20 s are sung; a take kept; Home says "1 of 4 parts is you".
7. Past week one: Home's Today line and any New or Carol season tag on its picks.
8. Lesson 4 words (4-words): its song step runs inside the lesson without errors.

Scores are not what this walks: where a sung step needs a voice, the lesson's own result is set as the step would set
it, and the lesson finished by its own finish(). Every count the page sends is read off the wire (CDP's Network domain,
the beacons to /api/counts as the server got them), and every failed request and console error is listed.

Each run makes one new account per skin (sign-ups: 5 an hour from one address). FIRST_WEEK_EMAIL (and the flow's
password) signs in an existing account instead, to rerun the later steps without spending sign-ups.
The results, as a table, go to tests/flows/shots/first_week_real-<skin>-results.json and to stdout.
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

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
LEARNING = "(await import('/js/stores/learning.js'))"
PASSWORD = "a-long-flow-password-2026"
SERVER = Path(os.environ.get("FLOW_SERVER", Path.home() / "Code/stackapella-backend/server"))
# The verification mail is a queued job on the shared dev database: whichever worker takes it writes it to its own
# log, so every checkout's log is read (only read).
LOGS = [SERVER / "storage/logs/laravel.log"] + [Path.home() / f"Code/{d}/server/storage/logs/laravel.log" for d in ("stackapella-backend", "stackapella-frontend", "stackapella")]

# The lesson view's own instance (as tests/flows/unit_song.py finds it): its step, result and finish().
VIEW = """(() => { const seen = new Set();
    const walk = (vnode) => { if (!vnode || typeof vnode !== 'object' || seen.has(vnode)) return null; seen.add(vnode);
        const c = vnode.component; if (c) { if (c.proxy && typeof c.proxy.openUnitSong === 'function') return c; const r = walk(c.subTree); if (r) return r; }
        for (const list of [vnode.children, vnode.dynamicChildren]) if (Array.isArray(list)) for (const ch of list) { const r = walk(ch); if (r) return r; }
        if (vnode.suspense) { const r = walk(vnode.suspense.activeBranch); if (r) return r; }
        return null; };
    return walk(document.querySelector('#app')?._vnode)?.proxy ?? null; })()"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


class Wire:
    """What went over the network, read from CDP's Network events: the count batches the server got, and every request
    that failed or answered 4xx/5xx."""

    def __init__(self, f):
        self.f = f
        self.batches = []  # (mark, payload)
        self.failed = []
        self.unread = []  # count batches whose body Chrome no longer had
        self.urls = {}
        self.mark = 0
        chrome = f.chrome
        before = chrome.on_event

        def on_event(message):
            method = message.get("method", "")
            params = message.get("params", {})
            if method == "Network.requestWillBeSent":
                request = params["request"]
                self.urls[params["requestId"]] = (request["method"], request["url"])
                if request["url"].endswith("/api/counts") and request["method"] == "POST":
                    # A beacon's Blob body isn't in the event: asked for at once, while Chrome still holds it.
                    body = request.get("postData")
                    if body is None:
                        try:
                            body = chrome.send("Network.getRequestPostData", {"requestId": params["requestId"]}).get("postData")
                        except RuntimeError as error:
                            body = None
                            self.unread.append(str(error)[:200])
                    try:
                        self.batches.append((self.mark, json.loads(body or "{}")))
                    except ValueError:
                        self.batches.append((self.mark, {"unreadable": body}))
            elif method == "Network.responseReceived":
                status = params["response"]["status"]
                if status >= 400:
                    verb, url = self.urls.get(params["requestId"], ("?", params["response"]["url"]))
                    self.failed.append(f"{status} {verb} {url}")
            elif method == "Network.loadingFailed" and not params.get("canceled"):
                verb, url = self.urls.get(params["requestId"], ("?", "?"))
                if params.get("errorText") != "net::ERR_ABORTED":
                    self.failed.append(f"{params.get('errorText')} {verb} {url}")
            before(message)

        chrome.on_event = on_event
        chrome.send("Network.enable", {"maxPostDataSize": 65536})

    def flush(self):
        """Everything queued in the page sent now, and its beacon seen."""
        try:
            self.f.run("(await import('/js/usage.js')).send()")
        except RuntimeError:
            pass
        time.sleep(1.2)
        self.f.js("1")  # reads the events that came meanwhile

    def kinds(self, since=None):
        """Every kind sent since mark `since` (all if None), with its total."""
        out = {}
        for mark, payload in self.batches:
            if since is not None and mark < since:
                continue
            for e in payload.get("events", []):
                out[e["kind"]] = out.get(e["kind"], 0) + e.get("n", 1)
        return out

    def next_mark(self):
        self.flush()
        self.mark += 1
        return self.mark


class Report:
    def __init__(self, f):
        self.f = f
        self.rows = []

    def add(self, item, ok, evidence):
        status = "note" if ok == "note" else "works" if ok is True else "broken" if ok is False else "couldn't check"
        self.rows.append({"item": item, "status": status, "evidence": evidence})
        print(f"     [{self.f.skin}] {status:14} {item}: {str(evidence)[:400]}")
        return ok


def dismiss_title(f):
    """Game mode's lesson title card (it's over the page while it plays) ends on a tap."""
    for _ in range(3):
        if f.js("!!document.querySelector('[data-game-title]')"):
            f.run("document.querySelector('[data-game-title]').click()")
            time.sleep(0.4)


def lesson_open(f, lesson_id):
    f.wait(f"location.pathname === '/learn/{lesson_id}' && !!{VIEW} && {VIEW}.entry?.lesson.id === {lesson_id!r}", timeout=30, what=f"lesson {lesson_id} open")
    dismiss_title(f)


def walk_steps(f, lesson_id, stop_before_last=True):
    """Every step of the open lesson by its own way on (Continue) or Skip, up to its last step (or past it)."""
    total = f.js(f"{VIEW}.entry.lesson.steps.length")
    for _ in range(total * 3):
        step = f.js(f"{VIEW}?.step")
        if f.js(f"!!{VIEW}?.done"):
            return total
        if stop_before_last and step == total - 1:
            return total
        dismiss_title(f)
        f.wait("document.querySelector('[data-go-on]:not(:disabled), [data-skip]')", what=f"{lesson_id}'s way on at step {step}")
        f.run("const b = document.querySelector('[data-go-on]:not(:disabled)') ?? document.querySelector('[data-skip]'); b.click();")
        f.wait(f"{VIEW}?.step !== {step} || !!{VIEW}?.done", what=f"{lesson_id} past step {step}")
    return total


def finish_scored(f, percent=92):
    """The lesson's last scored step given a result as the step would keep it, then the lesson's own finish()."""
    f.run(f"""const view = {VIEW}; const steps = view.entry.lesson.steps;
        const at = steps.findLastIndex((s) => ['play', 'record', 'use'].includes(s.kind) || s.exercise);
        const result = {{ percent: {percent}, chart: null }};
        if (at >= 0) view.kept.results[at] = result;
        view.result = result;
        await view.finish();""")
    f.wait(f"!!{VIEW}?.done", what="the lesson's end")


def sign_up(f, report, wire, email):
    """Make a free account from the wall: the sign-up form, the mail's link from a server log, verified, signed in."""
    sizes = {log: (log.stat().st_size if log.exists() else 0) for log in LOGS}
    f.run("document.querySelector('[data-needs-account-yes]').click()")
    f.wait("location.pathname === '/signin' && document.querySelector('form input[type=email]')", what="sign-up")
    query = f.js("Object.fromEntries(new URLSearchParams(location.search))")
    report.add("wall: Make a free account opens sign-up for the course, back to lesson 2", query == {"mode": "signup", "for": "course", "next": "/learn/1-match"}, query)
    if os.environ.get("FIRST_WEEK_EMAIL"):
        f.go("/signin?next=/learn/1-match", "document.querySelector('form')")
        f.type("input[type=email]", email)
        f.run("document.querySelector('form').requestSubmit()")
        f.wait("document.querySelector('input[autocomplete=current-password]')", what="the password step")
        f.type("input[autocomplete=current-password]", PASSWORD)
        f.run("document.querySelector('form').requestSubmit()")
        f.wait(f"(await import('/js/stores/account.js')).account.me?.email === {email!r}", timeout=20, what="signed in (FIRST_WEEK_EMAIL)")
        report.add("sign-up and verify (skipped: FIRST_WEEK_EMAIL signed in instead)", None, email)
        return
    if f.js("!!document.querySelector('input[autocomplete=name]')"):
        f.type("input[autocomplete=name]", "First Week")
    f.type("input[type=email]", email)
    f.type("input[autocomplete=new-password]", PASSWORD)
    f.run("const s = document.querySelectorAll('form fieldset select'); if (s.length >= 2) { s[0].value = '4'; s[0].dispatchEvent(new Event('change')); s[1].value = '1990'; s[1].dispatchEvent(new Event('change')); }")
    time.sleep(2.5)  # a form sent in under 2 seconds is taken for a bot
    f.run("document.querySelector('form').requestSubmit()")
    f.wait_text(t(f, "auth.sent.title"), timeout=20)
    f.shot("signup-sent")
    deadline = time.time() + 90
    token = None
    while time.time() < deadline and not token:
        for log, before in sizes.items():
            if not log.exists():
                continue
            text = log.read_bytes()[before:].decode(errors="replace")
            if email in text:
                found = re.findall(r"/verify\?token=([A-Za-z0-9._~%-]+)", text[text.index(email):])
                if found:
                    token = found[0]
                    report.add("verification mail written to a server log", True, str(log))
                    break
        time.sleep(0.5)
    if not token:
        report.add("verification mail written to a server log", False, f"none for {email} in 90 s in {[str(x) for x in LOGS]}")
        raise AssertionError("no verification mail")
    f.go(f"/verify?token={token}", "document.querySelector('#app')")
    f.wait(f"(await import('/js/stores/account.js')).account.me?.email === {email!r}", timeout=30, what="signed in, verified")
    f.shot("verified")


def journey(f):
    report = Report(f)
    wire = Wire(f)
    # Errors this walk causes on purpose: signed out (401), a first CSRF token retried (419), optional lookups (404).
    f.expected_errors += ["401 (Unauthorized) http://127.0.0.1", "419 (Unknown Status Code) http://127.0.0.1", "404 (Not Found) http://127.0.0.1"]
    email = os.environ.get("FIRST_WEEK_EMAIL") or f"e2e-{f.skin}-{int(time.time())}-{random.randint(100, 999)}@example.com"
    serious = f.skin == "serious"
    steps = {}

    def counts_since(mark):
        wire.flush()
        return wire.kinds(mark)

    try:
        _journey(f, report, wire, email, serious, counts_since, steps)
    finally:
        wire.flush()
        out = {"skin": f.skin, "email": email, "rows": report.rows, "counts_by_step": steps, "all_counts": wire.kinds(),
               "batches": [p for _, p in wire.batches], "failed_requests": wire.failed, "count_batches_unread": wire.unread, "console_errors": list(f.chrome.errors),
               "console_warnings": list(getattr(f.chrome, "warnings", []))[:60]}
        SHOTS.mkdir(exist_ok=True)
        (SHOTS / f"first_week_real-{f.skin}-results.json").write_text(json.dumps(out, indent=2))
    broken = [r for r in report.rows if r["status"] == "broken"]
    if broken:
        raise AssertionError(f"{len(broken)} broken: " + "; ".join(r["item"] for r in broken))


def _journey(f, report, wire, email, serious, counts_since, steps):
    # ---- 1. Arrive, the mic checked, lesson 1 sung --------------------------------------------------------------------
    mark = wire.mark
    f.go("/", "document.querySelector('#app header')")
    f.wait("(await import('/js/stores/account.js')).account.config", what="the server's config")
    report.add("server config: usage counts on", f.js("(await import('/js/stores/account.js')).account.config.usageCounts") is True, "account.config.usageCounts")
    f.mic_checked()
    f.shot("home-guest")
    if f.js("!!document.querySelector('[data-press-start]')"):
        f.run("document.querySelector('[data-press-start]').click()")
    else:
        f.click_t("course.start")
    f.wait("location.pathname === '/learn/1-comfortable'", what="lesson 1")
    lesson_open(f, "1-comfortable")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    walk_steps(f, "1-comfortable")  # the Learn card, on to finding the note (its last step)
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening for the note")
    f.run(f"const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js'); engine.preview(renderPart([{{ beat: 0, length: 6, pitch: 57 }}], {{ bpm: 60, sampleRate: engine.sampleRate }}), engine.sampleRate);")
    f.wait("document.querySelector('[data-note]')?.textContent === 'A3'", timeout=25, what="the note found")
    f.click_t("course.finish")
    f.wait(f"!!{VIEW}?.done", what="lesson 1's end")
    time.sleep(1.5)
    end_text = f.js("document.querySelector('main')?.innerText ?? ''")
    up_next = t(f, "results.upNextLesson", {"number": 2, "minutes": 0}).split("·")[0].strip()
    has_end = f.js("!!document.querySelector('[data-lesson-end]')")
    named = re.search(r"Lesson 2", end_text) is not None
    report.add("lesson 1's end names lesson 2 (results.upNextLesson)", True if named else "note",
               {"lesson_end_present": has_end, "note_result_present": f.js("!!document.querySelector('[data-note-result]')"),
                "text": re.sub(r"\s+", " ", end_text)[:300], "note": "lesson 1 finds the note: its end is NoteResult, not LessonEnd" if not has_end else ""})
    f.shot("lesson1-end")
    c = counts_since(mark)
    steps["1 lesson 1"] = c
    want = {"lesson_start": 1, "fl_s:1-comfortable": 1, "fs_r:1-comfortable.0": 1, "fs_r:1-comfortable.1": 1, "fl_d:1-comfortable": 1, "ft:lesson": 1}
    report.add("lesson 1 counted (lesson_start, fl_s, fs_r per step, fl_d, ft:lesson)", all(c.get(k) == v for k, v in want.items()), {"want": want, "seen": c})
    if any(k.startswith("br:") for k in c):
        report.add("day 1: breadth already counted on the first open", "note", {k: v for k, v in c.items() if k.startswith(("br:", "ft:"))})
    report.add("first open counted app_open/app_day", "app_day" in c and "app_open" in c, {k: c.get(k) for k in ("app_open", "app_day", "app_week", "app_month") if k in c})

    # ---- 2. Lesson 2 as a guest: the wall, what waits, one way back --------------------------------------------------
    mark = wire.next_mark()
    f.run("(await import('/js/router.js')).navigate('/learn/1-match')")
    f.wait("location.pathname === '/learn/1-match' && document.querySelector('[data-needs-account]')", timeout=20, what="lesson 2's wall")
    dismiss_title(f)
    expected = f.js("(async () => (await import('/js/next-up.js')).nextUpLine('1-match'))()")
    seen = f.js("document.querySelector('[data-wall-next]')?.textContent.trim() ?? null")
    report.add("wall names what waits ([data-wall-next])", bool(seen) and seen == expected and "Lesson 2" in (seen or ""), {"seen": seen, "expected": expected})
    back = f.js("[...document.querySelectorAll('[data-wall-back] button')].map((b) => ({ text: b.textContent.trim(), cls: b.className, data: Object.keys(b.dataset) }))")
    can_share = f.js("typeof navigator.share === 'function'")
    one_link = len(back) == 1 and "btn-" not in back[0]["cls"]
    report.add("wall: ONE text link in [data-wall-back]", one_link, {"navigator.share": can_share, "links": back})
    f.shot("wall")
    if can_share:
        f.run("window.__shared = null; navigator.share = (data) => { window.__shared = data; return Promise.resolve(); }")
        f.run("document.querySelector('[data-wall-send]').click()")
        f.wait("window.__shared", what="the share sheet asked")
        shared = f.js("window.__shared")
        report.add("Send myself the link: shares the lesson's address", "/learn/1-match?ref=back-link" in (shared or {}).get("url", ""), shared)
    # The other face (a device without a share sheet): the calendar, morning or evening. The wall made again without it.
    f.run("try { delete Navigator.prototype.share; } catch (e) {} try { delete navigator.share; } catch (e) {} navigator.share = undefined;")
    f.run("(await import('/js/router.js')).navigate('/learn')")
    f.wait("location.pathname === '/learn'", what="the map")
    f.run("(await import('/js/router.js')).navigate('/learn/1-match')")
    f.wait("document.querySelector('[data-needs-account] [data-wall-back]')", what="the wall again")
    cal = f.js("document.querySelector('[data-wall-calendar]')?.textContent.trim() ?? null")
    report.add("without a share sheet: Add tomorrow to my calendar", cal == t(f, "courses.wall.calendar"), cal)
    if cal:
        f.run("document.querySelector('[data-wall-calendar]').click()")
        f.wait("document.querySelectorAll('[data-wall-calendar-at]').length === 2", what="morning or evening")
        f.shot("wall-calendar")
        f.run("document.querySelector('[data-wall-calendar-at=morning]').click()")
    c = counts_since(mark)
    steps["2 wall"] = c
    report.add("wall's way back counted (back_link_tap, back_cal_tap)", c.get("back_link_tap", 0) >= (1 if can_share else 0) and c.get("back_cal_tap", 0) >= 1, {k: c.get(k) for k in ("back_link_tap", "back_cal_tap")})
    report.add("the wall doesn't count lesson 2 as started (a guest who can't open it)", not any(k in c for k in ("lesson_start", "fl_s:1-match")) and not any(k.startswith("fs_r:1-match") for k in c), {k: v for k, v in c.items() if k in ("lesson_start", "fl_s:1-match") or k.startswith("fs_r:")})

    # ---- 3. Make a free account from the wall; lesson 1 comes along ---------------------------------------------------
    mark = wire.next_mark()
    sign_up(f, report, wire, email)
    f.run("(await import('/js/router.js')).navigate('/learn/1-match')")
    f.wait(f"location.pathname === '/learn/1-match' && !document.querySelector('[data-needs-account]') && !!{VIEW}?.entry", timeout=30, what="lesson 2 open for the new member")
    dismiss_title(f)
    f.wait("(async () => { await (await import('/js/stores/learning.js')).syncLearning(); return true; })()", timeout=30, what="the learning synced")
    theirs = f.js("(async () => { const { api } = await import('/js/api.js'); const d = (await api.account.learning.show()).data; return { lessons: Object.keys(d.progress?.lessons ?? {}), home: d.progress?.home ?? null, epoch: d.epoch ?? null }; })()")
    here = f.js(f"Object.keys({LEARNING}.learning.progress.lessons ?? {{}})")
    report.add("guest's lesson 1 kept on the account", "1-comfortable" in theirs["lessons"] and theirs["home"] is not None, {"account": theirs, "device": here})
    f.shot("lesson2-member")
    c = counts_since(mark)
    steps["3 sign-up"] = c

    # ---- 4. Lessons 2 and 3 the same day: lesson 3's end names lesson 4 and wraps the session --------------------------
    mark = wire.next_mark()
    lesson_open(f, "1-match")
    walk_steps(f, "1-match")
    finish_scored(f)
    time.sleep(1)
    f.shot("lesson2-end")
    up = f.js("[...document.querySelectorAll('[data-end-next] p')].map((p) => p.textContent.trim()).find((x) => / min$/i.test(x)) ?? null")
    report.add("lesson 2's end: Up next . Lesson 3 . N min", bool(up) and "Lesson 3" in up and "min" in up, up)
    f.run("document.querySelector('[data-end-next] .btn-primary').click()")
    lesson_open(f, "1-high-low")
    walk_steps(f, "1-high-low")
    finish_scored(f)
    time.sleep(1.5)
    up = f.js("[...document.querySelectorAll('[data-end-next] p')].map((p) => p.textContent.trim()).find((x) => / min$/i.test(x)) ?? null")
    report.add("lesson 3's end: Up next . Lesson 4 . N min", bool(up) and "Lesson 4" in up and "min" in up, up)
    push = f.js("'serviceWorker' in navigator && 'PushManager' in window && typeof Notification !== 'undefined'")
    f.wait("document.querySelector('[data-session-wrap]')", timeout=10, what="the session wrap")
    f.wait("document.querySelector('[data-wrap-keep]') || (async () => { await new Promise((r) => setTimeout(r, 4000)); return true; })()", timeout=10)
    ask = f.js("document.querySelector('[data-wrap-keep]')?.dataset.ask ?? null")
    nudge = f.js("document.querySelector('[data-wrap-nudge]')?.textContent.trim() ?? null")
    report.add("lesson 3's end (day 1, signed in): the session wrap", f.js("!!document.querySelector('[data-session-wrap]')"), f.js("document.querySelector('[data-session-wrap]')?.innerText.replace(/\\s+/g, ' ')"))
    report.add("wrap: Nudge me tomorrow at about this time", (nudge == t(f, "wrap.nudge")) if push else None, {"ask": ask, "button": nudge, "push_here": push})
    f.run("document.querySelector('[data-session-wrap]')?.scrollIntoView({ block: 'center' })")
    f.shot("lesson3-wrap")
    if nudge:
        f.chrome.send("Browser.grantPermissions", {"permissions": ["notifications"], "origin": f.base})
        f.run("document.querySelector('[data-wrap-nudge]').click()")
        f.wait("document.querySelector('[data-wrap-nudged], [data-wrap-keep] [role=alert]')", timeout=20, what="the nudge's answer")
        said = f.js("(document.querySelector('[data-wrap-nudged]') ?? document.querySelector('[data-wrap-keep] [role=alert]')).textContent.trim()")
        report.add("wrap: tapping the nudge sets a reminder (headless push may refuse)", True if f.js("!!document.querySelector('[data-wrap-nudged]')") else None, said)
        f.shot("lesson3-nudged")
    c = counts_since(mark)
    steps["4 lessons 2-3"] = c
    want = ["fl_s:1-match", "fl_d:1-match", "fl_s:1-high-low", "fl_d:1-high-low", "fs_r:1-match.0", "fs_r:1-high-low.0"]
    both = {k: steps["3 sign-up"].get(k, 0) + c.get(k, 0) for k in set(steps["3 sign-up"]) | set(c)}  # lesson 2 starts as it opens after sign-up
    report.add("lessons 2 and 3 counted (fl_s, fs_r, fl_d; lesson 2's start counted as it opened after sign-up)", all(k in both for k in want), {"want": want, "seen": both})
    report.add("unit 1 not counted done at lesson 3 (f_unit1, unit_done)", not any(k in c for k in ("f_unit1", "unit_done")), {k: c.get(k) for k in ("f_unit1", "unit_done")})
    report.add("wrap's ask counted shown (ask_nudge_shown)", ("ask_nudge_shown" in c) if nudge else None, {k: v for k, v in c.items() if k.startswith("ask_")})

    # ---- 5. A day later: coming back -----------------------------------------------------------------------------------
    mark = wire.next_mark()
    f.run("""const day = (d) => new Date(d - new Date(d).getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        const funnel = JSON.parse(localStorage.getItem('stackapella.funnel') || '{}');
        funnel.arrived = day(Date.now() - 86400000);
        localStorage.setItem('stackapella.funnel', JSON.stringify(funnel));
        localStorage.removeItem('stackapella.reach');
        localStorage.removeItem('stackapella.breadthDay');""")
    breadth_before = f.js("JSON.parse(localStorage.getItem('stackapella.tried') || 'null')")
    f.go("/", "document.querySelector('#app header')")
    f.wait("(await import('/js/stores/account.js')).account.me", timeout=20, what="signed in after the reload")
    time.sleep(2)
    c = counts_since(mark)
    steps["5 day later"] = c
    br = {k: v for k, v in c.items() if k.startswith("br:")}
    report.add("a day later: app_day, back_d1, back_w1", all(k in c for k in ("app_day", "back_d1", "back_w1")), {k: c.get(k) for k in ("app_day", "back_d1", "back_w1", "back_d7")})
    tried = f.js("JSON.parse(localStorage.getItem('stackapella.tried') || 'null')")
    report.add("a day later: br:<n> once (the tried set filled in)", len(br) == 1 and sum(br.values()) == 1, {"br": br, "tried": tried, "tried_before": breadth_before})
    f.shot("home-day2")

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
    title = f.js("document.querySelector('[data-unit-song] p')?.textContent.trim() ?? null")
    report.add("unit 1's end: the unit's song card (Michael, Row the Boat Ashore)", card, {"title": title, "end": re.sub(r"\s+", " ", f.js("document.querySelector('[data-end-next]')?.innerText ?? ''"))[:300]})
    if card:
        f.run("document.querySelector('[data-unit-song]').scrollIntoView({ block: 'center' })")
    f.shot("unit-song-card")
    if card:
        f.run("document.querySelector('[data-unit-song-open]').click()")
        f.wait("/^\\/p\\/[^/]+\\/sing$/.test(location.pathname)", timeout=30, what="Sing")
        try:
            f.wait("(async () => { const { session } = await import('/js/stores/session.js'); return session.project?.course?.unitSong && session.focusPart?.templatePartKey === session.project.course.partKey && session.practice?.sing?.way === 'parts'; })()", timeout=30, what="Sing on the tune, part by part")
            on_part = True
        except AssertionError:
            on_part = False
        state = f.js("(async () => { const { session } = await import('/js/stores/session.js'); return { course: JSON.parse(JSON.stringify(session.project?.course ?? null)), focus: session.focusPart?.templatePartKey ?? null, way: session.practice?.sing?.way ?? null, template: session.project?.template?.id ?? null }; })()")
        report.add("the card opens Sing straight on your part (unitSong)", on_part, state)
        f.shot("unit-song-sing")
        had_back = f.js("!!document.querySelector('[data-lesson-back]')")
        f.run("const { session } = await import('/js/stores/session.js'); session.practice.sungSeconds = 20;")
        try:
            f.wait("document.querySelector('[data-lesson-back]')", timeout=10, what="Back to the lesson")
            back_ok = not had_back
        except AssertionError:
            back_ok = False
        report.add("Back to the lesson ([data-lesson-back]) once 20 s are sung", back_ok, {"before": had_back, "after": f.js("document.querySelector('[data-lesson-back]')?.innerText.trim() ?? null")})
        f.shot("unit-song-back")
        # A take kept on your part (as unit_song.py keeps one: a second of sound, not a real recording over loopback).
        kept = f.js("""(async () => { const { projects, openProject, savePart, loadProjects } = await import('/js/stores/projects.js');
            await loadProjects();
            const p = projects.all.find((x) => x.course?.unitSong && x.template?.id === 'michael-row');
            if (!p) return null;
            const { parts } = await openProject(p.id);
            const part = parts.find((x) => x.templatePartKey === p.course.partKey);
            await savePart({ ...part, frames: 48000, sampleRate: 48000 }, new Float32Array(48000).map((_, i) => Math.sin(i / 20) * 0.2));
            return { count: parts.length, key: part.templatePartKey, id: p.id }; })()""")
        report.add("a take kept on the unit song's part (savePart; not sung over loopback)", kept is not None, kept)
        f.go("/", "document.querySelector('#app header')")
        try:
            f.wait("document.querySelector('[data-parts-you]')", timeout=20, what="Home's Your songs card with parts-you")
            line = f.js("document.querySelector('[data-parts-you]').textContent.trim()")
        except AssertionError:
            line = None
        want_line = t(f, "home.yours.partsYou", {"mine": 1, "count": (kept or {}).get("count", 4)})
        report.add("Home's Your songs: \"1 of 4 parts is you\"", line == want_line and "1 of 4" in (line or ""), {"seen": line, "expected": want_line})
        if line:
            f.run("document.querySelector('[data-parts-you]').closest('li').scrollIntoView({ block: 'center', inline: 'center' })")
        f.shot("home-parts-you")
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
        localStorage.setItem('stackapella.funnel', JSON.stringify(funnel));
        window.__rangeBefore = JSON.stringify(learning.progress.range ?? null);""")
    f.go("/", "document.querySelector('#app header')")
    f.wait("document.fonts.status === 'loaded' && !document.querySelector('[data-screen-loading]')", timeout=20, what="Home settled")
    time.sleep(3)
    today = f.js("(() => { const e = document.querySelector('[data-today]'); return e ? { kase: e.dataset.today, text: e.innerText.replace(/\\s+/g, ' ') } : null; })()")
    tags = "[...document.querySelectorAll('[data-news-tag]')].map((e) => ({ kind: e.dataset.news, text: e.textContent.trim(), song: e.closest('[data-song-card]')?.innerText.split('\\n').map((x) => x.trim()).filter((x) => x && x !== e.textContent.trim())[0] }))"
    tag = f.js(tags)
    range_before = f.js(f"JSON.stringify({LEARNING}.learning.progress.range ?? null)")
    tag_wide = None
    if not tag:
        # The singer's range is only their note (a new singer who never measured it): nothing fits. A measured range
        # (as the range check keeps it), to see whether the tag shows at all.
        f.shot("home-week2-no-tag")
        f.run(f"await {LEARNING}.setRange(50, 69)")
        f.go("/", "document.querySelector('#app header')")
        f.wait("document.fonts.status === 'loaded' && !document.querySelector('[data-screen-loading]')", timeout=20, what="Home settled")
        time.sleep(3)
        tag_wide = f.js(tags)
    daily = f.js("[...document.querySelectorAll('[data-song-card][data-daily]')].map((e) => e.innerText.replace(/\\s+/g, ' ').slice(0, 80))")
    report.add("past week one: Home's Today line ([data-today])", bool(today) or None, {"today": today, "daily_card": daily})
    report.add("past week one: a New / Carol season tag on Home's picks, with the range a new singer has", bool(tag) or None, {"tags": tag, "range": range_before, "note": "" if tag else "no tag: the only range known is the note from lesson 1, so no song fits"})
    if tag_wide is not None:
        report.add("past week one: the tag with a measured range (C3 to A4 set)", bool(tag_wide), {"tags": tag_wide})
        tag = tag_wide
    f.shot("home-week2")
    c = counts_since(mark)
    steps["7 past week one"] = c
    report.add("Home's tag counted (home_new_shown) when shown", ("home_new_shown" in c) if tag else None, {k: v for k, v in c.items() if k.startswith("home_")})

    # ---- 8. Lesson 4 words: its song sung inside the lesson -------------------------------------------------------------
    mark = wire.next_mark()
    errors_before = len(f.chrome.errors)
    f.run(f"""const {{ learning, finishLesson }} = {LEARNING};
        for (const u of learning.course.units.slice(0, 3)) for (const l of u.lessons) if (!learning.progress.lessons[l.id]?.stars) await finishLesson(l.id, {{ percent: 90, stars: 3 }});""")
    f.run("(await import('/js/router.js')).navigate('/learn/4-words')")
    lesson_open(f, "4-words")
    walk_steps(f, "4-words")  # the Learn card, to the song step
    try:
        f.wait(f"(() => {{ const v = {VIEW}; return v?.current?.kind === 'play' && (!v.song || !!v.stepExercise); }})()", timeout=30, what="4-words' song step ready")
        ready = True
    except AssertionError:
        ready = False
    step = f.js(f"(() => {{ const v = {VIEW}; return {{ kind: v?.current?.kind, song: v?.song ? JSON.parse(JSON.stringify(v.song)) : null, notes: v?.stepExercise?.notes?.length ?? null, inSing: location.pathname }}; }})()")
    f.shot("words-song-step")
    # Sing it: Play, then a few seconds of the run, then stopped.
    played = False
    try:
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        f.run("document.querySelector('[data-play-disc]').closest('button').click()")
        time.sleep(0.8)
        if f.js("!!document.querySelector('[data-headphones]')"):
            f.click_t("headphones.on")  # the first sung step with sound under it asks, once
        stop = t(f, "studio.stop")
        f.wait(f"{AUDIO}.engine.playing || document.querySelector('[data-play-disc]')?.closest('button').innerText.includes({stop!r})", timeout=15, what="the song step running")
        played = True
        time.sleep(4)
        f.shot("words-song-running")
        # The run to its end (nobody singing into the loopback: "couldn't hear" is its answer).
        f.wait(f"!{AUDIO}.engine.playing && !document.querySelector('[data-play-disc]')?.closest('button').innerText.includes({stop!r})", timeout=60, what="the song step's run ended")
        played = f.js("document.querySelector('main [role=status]')?.textContent.trim() ?? 'ran'")
    except AssertionError:
        pass
    new_errors = [e for e in f.chrome.errors[errors_before:] if not any(x in e for x in f.expected_errors)]
    report.add("4-words: the song step runs inside the lesson without errors", ready and not new_errors and not step["inSing"].endswith("/sing"), {"step": step, "played": played, "errors": new_errors})
    walk_steps(f, "4-words", stop_before_last=False)
    f.wait(f"!!{VIEW}?.done", timeout=20, what="4-words' end")
    f.shot("words-end")
    c = counts_since(mark)
    steps["8 4-words"] = c
    report.add("4-words counted (fl_s, fs_r per step)", "fl_s:4-words" in c and "fs_r:4-words.0" in c and "fs_r:4-words.1" in c, {k: v for k, v in c.items() if "4-words" in k or k == "lesson_start"})

    # ---- Everything the walk saw going wrong ----------------------------------------------------------------------------
    report.add("no failed requests other than expected 401/419/404", not [x for x in wire.failed if not re.match(r"^(401|419|404) ", x)], wire.failed)


if __name__ == "__main__":
    run("first_week_real", journey, sizes=("phone",))
