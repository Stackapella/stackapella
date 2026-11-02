"""Lend your voice's quiet line (docs/lend-your-voice-plan.md, "Where"): with the switch on, a signed-in
adult whose account is a week old sees "No real voice yet. Lend yours?" under their part in Sing's Your
part sheet when the practice voice sings it, and the link opens the Wanted voices. A new account, an
account under 18, and the switch off see nothing.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import SESSION, new_song, t  # noqa: E402

ACCOUNT = "(await import('/js/stores/account.js')).account"
LINE = "document.querySelector('[data-lend-line]')"
DAY = 86400000


def me(f, days_old, adult=True, song=None):
    """The mock account this old and of this age (kept for the tab), then Sing opened again with it."""
    f.run(f"sessionStorage.setItem('stackapella.mockAccount', JSON.stringify({{ createdAt: new Date(Date.now() - {days_old} * {DAY}).toISOString(), isAdult: {'true' if adult else 'false'} }}))")
    if song:
        open_sing(f, song)
    f.wait(f"{ACCOUNT}.me?.isAdult === {'true' if adult else 'false'}", what="the account as set")


def open_sing(f, song):
    """Sing on the soprano (a part the mock gives no real voice), the whole song."""
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'soprano')); s.savePractice();""")
    f.wait("!document.querySelector('[data-way]')", what="past the chooser")


def open_sheet(f):
    """Your part: the header's chip on a wide screen, Session settings' part row on a phone."""
    if f.size == "phone":
        f.run("document.querySelector('[data-settings-open]').click()")
        f.wait("document.querySelector('[data-part-picker]')", what="Session settings' part row")
        f.run("document.querySelector('[data-part-picker]').click()")
    else:
        f.click(t(f, "sing.yourPart.open", {"part": t(f, "parts.soprano")}))
    f.wait_text(t(f, "sing.yourPart.title"))
    f.js("new Promise((r) => setTimeout(r, 300))")


def close_sheet(f):
    f.press("Escape")
    f.wait(f"!document.body.innerText.includes({t(f, 'sing.yourPart.intro')!r})", what="the sheet closed")


def journey(f):
    f.go("/voice?api=mock&scenario=", "document.querySelector('#app header, #app main')")
    f.run("sessionStorage.setItem('stackapella.mockLendVoice', '1')")
    f.go("/voice", "document.querySelector('#app header, #app main')")
    f.wait(f"{ACCOUNT}.me && {ACCOUNT}.config?.lendVoice", what="signed in, the switch on")
    song = new_song(f, "amazing-grace")

    # A week-old adult account: the line, under your part only.
    me(f, 30, song=song)
    open_sheet(f)
    f.wait(LINE, what="the quiet line")
    assert f.js(f"document.querySelectorAll('[data-lend-line]').length") == 1, "the line shows under your part only"
    assert f.js(f"{LINE}.textContent.includes({t(f, 'voices.partLine')!r})"), "its words"
    f.shot("lend-line")
    f.no_sideways("Your part with the line")

    # Its link opens the Wanted voices.
    f.click(t(f, "voices.partLineLink"))
    f.wait("location.pathname === '/voices'", what="the Wanted voices")

    # A new account, or an account under 18: nothing.
    for days, adult, why in [(3, True, "a new account"), (30, False, "under 18")]:
        me(f, days, adult, song=song)
        open_sheet(f)
        assert not f.js(f"!!{LINE}"), f"the line shown to {why}"
        close_sheet(f)

    # The switch off: nothing.
    f.run("sessionStorage.removeItem('stackapella.mockLendVoice')")
    me(f, 30, song=song)
    f.wait(f"{ACCOUNT}.config && !{ACCOUNT}.config.lendVoice", what="the switch off")
    open_sheet(f)
    assert not f.js(f"!!{LINE}"), "the line shown with the switch off"


if __name__ == "__main__":
    run("lend_line", journey)
