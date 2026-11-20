"""Hear yourself as a choir on a singer's own song (own_choir_songs; Hillel: "they could hum it and have their voice
replace their humming"): a hummed song kept with its humming offers "Hear it in your voice" on its page; with no voice
saved, "Make my choir from my humming" sends the humming itself as the clip (its beat 0 unknown) and deletes it from the
device; a song with words in another language says once that it sings English words. Off, the song page has no such way.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SONG = "11111111-2222-4333-8444-555555555555"


def tab(f, songs):
    f.run(f"""sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockOwnChoirSongs', '{'1' if songs else '0'}');
        sessionStorage.setItem('stackapella.mockChoirState', 'fresh'); sessionStorage.removeItem('stackapella.mockChoir');""")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    tab(f, False)
    # One of their own songs (a copy of a bundled one, in Spanish), saved, with a hum kept for it.
    f.run(f"""const {{ loadTemplate }} = await import('/js/templates.js'); const s = await import('/js/stores/templates.js');
        const song = structuredClone(await loadTemplate('amazing-grace'));
        Object.assign(song, {{ id: {json.dumps(SONG)}, title: 'My hummed tune', language: 'es' }});
        await s.saveTemplate(song, {{ visibility: 'private', libraryOptIn: false }});
        const {{ keepHum }} = await import('/js/own-choir.js');
        await keepHum({json.dumps(SONG)}, {{ samples: new Float32Array(48000 * 12).map((_, i) => 0.3 * Math.sin(i / 25)), sampleRate: 48000 }});""")
    f.go(f"/t/{SONG}?api=mock&scenario=&as=member", "document.querySelector('main h1')")
    assert not f.js("document.querySelector('[data-template-choir]')"), "no way in while own songs are off"

    tab(f, True)
    f.go(f"/t/{SONG}?api=mock&scenario=&as=member", "document.querySelector('[data-template-choir]')")
    f.shot("song-page")
    f.run("document.querySelector('[data-template-choir]').click()")
    f.wait("document.querySelector('[data-choir-agree]')", what="the terms")
    f.run("document.querySelector('[data-choir-agree]').click()")
    f.wait("document.querySelector('[data-choir-hum]')", what="Make my choir from my humming")
    assert f.js("!!document.querySelector('[data-choir-english]')"), "says once that it sings English words"
    f.shot("from-hum")
    f.run("document.querySelector('[data-choir-hum]').click()")
    f.wait("document.querySelector('[data-choir-wait]') || document.querySelector('[data-choir-play]')", what="sent: on its way, or already made")
    clip = f.js("globalThis.mockChoirClip")
    assert clip["templateId"] == SONG and clip["offsetFrames"] is None and clip["frames"] == 48000 * 12, clip
    assert f.js(f"(async () => !(await (await import('/js/own-choir.js')).humFor({json.dumps(SONG)})))()"), "the humming is deleted from the device once sent"
    f.run(f"const s = await import('/js/stores/templates.js'); await s.deleteTemplate({json.dumps(SONG)});")


if __name__ == "__main__":
    run("own_choir_songs", journey)
