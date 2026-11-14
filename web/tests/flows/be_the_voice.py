"""Be the voice (Hillel, 2026-11-12: make it very clear in the song that a part has no real voice; "record first, the legal
part only when they share"; the engagement review's top 5; js/be-the-voice.js): with the call open (auth.config
`lendVoice`), Sing's part picker tags the parts no one has sung (from day one) and the song's sheet names them past the
first week. A guest who sings a whole, usable take of such a part sees one calm invitation under the review; Share it goes
to sign-up titled for that part, which comes straight back to the same take with the share sheet open: three plain points,
18 or over, the credit with its preview, Share it or Keep it private for now, then Sent with honest timing. Lend your voice
lists the take under Ready to share. Against the mock, whose Amazing Grace has no real soprano or tenor; voices.share is
answered in the page as sent.
Proven at phone size, where most singers are (2026-11-13; lend_voice waited on it): the take is the loopback's, so the
loopback goes on again on the page that records (a page load drops it, and the take was Chrome's fake-mic beep, scored
"unheard"), with the other parts muted and the tenor's guide on, so it records a clean tenor (scored 100, no bleed). The
whole run is phone-first; desktop returns after the first step.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t  # noqa: E402
from record_mix import heard_while_recording  # noqa: E402

OPEN = """(() => {
    const real = window.fetch.bind(window);
    window.fetch = async (input, init) => {
        const url = new URL(typeof input === 'string' ? input : input.url, location.href);
        if (url.pathname === '/contract/examples/auth.config.200.json') {
            const json = await (await real(input, init)).json();
            json.data.lendVoice = true;
            return new Response(JSON.stringify(json), { headers: { 'Content-Type': 'application/json' } });
        }
        if (url.pathname.endsWith('/voices.share.200.json') || url.pathname === '/api/voices/share') {
            return new Response(JSON.stringify({ data: { status: 'sent' } }), { headers: { 'Content-Type': 'application/json' } });
        }
        return real(input, init);
    };
})();"""
LEARNING = "(await import('/js/stores/learning.js')).learning"
I18N = "(await import('/js/i18n.js'))"


def past_first_week(f, past=True):
    lessons = {f"l{i}": {"stars": 2} for i in range(3)} if past else {}
    f.run(f"{LEARNING}.progress.lessons = {json.dumps(lessons)};")


def journey(f):
    if f.size == "desktop":
        return  # a phone first: the whole run is shot there (it records the whole song)
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": OPEN})

    # The song's sheet, past the first week: one line naming the parts with no voice.
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#home-songs [data-song-card]')")  # a guest: the take is theirs before any account
    past_first_week(f)
    f.run("[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Amazing Grace')).querySelector('button').click()")
    f.wait("document.querySelector('[data-song-no-voice]')", what="the no-voice line")
    line = f.js("document.querySelector('[data-song-no-voice]').textContent")
    assert t(f, "parts.soprano") in line and t(f, "parts.tenor") in line and t(f, "parts.alto") not in line, line
    f.shot("sheet")
    f.press("Escape")

    # Sing's part picker, day one: No voice yet on the soprano and the tenor (serious mode's list; game's select is game's).
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    f.run(f"""const a = {AUDIO}; await a.openMic();
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    clear_projects(f)
    song = new_song(f)
    past_first_week(f, past=False)
    f.go(f"/p/{song}/sing", "document.querySelector('[data-way]')")
    f.run("document.querySelector('[data-way=parts]').click()")
    # Both skins: the calm list's tag, or game mode's cartridge label on the part select's portraits.
    f.wait("document.querySelectorAll('[data-no-voice]').length === 2", what="two parts with no voice yet")
    f.shot("picker")

    # A whole tenor take, as a guest, over headphones in the song's key: the review invites it, once.
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'tenor')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    # The harness's singer is the loopback, which records what plays: the other parts muted and the tenor's own guide on,
    # it sings a clean tenor, as a singer over headphones would (with them all playing it would hear no tenor at all).
    # A page load drops the loopback, so it goes on again here, on the page that records.
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run(f"""const s = {SESSION}; for (const p of s.session.parts) if (p.templatePartKey !== 'tenor' && !s.session.hearing.off.includes(p.id)) s.toggleHearing(p.id);
        s.setRecordGuide(true);""")
    f.press("r")
    heard_while_recording(f, "the whole song", seconds=0.5)
    length = f.js(f"{SESSION}.session.contentLength")
    f.wait(f"!{SESSION}.session.recording && document.querySelector('[data-take-review]')", timeout=length + 40, what="the take's review")
    f.wait("document.querySelector('[data-be-the-voice]')", timeout=20, what="Be the voice's invitation")
    card = f.js("document.querySelector('[data-be-the-voice]').textContent")
    assert t(f, "parts.tenor") in card and f.js("!!document.querySelector('[data-be-the-voice-guest]')"), card
    f.js("document.querySelector('[data-be-the-voice]').scrollIntoView({ block: 'center' })")
    f.shot("invite-guest")
    f.no_sideways("the review with Be the voice")

    # Share it, as a guest: sign-up titled for the part, coming back to this take.
    tenor_id = f.js(f"{SESSION}.session.parts.find((p) => p.templatePartKey === 'tenor').id")  # read here: Sing closes on sign-up
    f.run("document.querySelector('[data-be-the-voice-share]').click()")
    f.wait("location.pathname === '/signin'", what="sign-up")
    title = t(f, "auth.reason.voicePart", {"part": t(f, "parts.tenor")})
    f.wait(f"document.querySelector('h1')?.textContent.includes({json.dumps(title)})", what="sign-up titled for the tenor")
    nxt = f.js("new URLSearchParams(location.search).get('next')")
    assert nxt == f"/p/{song}/sing?voice={tenor_id}", nxt
    f.shot("signup")

    # Signed in and back: the same take, its sheet open (the take saved to the account first).
    f.go(f"{nxt}&as=member", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.some((p) => p.templatePartKey === 'tenor')", what="the song open again")
    f.run(f"const s = {SESSION}; const p = s.session.parts.find((x) => x.templatePartKey === 'tenor'); p.syncedTakeId = p.takeId;")
    f.wait("document.querySelector('[data-share-voice]')", what="the share sheet")
    assert f.js("document.querySelector('[data-share-voice-send]').disabled"), "Share it before 18 or over is ticked"
    f.run("document.querySelector('[data-share-voice-adult]').click()")
    f.wait("!document.querySelector('[data-share-voice-send]').disabled", what="Share it, once 18 or over")
    preview = f.js("document.querySelector('[data-share-voice-preview]').textContent")
    assert preview.strip(), "the credit preview"
    f.shot("share-sheet")
    f.run("document.querySelector('[data-share-voice-send]').click()")
    f.wait("document.querySelector('[data-share-voice-sent]')", what="Sent")
    f.shot("sent")
    f.press("Escape")

    # Lend your voice: the take under Ready to share.
    f.go("/voices", "document.querySelector('#app main')")
    f.wait("document.querySelector('[data-voices-ready]')", what="Ready to share")
    f.shot("ready")


if __name__ == "__main__":
    run("be_the_voice", journey)
