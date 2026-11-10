"""Clap along (docs/wireless-timing-plan.md): before recording with Bluetooth, the timing check asks for claps on
steady clicks instead of holding an earbud to the mic, with the AirPods tip and the click test a tap away. A simulated
clapper stands in for the mic (the engine's measure: claps 200 ms behind each click, a human's ±20 ms, the usual lead),
so the result is known; a clapper all over the place is asked to try again; skipping uses a typical wireless delay.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"

# The engine's measure, as a clapper: claps `roundTrip - lead` ms after each click it plays, ± `jitter` ms.
CLAPPER = """((roundTrip, jitter) => {
    let s = 7;
    const uniform = () => ((s = (s * 16807) % 2147483647) / 2147483647);
    const normal = () => Math.sqrt(-2 * Math.log(uniform() + 1e-12)) * Math.cos(2 * Math.PI * uniform());
    return async (signal, { captureLength }) => {
        const tc = await import('/js/timing-check.js');
        const rate = (await import('/js/audio.js')).engine.sampleRate;
        // As long as the real clicks would take (the stub answers at once otherwise, and the listening state can come and
        // go before the flow sees it): a wait in a test's stand-in, nothing scheduled for sound.
        await new Promise((r) => setTimeout(r, (captureLength / rate) * 1000));
        const out = new Float32Array(captureLength);
        for (let i = 0; i < out.length; i++) out[i] = 0.002 * normal();
        tc.clapClicks(rate).positions.forEach((position, i) => {
            if (i === 0) return;
            const start = Math.round(position + ((roundTrip - tc.CLAP_LEAD_MS + jitter * normal()) / 1000) * rate);
            for (let k = 0; k < 0.04 * rate && start + k < out.length; k++) if (start + k >= 0) out[start + k] += 0.5 * Math.exp(-k / (0.008 * rate)) * Math.max(-1, Math.min(1, normal() * 0.5));
        });
        return out;
    };
})"""


def journey(f):
    f.go("/", "document.querySelector('h1')")
    f.mic_checked()
    project = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{project}", "document.querySelectorAll('canvas').length > 3")
    f.js(f"{AUDIO}.unlock()")
    soprano = f.js("(await import('/js/i18n.js')).t('parts.soprano')")
    f.click_t("studio.recordNamed", {"name": soprano})
    f.wait("document.querySelector('[data-headphones]')", what="the headphones question")
    f.click_t("headphones.start")
    f.wait("document.querySelector('[data-timing]')", what="the timing check")
    # Bluetooth: clap along, the AirPods tip, and the click test a tap away.
    bluetooth = f.js("(await import('/js/i18n.js')).t('timing.route.bluetooth')")
    f.run(f"[...document.querySelectorAll('[data-timing] [role=radio]')].find((b) => b.textContent.trim() === {bluetooth!r}).click()")
    f.wait("document.querySelector('[data-clap-tip]')", what="the AirPods tip")
    hint = f.js("(await import('/js/i18n.js')).t('timing.clap.hint')")
    assert f.js("document.querySelector('[data-timing-hint]').textContent.trim()") == hint, "the clap-along hint"
    skip = f.js("(await import('/js/i18n.js')).t('timing.clap.skip')")
    assert f.js(f"[...document.querySelectorAll('[data-timing] button')].some((b) => b.textContent.trim() === {skip!r})"), "Bluetooth's skip uses a typical wireless delay"
    intro = f.js("(await import('/js/i18n.js')).t('timing.clap.intro')")
    assert f.js(f"document.body.innerText.includes({intro!r})"), "the intro says it listens for your claps"
    f.shot("clap-choose")
    f.run("document.querySelector('[data-clap-switch]').click()")
    f.wait(f"document.querySelector('[data-timing-hint]').textContent.trim() !== {hint!r}", what="the click test's hint")
    start = f.js("(await import('/js/i18n.js')).t('timing.start')")
    assert f.js("document.querySelector('[data-timing-start]').textContent.trim()") == start, "the click test's own Start"
    f.run("document.querySelector('[data-clap-switch]').click()")
    # A steady clapper, 200 ms round trip: found within 15 ms and kept.
    f.run(f"{AUDIO}.engine.measure = {CLAPPER}(200, 20);")
    f.run("document.querySelector('[data-timing-start]').click()")
    f.wait("document.querySelector('[data-timing-listening]')", what="clapping along")
    f.shot("clap-measuring")
    f.wait(f"!document.querySelector('[data-timing-listening]')", what="the claps read", timeout=30)
    ms = f.js(f"(async () => {{ const a = {AUDIO}; return a.audio.latencyFrames * 1000 / a.engine.sampleRate; }})()")
    assert ms is not None and abs(ms - 200) <= 15 and not f.js(f"{AUDIO}.audio.latencyEstimated"), f"the clapped delay: {ms}"
    f.shot("clap-result")
    # All over the place (±200 ms): asked to try again, right on the click.
    f.click_t("timing.again")
    f.run(f"{AUDIO}.engine.measure = {CLAPPER}(200, 200);")
    f.wait("document.querySelector('[data-timing-start]')", what="the check again")
    f.run("document.querySelector('[data-timing-start]').click()")
    uneven = f.js("(await import('/js/i18n.js')).t('timing.clap.uneven')")
    f.wait(f"document.querySelector('[data-timing-failed]')?.textContent.trim() === {uneven!r}", what="try again, on the click", timeout=30)
    f.shot("clap-uneven")
    f.no_sideways("the clap-along check")


if __name__ == "__main__":
    run("timing_clap", journey)
