"""Song requests (docs/more-features-plan.md section 8, N7): staff see them before the switch opens. The
library's full list ends with "Can't find your song? Request it"; /requests lists the most wanted with a
vote each (pressed once yours), Yours, and Request a song in a sheet that shows requests like the title
first; a request is reported from its ⋯; a guest reads the list and meets the account wall to ask or vote;
closed, everyone else sees it isn't open yet. Admin > Content > Requests hides, sets a status, marks one
Ready with its song and merges. No one's name is ever shown on a request.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import every_theme  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
ROUTER = "(await import('/js/router.js'))"
ACCOUNT = "(await import('/js/stores/account.js')).account"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    # Staff, before the switch: the library's last line asks for the song that isn't there.
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    # Past the first week (nothing new in a singer's first week): the library trimmed to three, with Browse all.
    f.run("""const db = await import('/js/db.js'); const { emptyProgress } = await import('/js/course.js');
        await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 12 * 86400000, home: 57, lessons: { '1-comfortable': { stars: 3, bestPercent: 95, at: Date.now() - 9 * 86400000 } } }, 'progress');""")
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.wait("document.querySelector('[data-browse-all]')", what="Browse all")
    if f.js("!!document.querySelector('[data-browse-all]')"):
        f.run("document.querySelector('[data-browse-all]').click()")
    f.wait("document.querySelector('[data-request-song] a')", what="Can't find your song? Request it")
    f.run("document.querySelector('[data-request-song] a').click()")
    f.wait("location.pathname === '/requests' && document.querySelector('[data-request-ask]')", what="the request sheet, opened from the library")
    f.click_t("common.cancel")

    # The list: most wanted, each with its vote; a vote is pressed once it's yours.
    f.wait("document.querySelector('[data-requests-list] [data-request]')", what="the requests")
    text = f.js("document.querySelector('[data-requests-list]').innerText")
    assert t(f, "requests.status.arranging").upper() in text.upper(), "where a request stands, in words"  # game mode's tag shows it in capitals
    f.no_sideways("the requests page")
    f.shot("list")
    if f.skin == "game":
        every_theme(f, "song requests")  # the arcade pass, at AA in all eight themes
    f.run("window.mockCalls = [];")
    f.run("document.querySelector('[data-request-vote]').click()")
    f.wait("(globalThis.mockCalls ?? []).includes('requests.vote')", what="the vote sent")

    # Request a song: requests like the title come first, so you vote instead of asking again.
    f.click_t("requests.ask.button")
    f.wait("document.querySelector('[data-request-ask] [data-ask-title]')", what="the request sheet")
    f.type("[data-ask-title]", "Danny Boy")
    f.wait("document.querySelector('[data-ask-like] [data-request]')", what="requests like it")
    f.shot("ask")
    f.run("window.mockCalls = [];")
    f.run("document.querySelector('[data-request-ask]').requestSubmit()")
    f.wait("(globalThis.mockCalls ?? []).includes('requests.create') && !document.querySelector('[data-request-ask]')", what="the request sent")

    # Yours: the ones you asked for or voted.
    f.run("document.querySelector('[data-requests-yours]').click()")
    f.wait("document.querySelector('[data-requests-yours][aria-pressed=true]')", what="Yours")
    f.run("document.querySelector('[data-requests-yours]').click()")

    # Report a request (never your own): a reason, then thanks.
    f.wait("document.querySelector('[data-request-report]')", what="a request's ⋯")
    f.run("document.querySelector('[data-request-report]').click()")
    f.wait("document.querySelector('[data-request-report-sheet]')", what="the report sheet")
    f.shot("report")
    f.run("window.mockCalls = [];")
    f.run("document.querySelector('[data-request-report-sheet]').requestSubmit()")
    f.wait_text(t(f, "requests.report.thanks"))
    assert "reports.create" in f.js("globalThis.mockCalls ?? []"), "the report sent"

    # Admin > Content > Requests: hide, a status, Ready with its song.
    f.go("/admin/requests?api=mock&scenario=&as=admin", "document.querySelector('[data-admin-requests] [data-admin-request]')")
    f.no_sideways("Admin's requests")
    f.shot("admin")
    f.run("window.mockCalls = [];")
    f.run("document.querySelector('[data-request-hide]').click()")
    f.wait("(globalThis.mockCalls ?? []).includes('admin.requests.update')", what="hidden")

    # A guest, once it's open: reads the list; asking meets the account wall.
    f.go("/requests?api=mock&scenario=&as=signedOut", "document.querySelector('[data-requests]')")
    f.wait_text(t(f, "requests.closed"))
    f.wait(f"!!{ACCOUNT}.config", what="the app's config")
    # The switch on (as auth.config says it once Hillel turns it on): kept on through a late config load.
    f.run(f"""const a = {ACCOUNT}; a.config = {{ ...(a.config ?? {{}}), songRequests: true }};
        window.__keepOn = setInterval(() => {{ if (!a.config?.songRequests) a.config = {{ ...(a.config ?? {{}}), songRequests: true }}; }}, 50);
        {ROUTER}.navigate('/'); await new Promise((r) => setTimeout(r, 300)); {ROUTER}.navigate('/requests');""")
    f.wait("document.querySelector('[data-requests-list] [data-request]')", what="the list for a guest")
    assert not f.js("!!document.querySelector('[data-request-report]')"), "a guest can report"
    f.click_t("requests.ask.button")
    f.wait_text(t(f, "wall.requests"))
    f.shot("guest")
    f.run("clearInterval(window.__keepOn)")


if __name__ == "__main__":
    run("song_requests", journey)
