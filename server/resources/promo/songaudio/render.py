#!/usr/bin/env python3
"""A song's full mix for its page: every part together, at the song's own key and tempo, with no metronome
and no count-in (Hillel: "expose the main audio for the song ... so previews on Google or AI responses can hear
the audio, they only need to visit to sing along").

    python3 render.py --song=silent-night            one song (a version changed, a guide was published)
    python3 render.py --song=silent-night,danny-boy  several
    python3 render.py --all                          every open song (the public catalogue's list)
    options: --out=DIR (default out/ here, never committed), --mono (one channel, no room),
             --http=8795 --port-base=9610 (the page's server and Chrome's port), --no-gate

The page (mix.js) mixes the song in headless Chrome on an OfflineAudioContext with the app's own engine: a part's
real voice where the public catalogue (GET /api/templates, the app's own call) has one for this version of the
song, otherwise the app's guide voice. Here the mix is levelled to about -16 LUFS (one gain for the whole file,
its true peak kept under -1.5 dB) and written as <slug>.mp3 (128 kbps, with ID3 title, "Stackapella" and the
song's credit line) and <slug>.m4a (AAC). manifest.json lists every song rendered: its version, the guides used,
its length, and each file's bytes and sha256. A song whose template here is another version than the
catalogue's is refused: link the content first.

One render at a time: before each song it waits for the Mac's 5-minute load to fall under 14.
"""
import base64
import hashlib
import importlib.util
import json
import os
import re
import subprocess
import sys
import time
import urllib.request
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[3]
A = {}
for arg in sys.argv[1:]:
    k, _, v = arg.lstrip('-').partition('=')
    A[k] = v if v else True

# the showreel's page server, Chrome driver and ffmpeg (its render.py reads its own arguments on import)
argv, sys.argv = sys.argv, sys.argv[:1]
spec = importlib.util.spec_from_file_location('showreel_render', HERE.parent / 'showreel/render.py')
R = importlib.util.module_from_spec(spec)
spec.loader.exec_module(R)
sys.argv = argv

CATALOGUE = os.environ.get('CATALOGUE', 'https://stackapella.com/api/templates')
OUT = Path(str(A['out'])).expanduser() if A.get('out') else HERE / 'out'
VOICES = HERE / 'voices'
HTTP, PORT = int(A.get('http', 8795)), int(A.get('port-base', 9610)) + 40
PAGE = f'http://127.0.0.1:{HTTP}/{HERE.relative_to(ROOT)}/mix.html'
TARGET, PEAK = -16.0, -1.5


def get(url):
    return urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'stackapella-showreel', 'Accept': 'application/json'}), timeout=120).read()


def gate():
    if A.get('no-gate'):
        return
    while True:
        load = os.getloadavg()[1]
        if load < 14:
            return
        print(f'waiting, 5-min load {load:.2f}', flush=True)
        time.sleep(30)


def guides_of(song):
    """Each part's default real voice in the catalogue, its audio kept in voices/ (by its id)."""
    VOICES.mkdir(exist_ok=True)
    found = {}
    for part in song['parts']:
        v = next((x for x in part.get('versions') or [] if x.get('default')), None) or part.get('guide')
        if not v or not v.get('url'):
            continue
        file = VOICES / f"{v['id']}.m4a"
        if not file.exists():
            file.write_bytes(get(v['url']))
        found[part['key']] = {**{k: v.get(k) for k in ('id', 'sampleRate', 'frames', 'offsetFrames', 'credit')}, 'file': f'voices/{file.name}'}
    return found


def credit_line(info):
    """"Words: ... Tune: ... Arrangement: Stackapella." and who sings the real voices."""
    names = {'words': 'Words', 'tune': 'Tune', 'music': 'Music', 'arrangement': 'Arrangement'}
    bits = [f"{names.get(k, k.capitalize())}: {v}." for k, v in (info.get('credits') or {}).items() if v]
    # who sings which real voice: only the parts whose singer chose to be named
    by = {}
    for p in info['parts']:
        if p['credit']:
            by.setdefault(p['credit'], []).append(p['key'])
    real = [p for p in info['parts'] if p['guide']]
    for name, keys in by.items():
        if len(keys) == len(info['parts']):
            bits.append(f'Sung by {name}.')
        else:
            bits.append(f"{' and '.join([', '.join(keys[:-1]), keys[-1]] if len(keys) > 1 else keys).capitalize()} sung by {name}.")
    return ' '.join(bits)


def loudness(wav):
    """The file's integrated loudness (LUFS) and true peak (dB), as ffmpeg's loudnorm measures them."""
    err = subprocess.run([R.FFMPEG, '-hide_banner', '-nostats', '-i', str(wav), '-af', f'loudnorm=I={TARGET}:TP={PEAK}:print_format=json', '-f', 'null', '-'],
                         capture_output=True, text=True).stderr
    m = json.loads(re.search(r'\{[^{}]*"input_i"[^{}]*\}', err).group(0))
    return float(m['input_i']), float(m['input_tp'])


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def render(browser, song):
    slug = song['id']
    file = next((f for f in (ROOT / f'server/database/templates/{slug}.json', ROOT / f'web/templates/{slug}.json') if f.exists()), None)
    if not file:
        raise RuntimeError(f'no template here for {slug}: link the content first')
    local = json.loads(file.read_text())
    if local['version'] != song['version']:
        raise RuntimeError(f"the catalogue's {slug} is version {song['version']}, the template here {local['version']}: link the content first")
    guides = guides_of(song)
    gate()
    stereo = 'false' if A.get('mono') else 'true'
    info = browser.cdp.eval(f'mixSong({json.dumps(slug)}, {json.dumps(guides)}, {{ stereo: {stereo} }})', wait=True)
    OUT.mkdir(parents=True, exist_ok=True)
    wav = OUT / f'{slug}.wav'
    with wav.open('wb') as f:
        for i in range(browser.cdp.eval('wavChunks()')):
            f.write(base64.b64decode(browser.cdp.eval(f'wavChunk({i})')))
    lufs, peak = loudness(wav)
    gain = min(TARGET - lufs, PEAK - peak)  # one gain for the whole song: nothing pumps
    credit = credit_line(info)
    tags = ['-metadata', f"title={info['title']}", '-metadata', 'artist=Stackapella', '-metadata', 'album=Stackapella', '-metadata', f'comment={credit}']
    base = [R.FFMPEG, '-y', '-hide_banner', '-loglevel', 'error', '-i', str(wav), '-af', f'volume={gain:.2f}dB', '-map_metadata', '-1']
    mp3, m4a = OUT / f'{slug}.mp3', OUT / f'{slug}.m4a'
    R.run(base + ['-c:a', 'libmp3lame', '-b:a', '128k', '-id3v2_version', '3', '-write_id3v1', '1'] + tags + [str(mp3)])
    R.run(base + ['-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart'] + tags + [str(m4a)])
    wav.unlink()
    entry = {
        'slug': slug, 'title': info['title'], 'version': info['version'], 'tempo': info['tempo'], 'key': info['key'],
        'guides': {p['key']: p['guide'] for p in info['parts']},  # a part's real-voice guide id, or null: the app's own voice
        'credit': credit, 'duration': round(info['duration'], 3), 'channels': 2 if info['stereo'] else 1,
        'lufs': round(lufs + gain, 1),
        'files': [{'name': p.name, 'bytes': p.stat().st_size, 'sha256': sha(p)} for p in (mp3, m4a)],
    }
    real = sum(1 for g in entry['guides'].values() if g)
    print(f"{slug} v{entry['version']}: {entry['duration']:.1f} s, {real} of {len(entry['guides'])} parts a real voice, {entry['lufs']} LUFS, mp3 {mp3.stat().st_size / 1e6:.2f} MB", flush=True)
    return entry


def main():
    catalogue = {t['id']: t for t in json.loads(get(CATALOGUE))['data']}
    if A.get('all'):
        slugs = sorted(catalogue)
    elif A.get('song'):
        slugs = str(A['song']).split(',')
    else:
        raise SystemExit(__doc__)
    missing = [s for s in slugs if s not in catalogue]
    if missing:
        raise SystemExit(f"not open in the catalogue: {', '.join(missing)}")
    path = OUT / 'manifest.json'
    manifest = json.loads(path.read_text()) if path.exists() else {'source': 'server/resources/promo/songaudio', 'songs': {}}
    httpd, failed = R.serve(HTTP), []
    browser = R.Browser(PORT, PAGE, 320, 240)
    try:
        for slug in slugs:
            try:
                manifest['songs'][slug] = render(browser, catalogue[slug])
                manifest['songs'] = dict(sorted(manifest['songs'].items()))
                path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + '\n')
            except Exception as error:  # one song's trouble never stops the rest; it is named at the end
                failed.append(slug)
                print(f'{slug} FAILED: {error}', file=sys.stderr, flush=True)
    finally:
        browser.close()
        httpd.shutdown()
    print(f'{path} ({len(manifest["songs"])} songs)')
    if failed:
        raise SystemExit(f"failed: {', '.join(failed)}")


if __name__ == '__main__':
    main()
