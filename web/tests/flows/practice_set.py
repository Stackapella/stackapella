"""Practise the set (docs/practice-playlist-plan.md, behind practice_set), against the mock, as a choir member: the
group page's button; the part asked once (the second song has no part of theirs); the set rendered into one file;
the lock screen told the song, their part and the group, with every control; Play, Next and Previous (seeks in the
one file, never a new song file); Without your part re-renders and keeps the song; Spanish on a phone; no sideways.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
GROUP = "/groups/Gp4rQs?api=mock&as=member"
ACTIONS = ["play", "pause", "previoustrack", "nexttrack", "seekto", "seekbackward", "seekforward"]


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def open_set(f, lang=""):
    f.run("sessionStorage.setItem('stackapella.mockPracticeSet', '1'); sessionStorage.setItem('stackapella.mockChoirMember', '1')")
    f.go(GROUP + lang, "document.querySelector('[data-practice-set-open]')")
    # What the page tells the lock screen (Media Session), kept for the checks.
    f.run("window.lockScreen = {}; const s = navigator.mediaSession; const real = s.setActionHandler.bind(s); s.setActionHandler = (a, h) => { window.lockScreen[a] = !!h; real(a, h); };")
    f.run("document.querySelector('[data-practice-set-open]').click()")
    f.wait("location.pathname === '/groups/Gp4rQs/set'", what="the set")


def ready(f, shot=None):
    # A phone is told the size first and makes it only on a tap (slice 4); a set kept from before opens at once.
    f.wait("document.querySelector('[data-set-player]') || document.querySelector('[data-set-confirm]')", timeout=180, what="the set made, or its size asked")
    if f.js("!!document.querySelector('[data-set-confirm]')"):
        assert f.size == "phone", "only a phone is asked"
        if shot:
            f.shot(shot)
        f.run("document.querySelector('[data-set-go]').click()")
    f.wait("document.querySelector('[data-set-player]')", timeout=180, what="the set made")
    f.wait("document.querySelector('[data-set-audio]').readyState >= 1", timeout=30, what="the set's file loaded")


def journey(f):
    f.go(GROUP, "document.querySelector('h1')")
    f.run("localStorage.removeItem('stackapella.setPart.Gp4rQs')")
    open_set(f)
    # Kim Ode has the tenor in Amazing Grace but nothing in the second song: their part, asked once.
    f.wait("document.querySelector('[data-set-pick]')", what="the part asked")
    f.shot("pick")
    f.run("document.querySelector('[data-set-part=\"tenor\"]').click()")
    ready(f, shot="size")
    f.shot("ready")
    audio = "document.querySelector('[data-set-audio]')"
    assert f.js(f"{audio}.src.startsWith('blob:')"), "one file, made on the device"
    src = f.js(f"{audio}.src")
    first = f.js("document.querySelector('[data-set-now]').firstChild.textContent.trim()")
    assert f.js("navigator.mediaSession.metadata?.title") == first, "the lock screen names the song"
    artist = f.js("navigator.mediaSession.metadata?.artist")
    assert "Thursday Singers" in artist, artist
    handlers = f.js("window.lockScreen")
    assert all(handlers.get(a) for a in ACTIONS), handlers
    assert f.js("JSON.parse(localStorage.getItem('stackapella.setPart.Gp4rQs')).key") == "tenor", "the part kept for the group"
    # Play, then Next and Previous: seeks within the one file.
    f.run(f"(await import('/js/audio.js')).unlock(); document.querySelector('[data-set-play]').click()")
    f.wait(f"!{audio}.paused && {audio}.currentTime > 0", timeout=20, what="the set playing")
    f.run("document.querySelector('[data-set-next]').click()")
    f.wait("document.querySelector('[aria-current=\"true\"][data-set-song]') === document.querySelectorAll('[data-set-song]')[1]", what="the second song")
    assert f.js(f"{audio}.src") == src, "Next seeks: the same file"
    assert f.js("navigator.mediaSession.metadata?.title") != first, "the lock screen follows the song"
    f.shot("second-song")
    f.run("document.querySelector('[data-set-previous]').click()")
    f.wait("document.querySelector('[aria-current=\"true\"][data-set-song]') === document.querySelectorAll('[data-set-song]')[0]", what="back to the first song")
    # Repeat my part: round the tenor's stretch of the first song; past its end, it comes back round, never on to song 2.
    f.run("document.querySelector('[data-set-repeat]').click()")
    f.wait("document.querySelector('[data-set-repeat]').getAttribute('aria-pressed') === 'true' && document.querySelector('[data-set-repeat-on]')", what="Repeat my part on")
    f.shot("repeat")
    f.run(f"{audio}.currentTime = {audio}.duration - 1")
    f.wait(f"!{audio}.paused && {audio}.currentTime < 40", timeout=20, what="back round the first song's part")
    assert f.js("document.querySelector('[aria-current=\"true\"][data-set-song]') === document.querySelectorAll('[data-set-song]')[0]"), "still the first song"
    # Next ends it: the whole set again.
    f.run("document.querySelector('[data-set-next]').click()")
    f.wait("document.querySelector('[data-set-repeat]')?.getAttribute('aria-pressed') !== 'true' && document.querySelector('[aria-current=\"true\"][data-set-song]') === document.querySelectorAll('[data-set-song]')[1]", what="Next, Repeat off")
    f.run("document.querySelector('[data-set-previous]').click()")
    f.wait("document.querySelector('[aria-current=\"true\"][data-set-song]') === document.querySelectorAll('[data-set-song]')[0]", what="the first song again")
    # The lock screen's own Next (as its button sends it).
    f.run("document.querySelector('[data-set-play]').click()")
    f.wait(f"{audio}.paused", what="paused")
    # Without your part: made again, on the same song.
    f.run("document.querySelector('[data-set-sound] [role=radio]:nth-child(2)').click()")
    ready(f)
    assert f.js("document.querySelector('[data-set-sound] [role=radio]:nth-child(2)').getAttribute('aria-checked')") == "true"
    assert f.js("document.querySelector('[aria-current=\"true\"][data-set-song]') === document.querySelectorAll('[data-set-song]')[0]"), "the same song"
    f.no_sideways("Practise the set")
    # Play after a pause: the same file again where it was (the installed app's silent resume, plan 3a).
    f.run(f"window.beforeResume = {audio}.currentTime; document.querySelector('[data-set-play]').click()")
    f.wait(f"!{audio}.paused", what="playing again")
    f.run("document.querySelector('[data-set-play]').click()")
    # Spanish on a phone, the part remembered (no question this time).
    if f.size == "phone":
        open_set(f, "&lang=es")
        # Kept on the device from the first time: no size asked, nothing made again.
        f.wait("document.querySelector('[data-set-player]') || document.querySelector('[data-set-confirm]')", timeout=30, what="the kept set")
        assert not f.js("document.querySelector('[data-set-confirm]')"), "a kept set opens at once"
        ready(f)
        assert not f.js("document.querySelector('[data-set-pick]')"), "the part was asked once"
        f.wait_text(t(f, "practiceSet.title"))
        f.no_sideways("Practise the set in Spanish")
        f.shot("es")
    # A rehearsal's set (a group linked to Event Schedule): the next rehearsal's songs in its order, by default; one with
    # no songs picked plays them all and says so; and all the choir's songs.
    f.go(GROUP, "document.querySelector('[data-practice-set-open]')")
    f.run("""const { api } = await import('/js/api.js'); const day = (n) => new Date(Date.now() + n * 864e5).toISOString().replace(/\\.\\d+Z$/, 'Z');
        api.groups.rehearsals.list = async () => ({ data: { schedule: { subdomain: 'thursday', name: 'Thursday Singers', url: null }, available: true, events: [
            { id: 'EvSoon', name: 'Thursday rehearsal', startsAt: day(1), endsAt: null, timezone: 'Europe/London', url: null, songs: [
                { templateId: 'b2c3d4e5-6f70-4182-93a4-b5c6d7e8f901', title: 'Our Song for Gran', partKey: 'tenor', status: 'started' },
                { templateId: 'amazing-grace', title: 'Amazing Grace', partKey: 'tenor', status: 'learned' } ] },
            { id: 'EvLater', name: 'Spring concert', startsAt: day(20), endsAt: null, timezone: 'Europe/London', url: null, songs: [] } ] } });""")
    f.run("document.querySelector('[data-practice-set-open]').click()")
    ready(f)
    assert f.js("document.querySelector('#set-which').value") == "EvSoon", "the next rehearsal first"
    assert f.js("document.querySelectorAll('[data-set-song]')[0].dataset.setSong") == "b2c3d4e5-6f70-4182-93a4-b5c6d7e8f901", "in the rehearsal's order"
    assert f.js("navigator.mediaSession.metadata?.album") == "Thursday rehearsal"
    f.shot("rehearsal")
    f.run("const s = document.querySelector('#set-which'); s.value = 'EvLater'; s.dispatchEvent(new Event('change'))")
    f.wait("document.querySelector('[data-set-none-picked]')", what="a rehearsal with no songs picked says so")
    ready(f)
    assert f.js("document.querySelectorAll('[data-set-song]')[0].dataset.setSong") == "amazing-grace", "all the choir's songs, in the group's order"
    f.run("const s = document.querySelector('#set-which'); s.value = 'all'; s.dispatchEvent(new Event('change'))")
    f.wait("!document.querySelector('[data-set-none-picked]') && document.querySelector('[data-set-player]')", timeout=180, what="all the songs")
    f.no_sideways("Practise the set, a rehearsal's")
    # A phone with nothing kept: the size, and Not now goes back without making anything.
    if f.size == "phone":
        f.run("const db = await import('/js/db.js'); for (const r of await db.all('sets')) await db.remove('sets', r.key);")
        open_set(f)
        f.wait("document.querySelector('[data-set-confirm]')", timeout=60, what="the size asked")
        assert "MB" in f.js("document.querySelector('[data-set-size]').textContent"), "the size said"
        f.run("document.querySelector('[data-set-not-now]').click()")
        f.wait("location.pathname === '/groups/Gp4rQs'", what="back to the group")
    # Off: the group page has no button, and the address goes back to the group.
    f.run("sessionStorage.removeItem('stackapella.mockPracticeSet')")
    f.go(GROUP, "document.querySelector('[data-my-part]')")
    assert not f.js("document.querySelector('[data-practice-set-open]')"), "no button while it's off"
    f.go("/groups/Gp4rQs/set?api=mock&as=member", "location.pathname === '/groups/Gp4rQs'")
    f.run("sessionStorage.removeItem('stackapella.mockChoirMember')")


run("practice_set", journey)
