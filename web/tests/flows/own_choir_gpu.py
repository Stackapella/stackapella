"""Hear yourself as a choir, the GPU batch's app side (contract: ownChoir.show's quota in minutes, busyToday, checkNeeded;
requests' startsAfter; email_unverified; turnstileToken), on the mock:
- Plus counts singing minutes: "47 minutes of singing left this month: about N songs like this one", N from the song's
  own part-minutes (each part's last note end, summed, as the server counts them).
- Busy today: said before singing; a song asked for then waits for tomorrow, and its page says so.
- The free song needs a confirmed email: a step before singing sends the link; coming back confirmed, it clears.
- The account's first song asks for the check (only with a site key): Make waits for it, and the token goes along.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

SONG = "11111111-2222-4333-8444-555555555556"
FLAGS = ["stackapella.mockChoirFreeUsed", "stackapella.mockChoirBusy", "stackapella.mockChoirCheck", "stackapella.mockUnverified", "stackapella.mockTurnstile"]
# Cloudflare's script, stood in for: the widget's callback is kept so the flow can pass the check itself.
FAKE_TURNSTILE = """const append = document.head.append.bind(document.head);
    document.head.append = (node) => {
        if (!String(node.src ?? '').includes('challenges.cloudflare.com')) return append(node);
        window.turnstile = { render: (el, options) => { window.__check = options; return 1; }, reset() {}, remove() {} };
        queueMicrotask(() => node.onload());
    };"""


def t(f, key, params=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(params or {})})")


def fresh(f, **flags):
    """The choir on for the tab, nothing made yet, and only these of the mock's flags on."""
    sets = "".join(f"sessionStorage.setItem({json.dumps(k)}, '1');" for k in flags.get("on", []))
    clears = "".join(f"sessionStorage.removeItem({json.dumps(k)});" for k in FLAGS)
    f.run(f"""sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockOwnChoirSongs', '1');
        sessionStorage.setItem('stackapella.mockChoirState', 'fresh'); sessionStorage.removeItem('stackapella.mockChoir'); {clears} {sets}""")


def agree(f):
    f.wait("document.querySelector('[data-choir-agree]')", what="the terms")
    f.run("document.querySelector('[data-choir-agree]').click()")


def journey(f):
    f.go("/?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    clear_projects(f)

    # Plus: the allowance in singing minutes, and about how many songs like this one.
    fresh(f)
    f.go("/t/silent-night/choir?api=mock&scenario=&as=plus", "document.querySelector('#app *')")
    agree(f)
    f.wait("document.querySelector('[data-choir-quota]')", what="the minutes line")
    songs = f.js("(async () => { const { partMinutes } = await import('/js/own-choir.js'); const { loadTemplate } = await import('/js/templates.js'); return Math.floor(47 / partMinutes(await loadTemplate('silent-night'))); })()")
    # Each month of the subscription: the line says when the month turns and the 60 come back (the server's resetsAt).
    date = f.js("(async () => { const { formatDate } = await import('/js/i18n.js'); const { ownChoir } = await import('/js/own-choir.js'); return formatDate(ownChoir.data.quota.resetsAt, { day: 'numeric', month: 'long' }); })()")
    expected = t(f, "ownChoir.limit.minutesLeftMore", {"minutes": "47", "songs": songs, "more": "60", "date": date})
    assert f.js("document.querySelector('[data-choir-quota]').textContent.trim()") == expected, f.js("document.querySelector('[data-choir-quota]').textContent")
    assert not f.js("document.querySelector('[data-choir-busy]')"), "busy said on a quiet day"
    f.no_sideways("the choir page")
    f.shot("minutes")

    # The free song already had (once per email, ever), without Plus: said before any singing, with Get Plus.
    fresh(f, on=["stackapella.mockChoirFreeUsed"])
    f.go("/t/silent-night/choir?api=mock&scenario=&as=member", "document.querySelector('#app *')")
    agree(f)
    f.wait("document.querySelector('[data-choir-free-had]')", what="the free song had")
    assert not f.js("document.querySelector('[data-choir-start]')"), "Start singing with no song left to make"
    assert t(f, "ownChoir.limit.free_used") in f.js("document.querySelector('[data-choir-free-had]').innerText")
    # What Plus unlocks and what it costs, on the card itself (Plus for what you make).
    assert t(f, "ownChoir.limit.unlocks") in f.js("document.querySelector('[data-choir-free-had]').innerText")
    f.wait("document.querySelector('[data-choir-free-had] [data-plus-price]')", what="Plus's prices on the card")
    f.shot("free-had")
    f.run("document.querySelector('[data-choir-plus]').click()")
    f.wait("location.pathname === '/plus' && location.search.includes('from=choir_song')", what="the Plus page, from the choir")
    f.wait(f"document.body.innerText.includes({json.dumps(t(f, 'plus.moment.choirSong'))})", what="why it's here")
    # With own_choir on, the Plus page lists the songs in a voice like yours, and downloading them.
    f.wait(f"document.body.innerText.includes({json.dumps(t(f, 'plus.row.choirSongs'))})", what="the choir songs row")
    assert t(f, "plus.plus.choirSongs") in f.js("document.body.innerText"), "about 15 a month"

    # Busy today: said before singing; a song asked for waits for tomorrow, and its page says so.
    fresh(f, on=["stackapella.mockChoirBusy"])
    f.run(f"""const {{ loadTemplate }} = await import('/js/templates.js'); const s = await import('/js/stores/templates.js');
        const song = structuredClone(await loadTemplate('amazing-grace'));
        Object.assign(song, {{ id: {json.dumps(SONG)}, title: 'My hummed tune' }});
        await s.saveTemplate(song, {{ visibility: 'private', libraryOptIn: false }});
        const {{ keepHum }} = await import('/js/own-choir.js');
        await keepHum({json.dumps(SONG)}, {{ samples: new Float32Array(48000 * 12).map((_, i) => 0.3 * Math.sin(i / 25)), sampleRate: 48000 }});""")
    f.go(f"/t/{SONG}/choir?api=mock&scenario=&as=plus", "document.querySelector('#app *')")
    agree(f)
    f.wait("document.querySelector('[data-choir-busy]')", what="busy today, before singing")
    assert f.js("document.querySelector('[data-choir-busy]').textContent.trim()") == t(f, "ownChoir.busy.today")
    f.shot("busy-today")
    f.run("document.querySelector('[data-choir-hum]').click()")
    f.wait("document.querySelector('[data-choir-tomorrow]')", what="made tomorrow")
    assert f.js("document.querySelector('[data-choir-tomorrow]').textContent.trim()") == t(f, "ownChoir.busy.tomorrow")
    f.shot("tomorrow")

    # The free song needs a confirmed email: asked before singing; confirmed and back, the step clears.
    fresh(f, on=["stackapella.mockUnverified"])
    f.go("/t/silent-night/choir?api=mock&scenario=&as=member", "document.querySelector('#app *')")
    f.run("(await import('/js/stores/account.js')).loadAccount({ fresh: true })")
    agree(f)
    f.wait("document.querySelector('[data-choir-email]')", what="the email step")
    assert not f.js("document.querySelector('[data-choir-start]')"), "Start singing before the email is confirmed"
    f.run("document.querySelector('[data-choir-email-send]').click()")
    email = f.js("(await import('/js/stores/account.js')).account.me.email")
    f.wait(f"document.querySelector('[data-choir-email] [role=status]')?.textContent.trim() === {json.dumps(t(f, 'ownChoir.email.sent', {'email': email}))}", what="sent, and where")
    assert "auth.verification.resend" in f.js("globalThis.mockCalls ?? []"), "the link wasn't sent"
    f.no_sideways("the email step")
    f.shot("email")
    f.run("sessionStorage.removeItem('stackapella.mockUnverified'); window.dispatchEvent(new Event('focus'))")
    f.wait("!document.querySelector('[data-choir-email]') && document.querySelector('[data-choir-start]')", what="confirmed: Start singing")

    # The first song's check (a site key set): Make waits for it, and the token goes with the clip.
    fresh(f, on=["stackapella.mockChoirCheck", "stackapella.mockTurnstile"])
    f.run(f"const {{ keepHum }} = await import('/js/own-choir.js'); await keepHum({json.dumps(SONG)}, {{ samples: new Float32Array(48000 * 12).map((_, i) => 0.3 * Math.sin(i / 25)), sampleRate: 48000 }});")
    f.go(f"/t/{SONG}/choir?api=mock&scenario=&as=member", "document.querySelector('#app *')")
    f.run(FAKE_TURNSTILE)
    agree(f)
    f.wait("document.querySelector('[data-choir-hum]') && window.__check", what="the check, before Make")
    assert f.js("document.querySelector('[data-choir-hum]').disabled"), "Make before the check"
    f.run("window.__check.callback('test-token')")
    f.wait("!document.querySelector('[data-choir-hum]').disabled", what="Make, once checked")
    f.run("document.querySelector('[data-choir-hum]').click()")
    f.wait("document.querySelector('[data-choir-wait]') || document.querySelector('[data-choir-play]')", what="sent")
    assert f.js("globalThis.mockChoirClip?.turnstileToken") == "test-token", "the token didn't go with the clip"

    f.run(f"const s = await import('/js/stores/templates.js'); await s.deleteTemplate({json.dumps(SONG)});")
    fresh(f)
    clear_projects(f)


run("own_choir_gpu", journey)
