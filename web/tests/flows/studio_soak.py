"""The studio over a long session doesn't leak (the coordinator's 20-minute check, 2026-11-14: flat over 52 cycles): a
song of 8 recorded tracks and a song from its template, then cycles of play, record a take and delete it, a phrase
looped on the template song, and a switch between the two, all by the app's own router (one page, so whatever leaks
adds up). After a warm-up cycle, measured on Home with the studio gone each time, the page's elements, the event listeners, the live AudioNodes (created minus collected, by
a FinalizationRegistry on each), the AudioBuffers' bytes and the AudioContexts made stay where they were through the
rest, and the JS heap grows by at most 1 MB. Mock API, desktop. A copy of it was run for 20 minutes the same way.
"""
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, home_page  # noqa: E402

CYCLES = int(os.environ.get("SOAK_CYCLES", "5"))
ROUTER = "(await import('/js/router.js'))"
PARTS = f"{SESSION}.session.parts"
COUNT = """(() => {
  const live = { created: 0, collected: 0, contexts: 0, byType: {} };
  window.__audio = live;
  const gone = new FinalizationRegistry((type) => { live.collected++; live.byType[type]--; });
  const note = (node) => { if (node instanceof AudioNode && !(node.context instanceof OfflineAudioContext)) { live.created++; const type = node.constructor.name; live.byType[type] = (live.byType[type] ?? 0) + 1; gone.register(node, type); } return node; };
  for (const name of Object.getOwnPropertyNames(BaseAudioContext.prototype)) {
    if (!name.startsWith('create') || name === 'createBuffer') continue;
    const make = BaseAudioContext.prototype[name];
    if (typeof make !== 'function') continue;
    BaseAudioContext.prototype[name] = function (...args) { return note(make.apply(this, args)); };
  }
  for (const name of Object.getOwnPropertyNames(window)) {
    if (!/Node$/.test(name) || name === 'AudioNode') continue;
    const Ctor = window[name];
    if (typeof Ctor !== 'function' || !(Ctor.prototype instanceof AudioNode)) continue;
    window[name] = new Proxy(Ctor, { construct: (target, args, newTarget) => note(Reflect.construct(target, args, newTarget)) });
  }
  // AudioBuffers' samples live outside the JS heap: their bytes, created minus collected.
  live.bufferBytes = 0;
  const freed = new FinalizationRegistry((bytes) => { live.bufferBytes -= bytes; });
  const keep = (buffer) => { if (buffer instanceof AudioBuffer) { const bytes = buffer.length * buffer.numberOfChannels * 4; live.bufferBytes += bytes; freed.register(buffer, bytes); } return buffer; };
  const makeBuffer = BaseAudioContext.prototype.createBuffer;
  BaseAudioContext.prototype.createBuffer = function (...args) { return keep(makeBuffer.apply(this, args)); };
  const decode = BaseAudioContext.prototype.decodeAudioData;
  BaseAudioContext.prototype.decodeAudioData = function (...args) { return decode.apply(this, args).then(keep); };
  window.AudioBuffer = new Proxy(window.AudioBuffer, { construct: (target, args, newTarget) => keep(Reflect.construct(target, args, newTarget)) });
  const AC = window.AudioContext;
  window.AudioContext = new Proxy(AC, { construct: (target, args, newTarget) => { live.contexts++; return Reflect.construct(target, args, newTarget); } });
})();"""



def journey(f):
    c = f.chrome
    c.send("Page.addScriptToEvaluateOnNewDocument", {"source": COUNT})
    c.send("Performance.enable", {"timeDomain": "timeTicks"})
    home_page(f, "member")
    clear_projects(f)
    f.run(f"""const a = {AUDIO}; await a.unlock(); await a.openMic(); a.engine.setLoopback(true);
        sessionStorage.setItem('stackapella.headphones', '1');
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    songs = f.js("""(async () => { const p = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return [(await p.createProject({ title: 'Soak A', bpm: 120 })).id, (await p.startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id]; })()""")
    f.mic_checked()

    def go(song):
        f.run(f"{ROUTER}.navigate('/p/{song}')")
        f.wait(f"location.pathname === '/p/{song}' && {SESSION}.session.project?.id === '{song}' && document.querySelector('footer')", what=f"the studio on {song}")

    def record(seconds, adds=True):
        n = f.js(f"{PARTS}.length")
        f.press("r")
        f.wait(f"{SESSION}.session.recording", what="recording")
        time.sleep(seconds)
        if f.js(f"{SESSION}.session.recording"):
            f.press("r")
        # A blank song's take adds a track; a song's fills its next part.
        f.wait(f"!{SESSION}.session.recording && {PARTS}.length === {n + 1 if adds else n}", timeout=30, what="the take kept")

    def play(seconds, loop=False):
        if loop:
            f.run(f"const s = {SESSION}; s.session.practice.phrase = 0; await s.toggleLoop();")
        f.run(f"{AUDIO}.engine.playing || document.dispatchEvent(new KeyboardEvent('keydown', {{ code: 'Space', key: ' ', bubbles: true }}))")
        f.wait(f"{AUDIO}.engine.playing", what="playing")
        time.sleep(seconds)
        f.run(f"{AUDIO}.engine.playing && document.dispatchEvent(new KeyboardEvent('keydown', {{ code: 'Space', key: ' ', bubbles: true }}))")
        f.wait(f"!{AUDIO}.engine.playing", what="stopped")
        if loop:
            f.run(f"const s = {SESSION}; s.session.practice.loop && await s.toggleLoop(); s.session.practice.phrase = null;")

    def sample():
        # Measured on Home, the studio left behind: what the studio shows depends on where it stopped (an empty next
        # track's row comes and goes), but nothing it made should stay once it's gone. Nothing passing on screen either.
        f.run(f"{ROUTER}.navigate('/')")
        f.wait("location.pathname === '/' && document.querySelector('h1')", what="Home")
        f.wait("(await import('/js/components/ui.js')).toasts.list.length === 0", timeout=20, what="the toasts gone")
        time.sleep(1)
        c.send("HeapProfiler.collectGarbage")
        time.sleep(0.5)
        c.send("HeapProfiler.collectGarbage")
        time.sleep(0.3)
        m = {x["name"]: x["value"] for x in c.send("Performance.getMetrics")["metrics"]}
        audio = f.js("({ live: window.__audio.created - window.__audio.collected, buffers: window.__audio.bufferBytes, contexts: window.__audio.contexts, byType: Object.fromEntries(Object.entries(window.__audio.byType).filter(([, n]) => n > 0)) })")
        # The page's elements (Chrome's own Nodes metric also counts detached ones not yet collected, which wobble).
        return {"heap": m["JSHeapUsedSize"], "nodes": f.js("document.getElementsByTagName('*').length"), "listeners": int(m["JSEventListeners"]), **audio}

    def cycle():
        go(songs[0])
        play(4)
        tracks = f.js(f"{PARTS}.length")
        record(5)
        f.run(f"const s = {SESSION}; await s.dropPart(s.session.parts.at(-1));")
        f.wait(f"{PARTS}.length === {tracks}", what=f"back to {tracks} tracks")
        go(songs[1])
        play(2)
        play(3, loop=True)
        go(songs[0])

    # The 8-track song: the first take sets its length; the rest stop by themselves at its end.
    go(songs[0])
    record(5)
    for _ in range(7):
        record(6)
    assert f.js(f"{PARTS}.length") == 8, f"the song has {f.js(f'{PARTS}.length')} tracks, not 8"
    go(songs[1])
    record(3, adds=False)  # the template's next part, over loopback
    cycle()  # a warm-up: caches and first-use objects settle
    before = sample()
    for _ in range(CYCLES - 1):
        cycle()
    after = sample()
    for key in ("nodes", "listeners", "live", "buffers", "contexts"):
        assert after[key] <= before[key], f"the studio leaks over {CYCLES - 1} cycles: {key} went from {before[key]} to {after[key]} ({before['byType']} to {after['byType']})"
    assert after["heap"] - before["heap"] < 2**20, f"the studio's JS heap grew {round((after['heap'] - before['heap']) / 2**20, 2)} MB over {CYCLES - 1} cycles"


if __name__ == "__main__":
    run("studio_soak", journey, sizes=("desktop",))
