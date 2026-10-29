"""Sing's HUD as a reskin of the recording stage (docs/game-elements-plan.md; Hillel: "reskinned these
elements"): in game mode the Recording pill holds READY? through the mic's settle and then each beat in
HUD digits (no GO), the take's clock is in HUD digits and its progress a power bar, and no strip is
added above the lane; the bonus pop-ups burst beside the combo or under the header, never on the lane.
The Score strip switch and serious mode give today's recording stage.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t  # noqa: E402
from game_frames import dark, frames, light  # noqa: E402




def record(f, again=False, counting=False):
    again_button = f"[...document.querySelectorAll('button')].find((b) => b.textContent.trim() === {t(f, 'sing.recorded.again')!r})"
    f.wait(f"!{SESSION}.session.recording && !{SESSION}.session.busy", timeout=30, what="ready to record again")
    if again and f.js(f"!!{again_button}"):
        f.run(f"{again_button}.click()")
    else:
        f.press("r")
    if counting:
        # READY? through the mic's settle, then the bar's beats counting up, and no GO after them.
        f.wait("document.querySelector('[data-count-in] .ge-ready')?.textContent.length > 0", timeout=10, what="READY? in the Recording pill")
        f.shot("ready")
        f.wait("document.querySelector('[data-count-in] .ge-count-beat')", timeout=10, what="the count-in's beats in the pill")
        f.shot("count-in")
    f.wait(f"{SESSION}.session.recording && {SESSION}.session.countIn === null && {SESSION}.session.position > {SESSION}.session.recordFrom + 2.5", timeout=40, what="recording, 2.5 s in")


def stop(f):
    f.run("document.querySelector('[data-stop]')?.click()")
    f.wait(f"!{SESSION}.session.recording", what="stopped")


def journey(f):
    f.go("/voice", "document.querySelector('#app header, #app main')")
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1'); localStorage.removeItem('stackapella.gameEffects')")
    f.run(f"""const a = {AUDIO}; await a.openMic();
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    # The CRT boot: once a session, game mode only.
    booted = f.js("sessionStorage.getItem('stackapella.crtShown')")
    assert (booted == "1") == (f.skin == "game"), f"the CRT boot in {f.skin}: {booted}"
    record(f, counting=f.skin == "game")
    if f.skin == "serious":
        assert not f.js("getComputedStyle(document.querySelector('[data-take-time]')).fontFamily.includes('Press Start')"), "serious mode: today's recording stage"
        stop(f)
        clear_projects(f)
        return
    assert f.js("!document.querySelector('[data-game-hud]')"), "no strip added"
    hud_font = "getComputedStyle(document.querySelector('[data-take-time]')).fontFamily.includes('Press Start')"
    assert f.js(hud_font), "the take's clock in HUD digits"
    f.no_sideways("the reskinned recording stage")
    f.shot("recording-hud")
    light(f)
    f.shot("recording-hud-light")
    dark(f)
    stop(f)
    # Heard back: Hear it with the others says REPLAY while it plays.
    hear = f"document.querySelector('[aria-label=\"' + (await import('/js/i18n.js')).t('sing.recorded.hearOthers') + '\"]')"
    f.wait(hear, timeout=30, what="Hear it with the others")
    f.run(f"{hear}.click()")
    f.wait("document.querySelector('[data-replay]')", what="the replay tag")
    assert t(f, "game.replay").upper() in f.js("document.querySelector('[data-replay]').innerText").upper(), "REPLAY while it plays"
    f.shot("replay")
    f.run("document.querySelector('[data-replay]')?.closest('.card')?.querySelector('button')?.click()")
    # Score strip off: today's recording stage.
    f.run("(await import('/js/game/effects.js')).effects.hud = false")
    record(f, again=True)
    assert not f.js(hud_font), "the Score strip switch turns the reskin off"
    stop(f)
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    clear_projects(f)
    popups(f)


POPUP = """const { createApp, h, reactive } = await import('vue');
    const { t } = await import('/js/i18n.js');
    const { PopupSlot } = await import('/js/game/hud.js');
    const { popupQueue } = await import('/js/game/popups.js');
    document.querySelector('#hud-test')?.remove();
    const box = Object.assign(document.createElement('div'), { id: 'hud-test', className: 'm-4 flex items-center gap-3' });
    document.querySelector('#app').prepend(box);
    window.hudQueue = popupQueue(); window.hudQueue.clear(0);
    const app = createApp({ render: () => [h('p', { class: 'self-start rounded-full bg-raised px-3 py-1 text-sm font-semibold text-cream', 'data-combo': '' }, t('course.combo', { count: 10 })), h(PopupSlot, { queue: window.hudQueue })] });
    app.config.globalProperties.t = t;
    app.mount(box); box.scrollIntoView();"""


PILL = """const { createApp, h } = await import('vue');
    const { t } = await import('/js/i18n.js');
    const { CountIn } = await import('/js/components/count-in.js');
    document.querySelector('#pill-test')?.remove();
    const box = Object.assign(document.createElement('div'), { id: 'pill-test', className: 'm-4 flex gap-3' });
    document.querySelector('#app').prepend(box);
    const pill = (beat) => h('span', { class: 'flex items-center gap-1.5 rounded-full glass-record px-3.5 py-1.5 text-sm font-bold tabular-nums' }, [h('span', { class: 'size-2 rounded-full bg-record-label' }), h(CountIn, { beat })]);
    const app = createApp({ render: () => [pill(0), pill(2), pill(3)] });
    app.config.globalProperties.t = t;
    app.mount(box);"""


def popups(f):
    """The Recording pill counting in (held: READY?, then beats 2 and 3), and the pop-ups beside the combo, a frame strip of each."""
    f.run("const e = await import('/js/game/effects.js'); Object.assign(e.effects, e.EFFECTS)")
    f.run(PILL)
    f.wait("document.querySelector('#pill-test .ge-ready')", what="the held pills")
    f.run("for (const a of document.getAnimations()) { a.pause(); a.currentTime = 300; }")
    f.shot("pill-count")
    f.run("document.querySelector('#pill-test').remove()")
    f.run(POPUP)
    f.run("window.hudQueue.offer({ kind: 'combo', count: 10 }, 5)")
    f.wait("document.querySelector('#hud-test [data-game-popup=combo]')", what="the combo pop-up")
    frames(f, "popup-combo", [0, 90, 150, 300, 900, 1150])
    f.run("window.hudQueue.tick(7); window.hudQueue.offer({ kind: 'phrase', phrase: 1 }, 7)")
    f.wait("document.querySelector('#hud-test [data-game-popup=phrase]')", what="the phrase pop-up")
    frames(f, "popup-phrase", [150, 400])
    f.run("window.hudQueue.tick(9); window.hudQueue.offer({ kind: 'bonus', count: 11 }, 9)")
    f.wait("document.querySelector('#hud-test [data-game-popup=bonus]')", what="the combo bonus pop-up")
    frames(f, "popup-bonus", [300])
    light(f)
    f.run("window.hudQueue.tick(11); window.hudQueue.offer({ kind: 'combo', count: 20 }, 11)")
    frames(f, "popup-light", [300])
    f.run("window.hudQueue.tick(13); window.hudQueue.offer({ kind: 'phrase', phrase: 2 }, 13)")
    frames(f, "popup-phrase-light", [300])
    dark(f)


if __name__ == "__main__":
    run("game_hud", journey)
