#!/usr/bin/env python3
"""Frame-accurate renderer for showreel.html. No packages beyond Pillow (for the blur and the
posters): the page is served by Python's own http.server, Chrome is driven over the DevTools
protocol with a small WebSocket client written here, and frames and sound are encoded by ffmpeg.
See README.md.

    python3 render.py --serve                         the preview player at http://127.0.0.1:8790/
    python3 render.py --stills=0,2.9,13.5 --out=DIR   PNG stills (half scale; --scale=1 for full)
    python3 render.py --preview --out=preview.mp4     960x540, 30fps, no motion blur, with sound
    python3 render.py --final --theme=dark            1920x1080 60fps WebM and MP4, 8 to 16-sample blur
    python3 render.py --mobile --theme=dark           960x540 30fps MP4 with blur
    python3 render.py --posters --theme=dark          the posters (JPEG, and WebP at three widths)
    python3 render.py --audio                         the soundtrack (WAV, M4A and Opus)
    python3 render.py --vtt                           the captions track (WebVTT): each phrase's line and what is heard
    python3 render.py --launch=vertical               a launch cut from the same film: vertical (1080x1920, social),
                                                      square (1080x1080) or thumb (Product Hunt's 240 px loop, MP4 and GIF)
    python3 render.py --manifest                      manifest.json: every output's name, size and sha256

Options: --theme=dark|light, --from=S --to=S, --workers=N, --scale=F, --fps=N, --samples=N,
--keep (leave the frames), --port-base=N (Chrome's first debugging port, default 9500),
--http=N (the page's port, default 8790), --dir=DIR (the outputs, default
server/public/videos/showreel), --poster-at=S.
"""
import base64
import hashlib
import http.server
import json
import os
import re
import shutil
import socket
import struct
import subprocess
import sys
import tempfile
import threading
import time
import urllib.request
from functools import partial
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[3]  # the repository: the page imports /web/js/synth.js and the song's template
PAGE = '/' + str(HERE.relative_to(ROOT)) + '/showreel.html'
OUT = ROOT / 'server/public/videos/showreel'
NAME = 'stackapella-showreel'
CHROME = os.environ.get('CHROME', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')
FFMPEG = os.environ.get('FFMPEG', shutil.which('ffmpeg') or 'ffmpeg')
DUR = float(re.search(r'\bDUR = (\d+(?:\.\d+)?)', (HERE / 'showreel.html').read_text()).group(1))
# the poster: the end card, its words up ("Harmony, made by you."), between its glints
POSTER_AT = 30.0

A = {}
for arg in sys.argv[1:]:
    k, _, v = arg.lstrip('-').partition('=')
    A[k] = v if v else True


# ---------------------------------------------------------------- the page's server
class Quiet(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def end_headers(self):
        self.send_header('Cache-Control', 'no-store')
        super().end_headers()


def serve(port):
    httpd = http.server.ThreadingHTTPServer(('127.0.0.1', port), partial(Quiet, directory=str(ROOT)))
    threading.Thread(target=httpd.serve_forever, daemon=True).start()
    return httpd


# ---------------------------------------------------------------- a WebSocket client (RFC 6455)
class Socket:
    def __init__(self, url):
        m = re.match(r'ws://([^/:]+):(\d+)(/.*)', url)
        host, port, path = m.group(1), int(m.group(2)), m.group(3)
        self.s = socket.create_connection((host, port))
        key = base64.b64encode(os.urandom(16)).decode()
        self.s.sendall((f'GET {path} HTTP/1.1\r\nHost: {host}:{port}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n'
                        f'Sec-WebSocket-Key: {key}\r\nSec-WebSocket-Version: 13\r\n\r\n').encode())
        head = b''
        while b'\r\n\r\n' not in head:
            head += self.s.recv(1)
        if b' 101 ' not in head.split(b'\r\n')[0]:
            raise RuntimeError('WebSocket handshake refused: ' + head.decode(errors='replace'))

    def _read(self, n):
        buf = bytearray()
        while len(buf) < n:
            chunk = self.s.recv(min(1 << 20, n - len(buf)))
            if not chunk:
                raise ConnectionError('Chrome closed the connection')
            buf += chunk
        return bytes(buf)

    def send(self, text):
        data = text.encode()
        head = bytearray([0x81])
        n = len(data)
        if n < 126:
            head.append(0x80 | n)
        elif n < 1 << 16:
            head.append(0x80 | 126); head += struct.pack('>H', n)
        else:
            head.append(0x80 | 127); head += struct.pack('>Q', n)
        mask = os.urandom(4)
        head += mask
        self.s.sendall(bytes(head) + bytes(b ^ mask[i % 4] for i, b in enumerate(data)))

    def recv(self):
        message = b''
        while True:
            b0, b1 = self._read(2)
            n = b1 & 0x7F
            if n == 126:
                n = struct.unpack('>H', self._read(2))[0]
            elif n == 127:
                n = struct.unpack('>Q', self._read(8))[0]
            payload = self._read(n)
            op = b0 & 0x0F
            if op == 0x9:  # ping
                continue
            if op == 0x8:
                raise ConnectionError('Chrome closed the WebSocket')
            message += payload
            if b0 & 0x80:
                return message.decode()


class CDP:
    def __init__(self, url):
        self.ws, self.id = Socket(url), 0

    def send(self, method, params=None):
        self.id += 1
        me = self.id
        self.ws.send(json.dumps({'id': me, 'method': method, 'params': params or {}}))
        while True:
            m = json.loads(self.ws.recv())
            if m.get('id') == me:
                if 'error' in m:
                    raise RuntimeError(f'{method}: {m["error"]}')
                return m.get('result', {})
            if m.get('method') == 'Runtime.exceptionThrown':
                d = m['params']['exceptionDetails']
                print('[page]', d.get('exception', {}).get('description') or d.get('text'), file=sys.stderr)
            if m.get('method') == 'Runtime.consoleAPICalled' and m['params']['type'] in ('error', 'warning'):
                print('[console]', ' '.join(str(a.get('value', a.get('description'))) for a in m['params']['args']), file=sys.stderr)

    def eval(self, expression, wait=False):
        r = self.send('Runtime.evaluate', {'expression': expression, 'awaitPromise': wait, 'returnByValue': True})
        if 'exceptionDetails' in r:
            raise RuntimeError(r['exceptionDetails'].get('exception', {}).get('description') or str(r['exceptionDetails']))
        return r['result'].get('value')


class Browser:
    def __init__(self, port, url, width, height):
        self.dir = tempfile.mkdtemp(prefix='showreel-chrome-')
        self.proc = subprocess.Popen([CHROME, '--headless=new', f'--remote-debugging-port={port}', f'--user-data-dir={self.dir}',
                                      '--hide-scrollbars', '--force-device-scale-factor=1', '--force-color-profile=srgb', '--no-first-run',
                                      '--no-default-browser-check', '--disable-background-timer-throttling', '--disable-renderer-backgrounding',
                                      '--autoplay-policy=no-user-gesture-required', '--mute-audio', 'about:blank'],
                                     stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        targets = []
        for _ in range(200):
            try:
                targets = json.load(urllib.request.urlopen(f'http://127.0.0.1:{port}/json'))
                if any(t['type'] == 'page' for t in targets):
                    break
            except OSError:
                pass
            time.sleep(0.1)
        self.cdp = CDP(next(t for t in targets if t['type'] == 'page')['webSocketDebuggerUrl'])
        self.cdp.send('Runtime.enable')
        self.cdp.send('Page.enable')
        self.cdp.send('Emulation.setDeviceMetricsOverride', {'width': width, 'height': height, 'deviceScaleFactor': 1, 'mobile': False})
        self.cdp.send('Page.navigate', {'url': url})
        for k in range(600):
            try:
                if self.cdp.eval('window.__ready === true'):
                    break
            except RuntimeError:
                pass
            time.sleep(0.05)
        else:
            raise RuntimeError('the page never became ready')

    def grab(self, t, fmt='jpeg'):
        self.cdp.eval(f'seek({t});new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))', wait=True)
        opts = {'format': 'png'} if fmt == 'png' else {'format': 'jpeg', 'quality': 95, 'optimizeForSpeed': True}
        return base64.b64decode(self.cdp.send('Page.captureScreenshot', opts)['data'])

    def close(self):
        self.proc.kill()
        self.proc.wait()
        shutil.rmtree(self.dir, ignore_errors=True)


# ---------------------------------------------------------------- the renders
theme = 'light' if A.get('theme') == 'light' else 'dark'
http_port = int(A.get('http', 8790))
port_base = int(A.get('port-base', 9500))
out_dir = Path(A['dir']).resolve() if 'dir' in A else OUT
suffix = '-light' if theme == 'light' else ''
# The frame of each format: lib.js's FORMATS, which the page checks against this (window.FRAME).
FORMATS = {'wide': (1920, 1080), 'vertical': (1080, 1920), 'square': (1080, 1080), 'thumb': (480, 480)}
fmt = A.get('launch') or A.get('format') or 'wide'
if fmt not in FORMATS:
    raise SystemExit(f'no format {fmt}: {", ".join(FORMATS)}')
FW, FH = FORMATS[fmt]
LAUNCH = 'stackapella-launch'


def page_url(scale):
    return f'http://127.0.0.1:{http_port}{PAGE}?render=1&scale={scale}&theme={theme}&format={fmt}'


def launch(i, scale):
    b = Browser(port_base + i, page_url(scale), round(FW * scale), round(FH * scale))
    if b.cdp.eval('window.FRAME') != [FW, FH]:
        raise RuntimeError(f'render.py and lib.js disagree about the {fmt} frame')
    shown = b.cdp.eval('window.THEME')
    if shown != theme:
        raise RuntimeError(f'asked for the {theme} cut but the page rendered {shown}')
    return b


def run(cmd):
    r = subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
    if r.returncode:
        raise RuntimeError(' '.join(cmd[:3]) + ' failed:\n' + r.stderr.decode()[-2000:])


def stills():
    scale = float(A.get('scale', 0.5))
    out = Path(A.get('out', 'stills')).resolve()
    out.mkdir(parents=True, exist_ok=True)
    b = launch(0, scale)
    try:
        for t in [float(x) for x in str(A['stills']).split(',')]:
            f = out / f'{theme}-t{t:05.2f}.png'
            f.write_bytes(b.grab(t, 'png'))
            print(f)
    finally:
        b.close()


def edit_of(scale):
    """The page's edit for this format (film.js EDIT), its fast windows and its cuts."""
    probe = launch(0, scale)
    try:
        return probe.cdp.eval('window.EDIT'), probe.cdp.eval('window.FAST'), probe.cdp.eval('window.CUTS')
    finally:
        probe.close()


def frames(scale, fps, samples, fast_samples, workers, t0, t1, tmp, edit):
    """Captures every frame of the edit (its stretches end to end) between t0 and t1 of the output
    into tmp as fNNNNN.jpg, motion-blurred. Returns the first frame's number."""
    from blend import average
    edit, fast, cuts = edit
    # each output frame: the stretch it is in, and the film's time at its middle
    plan, n0 = [], 0
    for a, b in edit:
        n = round((b - a) * fps)
        plan += [(a, b, a + k / fps) for k in range(n)]
        n0 += n
    f0, f1 = round(t0 * fps), min(len(plan), round(t1 * fps))
    shutter = 0.5  # 180 degrees

    def times(f):
        a, b, c = plan[f]
        n = fast_samples if any(x <= c <= y for x, y in fast) else samples
        # a frame's blur samples stay on its own side of a cut, and inside its own stretch of the
        # edit: one that straddled either would average two shots into a one-frame dissolve
        lo = max([a] + [k for k in cuts if k <= c + 1e-9])
        hi = min([b] + [k for k in cuts if k > c + 1e-9])
        return [min(hi - 1e-4, max(lo, c + (((s + 0.5) / n - 0.5) * shutter / fps if n > 1 else 0))) for s in range(n)]

    done, started, lock = [0], time.time(), threading.Lock()
    chunk = -(-(f1 - f0) // workers)
    errors = []

    def work(w):
        a, b = f0 + w * chunk, min(f1, f0 + (w + 1) * chunk)
        if a >= b:
            return
        br = launch(w, scale)
        try:
            for f in range(a, b):
                shots = [br.grab(t) for t in times(f)]
                path = tmp / f'f{f:05d}.jpg'
                if len(shots) == 1:
                    path.write_bytes(shots[0])
                else:
                    average(shots).save(path, quality=94, subsampling=0)
                with lock:
                    done[0] += 1
                    if done[0] % 60 == 0:
                        el = time.time() - started
                        print(f'  {done[0]}/{f1 - f0} frames, {el:.0f}s, ~{el / done[0] * (f1 - f0 - done[0]):.0f}s left', flush=True)
        except Exception as e:  # noqa: BLE001 - reported below, and the render fails
            errors.append(e)
        finally:
            br.close()

    threads = [threading.Thread(target=work, args=(w,)) for w in range(workers)]
    [t.start() for t in threads]
    [t.join() for t in threads]
    if errors:
        raise errors[0]
    print(f'captured {f1 - f0} frames in {time.time() - started:.0f}s')
    return f0


def soundtrack(path, edit=None, fps=None):
    """The page renders the soundtrack on an OfflineAudioContext; this saves it as a WAV. With an
    edit, its stretches are joined end to end, each exactly as long as its frames, with a short
    fade either side of each join."""
    b = Browser(port_base + 40, page_url(0.25), round(FW / 4), round(FH / 4))
    try:
        path.write_bytes(base64.b64decode(b.cdp.eval('window.renderWav()', wait=True)))
    finally:
        b.close()
    if not edit or len(edit) == 1 and edit[0] == [0, DUR]:
        return path
    full, parts, chain = path.with_name('whole.wav'), [], []
    path.rename(full)
    for k, (a, c) in enumerate(edit):
        n = round((c - a) * fps) / fps
        parts.append(f'[0:a]atrim={a:.5f}:{a + n:.5f},asetpts=PTS-STARTPTS,afade=t=in:d=0.012,afade=t=out:st={n - 0.025:.5f}:d=0.025[s{k}]')
        chain.append(f'[s{k}]')
    run([FFMPEG, '-y', '-hide_banner', '-loglevel', 'error', '-i', str(full), '-filter_complex',
         ';'.join(parts) + ';' + ''.join(chain) + f'concat=n={len(edit)}:v=0:a=1[out]', '-map', '[out]', str(path)])
    return path


# The frames are JPEGs (full-range BT.601). Convert to limited-range BT.709 and say so in the
# stream, or some decoders crush the contrast and shift the part colours (Event Schedule's lesson).
COLOURS = ['-vf', 'scale=in_range=pc:out_range=tv:in_color_matrix=bt601:out_color_matrix=bt709,format=yuv420p',
          '-color_range', 'tv', '-colorspace', 'bt709', '-color_primaries', 'bt709', '-color_trc', 'bt709']


def encode(tmp, f0, fps, wav, mp4=None, webm=None, bitrate='2400k', t0=0.0, t1=DUR, size=None):
    frames_in = ['-framerate', str(fps), '-start_number', str(f0), '-i', str(tmp / 'f%05d.jpg')]
    audio_in = ['-ss', f'{t0:.4f}', '-t', f'{t1 - t0:.4f}', '-i', str(wav)] if wav else []
    COLOUR = COLOURS if not size else [COLOURS[0], f'scale={size}:flags=lanczos,' + COLOURS[1], *COLOURS[2:]]
    log = str(tmp / 'pass')
    if mp4:
        for p in (1, 2):
            run([FFMPEG, '-y', '-hide_banner', '-loglevel', 'error', *frames_in, *audio_in, *COLOUR, '-c:v', 'libx264', '-preset', 'slow',
                 '-profile:v', 'high', '-b:v', bitrate, '-maxrate', '6M', '-bufsize', '8M', '-pass', str(p), '-passlogfile', log + 'x',
                 *(['-an', '-f', 'mp4', os.devnull] if p == 1 else
                   (['-map', '0:v', '-map', '1:a', '-c:a', 'aac', '-b:a', '128k'] if wav else []) + ['-shortest', '-movflags', '+faststart', str(mp4)])])
        print(f'{mp4} ({mp4.stat().st_size / 1048576:.2f} MB)')
    if webm:
        for p in (1, 2):
            run([FFMPEG, '-y', '-hide_banner', '-loglevel', 'error', *frames_in, *audio_in, *COLOUR, '-c:v', 'libvpx-vp9', '-b:v', bitrate,
                 '-row-mt', '1', '-deadline', 'good', '-cpu-used', '4' if p == 1 else '1', '-pass', str(p), '-passlogfile', log + 'v',
                 *(['-an', '-f', 'webm', os.devnull] if p == 1 else
                   (['-map', '0:v', '-map', '1:a', '-c:a', 'libopus', '-b:a', '112k'] if wav else []) + ['-shortest', str(webm)])])
        print(f'{webm} ({webm.stat().st_size / 1048576:.2f} MB)')


def video():
    if A.get('final') and A.get('mobile'):
        raise SystemExit('--final and --mobile are separate renders: pass one')
    # the launch cuts: social at 30 fps with room in the bitrate for the platforms' own re-encode;
    # the thumbnail drawn at 480 and made 240, a GIF and an MP4
    LAUNCHES = {'vertical': {'scale': 1, 'fps': 30, 'samples': 8, 'fast': 16, 'bitrate': '6000k'},
                'square': {'scale': 1, 'fps': 30, 'samples': 8, 'fast': 16, 'bitrate': '5000k'},
                'thumb': {'scale': 1, 'fps': 20, 'samples': 4, 'fast': 8, 'bitrate': '600k'}}
    preset = (LAUNCHES[fmt] if A.get('launch') else
              {'scale': 1, 'fps': 60, 'samples': 8, 'fast': 16, 'bitrate': '2600k'} if A.get('final') else
              {'scale': 0.5, 'fps': 30, 'samples': 8, 'fast': 16, 'bitrate': '900k'} if A.get('mobile') else
              {'scale': 0.5, 'fps': 30, 'samples': 1, 'fast': 1, 'bitrate': '1500k'})
    scale, fps = float(A.get('scale', preset['scale'])), int(A.get('fps', preset['fps']))
    samples = int(A.get('samples', preset['samples']))
    fast = max(samples, int(A.get('fast-samples', preset['fast'])))
    workers = int(A.get('workers', max(1, min(6, (os.cpu_count() or 4) - 4))))
    edit = edit_of(scale)
    length = sum(round((b - a) * fps) for a, b in edit[0]) / fps
    t0, t1 = float(A.get('from', 0)), min(length, float(A.get('to', length)))
    tmp = Path(tempfile.mkdtemp(prefix='showreel-frames-'))
    print(f'{fmt}: {round((t1 - t0) * fps)} frames at {round(FW * scale)}x{round(FH * scale)} {fps}fps, {samples} sample(s) ({fast} when fast), {workers} worker(s) -> {tmp}')
    try:
        f0 = frames(scale, fps, samples, fast, workers, t0, t1, tmp, edit)
        wav = soundtrack(tmp / 'sound.wav', edit[0], fps) if not A.get('silent') and fmt != 'thumb' else None
        if A.get('launch'):
            out_dir.mkdir(parents=True, exist_ok=True)
            base = out_dir / f'{LAUNCH}-{fmt}{suffix}'
            if fmt == 'thumb':
                encode(tmp, f0, fps, None, mp4=base.with_suffix('.mp4'), bitrate=preset['bitrate'], t0=t0, t1=t1, size='240:240')
                gif(tmp, f0, fps, base.with_suffix('.gif'))
            else:
                encode(tmp, f0, fps, wav, mp4=base.with_suffix('.mp4'), bitrate=A.get('bitrate', preset['bitrate']), t0=t0, t1=t1)
        elif A.get('final'):
            out_dir.mkdir(parents=True, exist_ok=True)
            encode(tmp, f0, fps, wav, mp4=out_dir / f'{NAME}{suffix}.mp4', webm=out_dir / f'{NAME}{suffix}.webm', bitrate=A.get('bitrate', preset['bitrate']), t0=t0, t1=t1)
        elif A.get('mobile'):
            out_dir.mkdir(parents=True, exist_ok=True)
            encode(tmp, f0, fps, wav, mp4=out_dir / f'{NAME}{suffix}-mobile.mp4', bitrate=A.get('bitrate', preset['bitrate']), t0=t0, t1=t1)
        else:
            out = Path(A.get('out', f'preview{suffix}.mp4')).resolve()
            encode(tmp, f0, fps, wav, **({'webm': out} if out.suffix == '.webm' else {'mp4': out}), bitrate=A.get('bitrate', preset['bitrate']), t0=t0, t1=t1)
    finally:
        if A.get('keep'):
            print(f'frames kept in {tmp}')
        else:
            shutil.rmtree(tmp, ignore_errors=True)


def gif(tmp, f0, fps, out):
    """A GIF of the frames at 240 px: one palette for the whole loop, lightly dithered."""
    src = ['-framerate', str(fps), '-start_number', str(f0), '-i', str(tmp / 'f%05d.jpg')]
    pal = tmp / 'palette.png'
    run([FFMPEG, '-y', '-hide_banner', '-loglevel', 'error', *src, '-vf', 'scale=240:240:flags=lanczos,palettegen=max_colors=192:stats_mode=full', str(pal)])
    run([FFMPEG, '-y', '-hide_banner', '-loglevel', 'error', *src, '-i', str(pal), '-lavfi',
         'scale=240:240:flags=lanczos[x];[x][1:v]paletteuse=dither=sierra2_4a', '-loop', '0', str(out)])
    print(f'{out} ({out.stat().st_size / 1048576:.2f} MB)')


def posters():
    """The posters: a clean still (no blur) at POSTER_AT, as a JPEG and as WebP at three widths."""
    from PIL import Image
    at = float(A.get('poster-at', POSTER_AT))
    b = launch(0, 1)
    try:
        png = b.grab(at, 'png')
    finally:
        b.close()
    import io
    im = Image.open(io.BytesIO(png)).convert('RGB')
    out_dir.mkdir(parents=True, exist_ok=True)
    jpg = out_dir / f'{NAME}{suffix}-poster.jpg'
    im.save(jpg, quality=82, optimize=True, progressive=True)
    print(f'{jpg} ({jpg.stat().st_size // 1024} KB)')
    for w in (960, 1440, 1920):
        f = out_dir / f'{NAME}{suffix}-poster-{w}.webp'
        im.resize((w, w * 9 // 16), Image.LANCZOS).save(f, quality=80, method=6)
        print(f'{f} ({f.stat().st_size // 1024} KB)')


def audio():
    out_dir.mkdir(parents=True, exist_ok=True)
    wav = soundtrack(out_dir / f'{NAME}-audio.wav')
    run([FFMPEG, '-y', '-hide_banner', '-loglevel', 'error', '-i', str(wav), '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', str(out_dir / f'{NAME}-audio.m4a')])
    run([FFMPEG, '-y', '-hide_banner', '-loglevel', 'error', '-i', str(wav), '-c:a', 'libopus', '-b:a', '112k', str(out_dir / f'{NAME}-audio.opus')])
    for f in sorted(out_dir.glob(f'{NAME}-audio.*')):
        print(f'{f} ({f.stat().st_size / 1048576:.2f} MB)')


def vtt():
    """The captions track (WebVTT), which the page writes from the score (score.js, vtt())."""
    b = launch(0, 0.25)
    try:
        text = b.cdp.eval('window.renderVtt()')
    finally:
        b.close()
    out_dir.mkdir(parents=True, exist_ok=True)
    (out_dir / f'{NAME}.vtt').write_text(text)
    print(out_dir / f'{NAME}.vtt')


def manifest():
    """What the CDN serves: every output's name, size and sha256, kept in git beside the posters. The
    film's files go in manifest.json (the landing's version is its hash, so only a new film changes
    it); the launch cuts in launch-manifest.json."""
    kinds = ('.mp4', '.webm', '.m4a', '.opus', '.jpg', '.webp', '.vtt', '.gif')
    files = sorted(f for f in out_dir.iterdir() if f.suffix in kinds)
    entry = lambda f: {'name': f.name, 'bytes': f.stat().st_size, 'sha256': hashlib.sha256(f.read_bytes()).hexdigest()}
    for name, mine in (('manifest.json', lambda f: f.name.startswith(NAME)), ('launch-manifest.json', lambda f: f.name.startswith(LAUNCH))):
        chosen = [f for f in files if mine(f)]
        if not chosen:
            continue
        data = {'film': 'Harmony, made by you', 'duration': DUR, 'source': str(HERE.relative_to(ROOT)), 'files': [entry(f) for f in chosen]}
        (out_dir / name).write_text(json.dumps(data, indent=2) + '\n')
        print(out_dir / name, f'({len(chosen)} files)')


def main():
    if A.get('manifest'):
        return manifest()
    httpd = serve(http_port)
    try:
        if A.get('vtt'):
            vtt()
        elif A.get('serve'):
            print(f'http://127.0.0.1:{http_port}{PAGE}  (Ctrl-C to stop)')
            while True:
                time.sleep(3600)
        elif 'stills' in A:
            stills()
        elif A.get('posters'):
            posters()
        elif A.get('audio'):
            audio()
        else:
            video()
    except KeyboardInterrupt:
        pass
    finally:
        httpd.shutdown()


if __name__ == '__main__':
    sys.path.insert(0, str(HERE))
    main()
