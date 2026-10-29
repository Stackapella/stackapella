"""Builds the real instruments (docs/instrument-build.md): each CC0 set in sets.json becomes a
folder of MP3s and a manifest.json in contract/instruments.md's format, ready for
`php artisan app:instruments:upload <name> <version> <folder>`.

    python3 -I tool/instruments/build.py list
    python3 -I tool/instruments/build.py build piano [--out DIR]
    python3 -I tool/instruments/build.py build all

Only the files a build uses are fetched (raw files from GitHub at a pinned commit, or a small
archive checked by its SHA-256), into a cache outside the repo ($STACKAPELLA_SAMPLES_CACHE, else
~/Library/Caches/stackapella-samples). No audio is ever written inside the repo. Needs Python 3 and
ffmpeg with libmp3lame; nothing from npm.
"""
import argparse
from array import array
import hashlib
import json
import math
import os
import re
import shutil
import struct
import subprocess
import sys
import tempfile
import urllib.parse
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import sfz  # noqa: E402

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[2]
CACHE = Path(os.environ.get("STACKAPELLA_SAMPLES_CACHE", Path.home() / "Library/Caches/stackapella-samples"))
RATE = 44100
FORMAT = 1
# The onset: the first sample at or over this share of the file's peak.
ONSET_SHARE = 0.05
# Kept before the onset, so the attack's start isn't cut (seconds).
PREROLL = 0.003
NOTE_NAMES = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"]


def fail(message):
    raise SystemExit(f"build: {message}")


def run(args):
    done = subprocess.run(args, capture_output=True)
    if done.returncode != 0:
        fail(f"{args[0]} failed: {done.stderr.decode(errors='replace')[-600:]}")
    return done.stdout


# Sources -------------------------------------------------------------------------------------


class GitHub:
    """A repo at a pinned commit: its file list from the API (once), each file from raw (once)."""

    def __init__(self, repo, commit):
        if not re.fullmatch(r"[0-9a-f]{40}", commit):
            fail(f"{repo}: pin a full 40-character commit")
        self.repo, self.commit = repo, commit
        self.root = CACHE / "github" / repo.replace("/", "_") / commit
        self._tree = None

    def tree(self):
        if self._tree is None:
            listing = CACHE / "trees" / f"{self.repo.replace('/', '_')}@{self.commit}.json"
            if not listing.exists():
                listing.parent.mkdir(parents=True, exist_ok=True)
                url = f"https://api.github.com/repos/{self.repo}/git/trees/{self.commit}?recursive=1"
                listing.write_bytes(fetch(url))
            data = json.loads(listing.read_text())
            if data.get("truncated"):
                fail(f"{self.repo}: the file list is truncated")
            self._tree = {item["path"] for item in data["tree"] if item["type"] == "blob"}
        return self._tree

    def exists(self, path):
        return path in self.tree()

    def get(self, path):
        if not self.exists(path):
            fail(f"{self.repo}@{self.commit[:7]} has no {path}")
        local = self.root / path
        if not local.exists():
            local.parent.mkdir(parents=True, exist_ok=True)
            data = fetch(f"https://raw.githubusercontent.com/{self.repo}/{self.commit}/{urllib.parse.quote(path)}")
            partial = local.with_name(local.name + ".part")
            partial.write_bytes(data)
            partial.rename(local)
        return local

    def read(self, path):
        return self.get(path).read_text(errors="replace")

    def provenance(self):
        return {"url": f"https://github.com/{self.repo}", "commit": self.commit}


class Archive:
    """A small release archive (FreePats), downloaded once, checked by its SHA-256, unpacked by
    the system's tar (libarchive reads .7z and .tar.xz)."""

    def __init__(self, url, sha256, size_limit_mb=40):
        self.url, self.sha256, self.limit = url, sha256, size_limit_mb * 1024 * 1024
        self.root = CACHE / "archives" / sha256[:16]
        self._tree = None

    def unpack(self):
        if (self.root / ".done").exists():
            return
        download = CACHE / "downloads" / Path(urllib.parse.urlparse(self.url).path).name
        if not download.exists():
            download.parent.mkdir(parents=True, exist_ok=True)
            data = fetch(self.url, self.limit)
            download.write_bytes(data)
        digest = hashlib.sha256(download.read_bytes()).hexdigest()
        if digest != self.sha256:
            fail(f"{self.url} has SHA-256 {digest}, not the pinned {self.sha256}")
        if self.root.exists():
            shutil.rmtree(self.root)
        self.root.mkdir(parents=True)
        run(["tar", "-xf", str(download), "-C", str(self.root)])
        (self.root / ".done").write_text("")

    def tree(self):
        if self._tree is None:
            self.unpack()
            self._tree = {str(p.relative_to(self.root)) for p in self.root.rglob("*") if p.is_file() and p.name != ".done"}
        return self._tree

    def exists(self, path):
        return path in self.tree()

    def get(self, path):
        if not self.exists(path):
            fail(f"{self.url} has no {path}")
        return self.root / path

    def read(self, path):
        return self.get(path).read_text(errors="replace")

    def provenance(self):
        return {"url": self.url, "sha256": self.sha256}


def fetch(url, limit=200 * 1024 * 1024):
    request = urllib.request.Request(url, headers={"User-Agent": "stackapella-instrument-build"})
    with urllib.request.urlopen(request, timeout=120) as answer:
        data = answer.read(limit + 1)
    if len(data) > limit:
        fail(f"{url} is bigger than {limit // (1024 * 1024)} MB: not fetched")
    return data


def source_of(spec):
    if spec["type"] == "github":
        return GitHub(spec["repo"], spec["commit"])
    if spec["type"] == "archive":
        return Archive(spec["url"], spec["sha256"])
    fail(f"unknown source type {spec['type']}")


# Choosing the zones ----------------------------------------------------------------------------


def name_to_note(name, middle_c_octave):
    match = re.fullmatch(r"([A-Ga-g])([#b]?)(-?\d+)", name)
    if not match:
        fail(f"not a note name: {name}")
    letter, accidental, octave = match.groups()
    step = {"c": 0, "d": 2, "e": 4, "f": 5, "g": 7, "a": 9, "b": 11}[letter.lower()]
    step += 1 if accidental == "#" else -1 if accidental == "b" else 0
    return 60 + (int(octave) - middle_c_octave) * 12 + step


def cc_values(control):
    values = {}
    for key, value in control.items():
        match = re.fullmatch(r"set_cc(\d+)", key)
        if match:
            values[int(match.group(1))] = float(value)
    return values


def plays_by_default(region, ccs):
    """A region sounds at the program's default controllers (its locc/hicc conditions hold)."""
    for key, value in region["ccConditions"].items():
        number = int(re.sub(r"\D", "", key))
        current = ccs.get(number, 0)
        if key.startswith("locc") and current < float(value):
            return False
        if key.startswith("hicc") and current > float(value):
            return False
    return True


def curve_at(curves, index, value):
    """A controller's value (0 to 127) through curve `index`: 0 is SFZ's linear 0 to 1, others the
    program's <curve>s, straight lines between their points."""
    if index == 0:
        return value / 127
    points = curves.get(index)
    if not points:
        fail(f"curve {index} isn't one the build reads (0, or a <curve> in the program)")
    if value <= points[0][0]:
        return points[0][1]
    for (x0, y0), (x1, y1) in zip(points, points[1:]):
        if value <= x1:
            return y0 + (y1 - y0) * (value - x0) / (x1 - x0)
    return points[-1][1]


# Level modulators, applied at the program's controller settings: amplitude (%) multiplies,
# volume (dB) adds. SFZ 1's amplitude_ccN and gain_ccN are the same as SFZ 2's _onccN.
LEVEL_MODULATOR = re.compile(r"(amplitude|volume|gain)_(?:on)?cc(\d+)")
# Opcodes about controllers that the build reads elsewhere, or that change nothing it renders.
CC_READ = re.compile(r"(lo|hi)cc\d+|set_cc\d+|label_cc\d+|(amplitude|volume|gain)_(on)?cc\d+|(amplitude|volume|gain)_curvecc\d+|amp_velcurve_\d+")


def cc_levels(raw, ccs, curves, unread):
    """A region's amplitude factor and its dB, from its level modulators at these controllers.
    Other controller opcodes (envelopes, tuning, pan) are noted in `unread`: the build premixes
    at the defaults and doesn't apply them."""
    factor, decibels = 1.0, 0.0
    for key, value in raw.items():
        match = LEVEL_MODULATOR.fullmatch(key)
        if match:
            kind, number = match.group(1), int(match.group(2))
            curve = int(raw.get(f"{'amplitude' if kind == 'amplitude' else 'volume'}_curvecc{number}", 0))
            level = curve_at(curves, curve, ccs.get(number, 0))
            if kind == "amplitude":
                factor *= float(value) / 100 * level
            else:
                decibels += float(value) * level
        elif re.search(r"cc\d+$", key) and not CC_READ.fullmatch(key):
            unread.add(re.sub(r"\d+$", "N", key))
    return factor, decibels


def cc_key(settings):
    """A piece's controller settings, as the label its regions carry."""
    return json.dumps({str(k): float(v) for k, v in sorted((settings or {}).items(), key=lambda kv: int(kv[0]))}, sort_keys=True)


def sfz_regions(source, spec, variants=({},)):
    """The attack regions of an SFZ program that sound at its default settings, and at each of
    `variants` (controller settings over the defaults, as a drum piece asks: an open hi-hat is
    the pedal's controller up). Each region carries its variant's label, `ccs`."""
    program = spec["program"]
    control, raw = sfz.regions(program, source.read, exists=source.exists)
    defaults = cc_values(control)
    found = []
    unread = set()
    for variant in variants:
        ccs = {**defaults, **{int(k): float(v) for k, v in variant.items()}}
        for region in raw:
            # No sample, or one of SFZ's own generators (*silence, *sine): nothing to render.
            if "sample" not in region or region["sample"].strip().startswith("*"):
                continue
            fields = sfz.region_fields(region, control, program)
            if fields["trigger"] != "attack" or not plays_by_default(fields, ccs):
                continue
            if spec.get("samples") and not re.search(spec["samples"], fields["sample"]):
                continue
            factor, decibels = cc_levels(region, ccs, control["curves"], unread)
            # A part its controllers turn all the way down (a muted mic, the kick's snare buzz)
            # isn't in the mix.
            if factor <= 0:
                continue
            if not source.exists(fields["sample"]):
                fail(f"{program} names {fields['sample']}, which isn't in the source")
            fields["amplitude"] *= factor
            fields["volume"] += decibels
            fields["ccs"] = cc_key(variant)
            found.append(fields)
    if unread:
        print(f"  {program}: not applied (the build renders at the defaults): {', '.join(sorted(unread))}", file=sys.stderr)
    if not found:
        fail(f"{program}: no regions")
    return found


def file_regions(source, spec):
    """Regions from file names, for sets without an SFZ (VCSL, VSCO 2 CE): `pattern` names the
    note and the velocity layer."""
    pattern = re.compile(spec["pattern"])
    middle_c = int(spec.get("middleCOctave", 4))
    found = []
    for path in sorted(source.tree()):
        if not path.startswith(spec["dir"].rstrip("/") + "/"):
            continue
        match = pattern.fullmatch(path[len(spec["dir"].rstrip("/")) + 1:])
        if not match:
            continue
        groups = match.groupdict()
        note = name_to_note(groups["note"], middle_c) if "note" in groups else None
        found.append({
            "sample": path, "root": note, "lo": note, "hi": note, "velLo": int(groups.get("vel") or 1), "velHi": int(groups.get("vel") or 1),
            "tune": 0.0, "volume": 0.0, "amplitude": 100.0, "seq": int(groups.get("rr") or 1), "piece": groups.get("piece"), "ccs": cc_key({}),
        })
    if not found:
        fail(f"{spec['dir']}: no file matches {spec['pattern']}")
    return found


def pick_roots(roots, step, low=None, high=None):
    """Every `step`th semitone or so, from what was sampled."""
    roots = sorted(r for r in set(roots) if (low is None or r >= low) and (high is None or r <= high))
    kept = [roots[0]]
    for target in range(roots[0] + step, roots[-1] + 1, step):
        nearest = min(roots, key=lambda r: (abs(r - target), r))
        if nearest > kept[-1]:
            kept.append(nearest)
    if roots[-1] - kept[-1] >= step // 2 + 1:
        kept.append(roots[-1])
    return kept


def spread(values, count):
    """`count` of the values, spread from the lowest to the highest."""
    values = sorted(values)
    if len(values) <= count:
        return values
    if count == 1:
        return [values[-1]]
    return sorted({values[round(i * (len(values) - 1) / (count - 1))] for i in range(count)})


def velocity_bands(count):
    """1 to 127 split into `count` equal bands."""
    edges = [1 + round(i * 127 / count) for i in range(count)] + [128]
    return [(edges[i], edges[i + 1] - 1) for i in range(count)]


def layer_key(region, by):
    return region["velLo"] if by == "lovel" else region["velHi"]


def pitched_zones(regions, spec):
    """Kept roots and layers; each zone covers the keys halfway to its neighbours."""
    roots = pick_roots([r["root"] for r in regions], int(spec["step"]), spec.get("low"), spec.get("high"))
    zones = []
    for i, root in enumerate(roots):
        lo = 0 if i == 0 else (roots[i - 1] + root) // 2 + 1
        hi = 127 if i == len(roots) - 1 else (root + roots[i + 1]) // 2
        # One take per note: the first round robin, and the first of any random alternatives.
        at_root = [r for r in regions if r["root"] == root and r["seq"] == 1 and r.get("random", 0) == 0]
        layers = spread(sorted({layer_key(r, spec.get("layerBy", "lovel")) for r in at_root}), int(spec["layers"]))
        bands = velocity_bands(len(layers))
        for (vel_lo, vel_hi), layer in zip(bands, layers):
            parts = [r for r in at_root if layer_key(r, spec.get("layerBy", "lovel")) == layer]
            zones.append({"root": root, "lo": lo, "hi": hi, "velLo": vel_lo, "velHi": vel_hi, "tune": parts[0]["tune"], "parts": parts})
    return zones


def drum_zones(regions, spec):
    """Each piece: its layers (spread over what was sampled) and round robins, all mics summed."""
    zones = []
    for piece, wanted in spec["pieces"].items():
        # A piece is a key, a sample pattern, or either with the controllers it's played at
        # ({"key": 46, "cc": {"4": 127}}: the open hi-hat).
        settings = cc_key(wanted.get("cc") if isinstance(wanted, dict) else None)
        if isinstance(wanted, dict):
            wanted = wanted["key"] if "key" in wanted else wanted["sample"]
        at_settings = [r for r in regions if r.get("ccs", cc_key({})) == settings]
        if isinstance(wanted, int):
            on_key = [r for r in at_settings if r["lo"] is not None and r["lo"] <= wanted <= r["hi"]]
            gm = wanted
        else:
            on_key = [r for r in at_settings if r.get("piece") == wanted or re.search(wanted, r["sample"])]
            gm = None
        if not on_key:
            fail(f"piece {piece}: nothing sampled for {wanted}")
        # A layer: a velocity the program plays, chosen as the middle of each band it has.
        bands = sorted({(r["velLo"], r["velHi"]) for r in on_key if r["velHi"] - r["velLo"] < 126} or {(1, 127)})
        chosen = spread([b[0] for b in bands], int(spec.get("layers", 3)))
        out_bands = velocity_bands(len(chosen))
        for (vel_lo, vel_hi), band_lo in zip(out_bands, chosen):
            band = next(b for b in bands if b[0] == band_lo)
            velocity = (band[0] + band[1]) // 2
            sounding = [r for r in on_key if r["velLo"] <= velocity <= r["velHi"]]
            # Each mic (a folder of samples) has its own round robins: the rr-th hit takes each
            # mic's rr-th take, so the mics of one hit are summed together.
            mics = {}
            for r in sounding:
                mics.setdefault(r["sample"].rsplit("/", 1)[0], []).append(r)
            # A take is a round robin (seq) or a random alternative (lorand).
            take = lambda r: (r["seq"], r.get("random", 0))  # noqa: E731
            takes = max(len({take(r) for r in regs}) for regs in mics.values())
            for rr in range(1, min(takes, int(spec.get("roundRobin", 2))) + 1):
                parts = []
                for regs in mics.values():
                    options = sorted({take(r) for r in regs})
                    chosen = options[(rr - 1) % len(options)]
                    parts.extend(r for r in regs if take(r) == chosen)
                zones.append({"piece": piece, "key": gm, "velLo": vel_lo, "velHi": vel_hi, "rr": rr, "parts": parts})
    return zones


# Rendering -------------------------------------------------------------------------------------


def decode(path, channels, start=0):
    """A file as 32-bit float samples, interleaved, at RATE. `start` (SFZ's offset) counts the
    file's own samples, so it's cut before resampling."""
    trim = ["-af", f"atrim=start_sample={int(start)}"] if start else []
    raw = run(["ffmpeg", "-v", "error", "-i", str(path), *trim, "-f", "f32le", "-ac", str(channels), "-ar", str(RATE), "-"])
    return list(struct.unpack(f"<{len(raw) // 4}f", raw))


def mix(parts, source, channels):
    """The zone's sources summed at their levels (dB and amplitude %)."""
    total = []
    for part in parts:
        gain = 10 ** (part["volume"] / 20) * part["amplitude"] / 100
        samples = decode(source.get(part["sample"]), channels, part.get("offset", 0))
        if len(samples) > len(total):
            total.extend([0.0] * (len(samples) - len(total)))
        for i, value in enumerate(samples):
            total[i] += value * gain
    return total


def onset_frame(samples, channels, share=ONSET_SHARE):
    peak = max((abs(v) for v in samples), default=0.0)
    if peak == 0:
        return 0
    limit = peak * share
    for i, value in enumerate(samples):
        if abs(value) >= limit:
            return i // channels
    return 0


def shape(samples, channels, seconds, fade):
    """Starts PREROLL before the onset, lasts at most `seconds`, fades out over its last `fade`."""
    start = max(0, onset_frame(samples, channels) - round(PREROLL * RATE))
    frames = min(len(samples) // channels - start, round(seconds * RATE))
    out = samples[start * channels:(start + frames) * channels]
    fade_frames = min(frames, round(fade * RATE))
    for f in range(frames - fade_frames, frames):
        level = (frames - f) / fade_frames
        for c in range(channels):
            out[f * channels + c] *= level
    # Silence at the end is cut (a quiet tail fades into nothing).
    while len(out) > channels * RATE // 10 and max(abs(v) for v in out[-channels * 256:]) < 1e-4:
        out = out[:-channels * 256]
    return out


def write_wav(path, samples, channels, gain):
    with open(path, "wb") as handle:
        data = array("f", (max(-1.0, min(1.0, v * gain)) for v in samples))
        if sys.byteorder != "little":
            data.byteswap()
        data = data.tobytes()
        handle.write(b"RIFF" + struct.pack("<I", 36 + len(data)) + b"WAVEfmt " + struct.pack("<IHHIIHH", 16, 3, channels, RATE, RATE * channels * 4, channels * 4, 32))
        handle.write(b"data" + struct.pack("<I", len(data)) + data)


# An MP3 decoder's own delay, after the encoder's (the LAME tag's figure) (samples).
DECODER_DELAY = 529


def lame_delay(mp3):
    """The encoder delay the first frame's Xing or Info header records, in its LAME extension (the
    encoder's name, then 12 bytes of fields, then the delay's 12 bits). ffmpeg writes "Lavc" as
    the name, LAME "LAME"; the place is the same."""
    head = Path(mp3).read_bytes()[:16384]
    for tag in (b"Info", b"Xing"):
        at = head.find(tag)
        if at < 0:
            continue
        flags = int.from_bytes(head[at + 4:at + 8], "big")
        at += 8 + (4 if flags & 1 else 0) + (4 if flags & 2 else 0) + (100 if flags & 4 else 0) + (4 if flags & 8 else 0)
        if at + 24 > len(head):
            return None
        return (head[at + 21] << 4) | (head[at + 22] >> 4)
    return None


def encoder_delay(mp3, wav, channels):
    """How many samples late the attack comes in a decode that keeps the encoder's priming: the
    LAME tag's delay plus the decoder's, checked against the onset measured in such a decode
    (the codec smears an attack by a few dozen samples, so the tag's exact figure is kept)."""
    raw = run(["ffmpeg", "-v", "error", "-flags2", "+skip_manual", "-i", str(mp3), "-f", "f32le", "-ac", str(channels), "-ar", str(RATE), "-"])
    kept = struct.unpack(f"<{len(raw) // 4}f", raw)
    measured = onset_frame(kept, channels) - onset_frame(decode(wav, channels), channels)
    tag = lame_delay(mp3)
    delay = tag + DECODER_DELAY if tag is not None else measured
    # A soft attack (an FM piano's, a pad's) crosses the onset threshold a little later.
    if abs(delay - measured) > 256:
        fail(f"{mp3}: the LAME tag says a delay of {delay}, but the attack comes {measured} samples late")
    return delay, len(kept) // channels


def pitch_cents(samples, channels, root):
    """How far the sample's pitch is from its root, by YIN on a slice after the attack, or None
    (above C6 the period is too short to measure this way)."""
    if root > 84:
        return None
    mono = [sum(samples[i:i + channels]) / channels for i in range(0, len(samples), channels)][::2]
    rate = RATE / 2
    expected = 440 * 2 ** ((root - 69) / 12)
    lo_lag, hi_lag = max(2, int(rate / (expected * 2.2))), min(2000, int(rate / (expected / 2.2)))
    # At least two and a half periods compared, or a low note's pitch can't be told.
    size = max(1024, int(2.5 * rate / expected))
    start = min(len(mono) - size - hi_lag - 1, round(0.15 * rate))
    if start < 0:
        return None
    window = mono[start:start + size + hi_lag + 1]
    diffs = []
    for lag in range(1, hi_lag + 1):
        diffs.append(sum((window[j] - window[j + lag]) ** 2 for j in range(size)))
    running, normal = 0.0, []
    for lag, d in enumerate(diffs, start=1):
        running += d
        normal.append(d * lag / running if running else 1.0)
    best = None
    for lag in range(lo_lag, hi_lag):
        if normal[lag - 1] < 0.2 and normal[lag - 1] <= normal[lag]:
            best = lag
            break
    if best is None:
        best = min(range(lo_lag, hi_lag), key=lambda l: normal[l - 1])
        if normal[best - 1] > 0.5:
            return None
    # Between whole lags: the parabola through the dip and its neighbours.
    if 1 < best < hi_lag - 1:
        left, mid, right = normal[best - 2], normal[best - 1], normal[best]
        bend = left - 2 * mid + right
        best = best + (0.5 * (left - right) / bend if bend else 0)
    found = rate / best
    return 1200 * math.log2(found / expected)


def build(name, spec, out):
    source = source_of(spec["source"])
    kind = spec["kind"]
    channels = int(spec.get("channels", 1))
    regions = []
    for part in spec["from"]:
        part_source = source_of(part["source"]) if "source" in part else source
        variants = [{}] + [dict(w["cc"]) for w in spec.get("pieces", {}).values() if isinstance(w, dict) and w.get("cc")]
        found = sfz_regions(part_source, part, variants) if "program" in part else file_regions(part_source, part)
        for region in found:
            region["_source"] = part_source
            if "piece" in part:
                region["piece"] = part["piece"]
        regions.extend(found)
    for region in regions:
        for key in ("root", "lo", "hi"):
            if region.get(key) is not None:
                region[key] += int(spec.get("transpose", 0))
    zones = pitched_zones(regions, spec) if kind == "pitched" else drum_zones(regions, spec)

    work = Path(tempfile.mkdtemp(prefix=f"stackapella-{name}-"))
    try:
        rendered = []
        peak = 0.0
        for index, zone in enumerate(zones):
            samples = []
            for part in zone["parts"]:
                one = mix([part], part["_source"], channels)
                if len(one) > len(samples):
                    samples.extend([0.0] * (len(one) - len(samples)))
                for i, v in enumerate(one):
                    samples[i] += v
            seconds = spec["seconds"] if not isinstance(spec["seconds"], dict) else spec["seconds"].get(zone.get("piece"), spec["seconds"]["*"])
            shaped = shape(samples, channels, float(seconds), float(spec.get("fade", 0.3)))
            peak = max(peak, max((abs(v) for v in shaped), default=0.0))
            rendered.append(shaped)
            print(f"  {name}: rendered {index + 1}/{len(zones)}", file=sys.stderr, end="\r")
        print(file=sys.stderr)
        if peak == 0:
            fail(f"{name}: everything rendered silent")
        # One gain for the whole instrument: its loudest sample at -1 dBFS, the layers' balance kept.
        gain = 10 ** (-1 / 20) / peak

        if out.exists():
            shutil.rmtree(out)
        (out / "s").mkdir(parents=True)
        manifest_zones = []
        files = []
        bitrate = "160k" if channels == 2 else "128k"
        problems = []
        pitch_log = []
        for index, (zone, samples) in enumerate(zip(zones, rendered)):
            label = f"{zone['root']:03d}-{zone['velHi']:03d}" if kind == "pitched" else f"{zone['piece']}-{zone['velHi']:03d}-{zone['rr']}"
            wav, mp3 = work / f"{label}.wav", out / "s" / f"{label}.mp3"
            write_wav(wav, samples, channels, gain)
            run(["ffmpeg", "-v", "error", "-y", "-i", str(wav), "-c:a", "libmp3lame", "-b:a", bitrate, "-ac", str(channels), str(mp3)])
            frames = len(samples) // channels
            delay, raw_frames = encoder_delay(mp3, wav, channels)
            onset = onset_frame(decode(wav, channels), channels) / RATE
            entry = {"file": f"s/{label}.mp3", "frames": frames, "encoderDelay": delay, "onset": round(onset, 5), "velLo": zone["velLo"], "velHi": zone["velHi"]}
            if kind == "pitched" and spec.get("pitchCheck", True):
                cents = pitch_cents(samples, channels, zone["root"])
                # The program's own tune corrects the sample: what counts is where it ends up.
                cents = cents + zone["tune"] if cents is not None else None
                pitch_log.append(cents)
                tolerance = float(spec.get("pitchTolerance", 60))
                if cents is not None and abs(cents) > tolerance and abs(abs(cents) - 1200) > tolerance:
                    problems.append(f"{label}: sounds {cents:+.0f} cents from {zone['root']}")
            if kind == "pitched":
                entry.update({"root": zone["root"], "lo": zone["lo"], "hi": zone["hi"], "tune": round(zone["tune"], 2)})
            else:
                entry.update({"piece": zone["piece"], "key": zone["key"], "rr": zone["rr"]})
            if not (0 <= delay <= 4096) or raw_frames < frames:
                problems.append(f"{label}: encoder delay {delay} looks wrong")
            manifest_zones.append(entry)
        # A whole set an octave off (a file-naming convention misread) is wrong, even though one
        # sample measured an octave off is YIN's usual slip.
        measured = sorted(c for c in pitch_log if c is not None)
        if measured and abs(measured[len(measured) // 2]) > 600:
            problems.append(f"the set sounds {measured[len(measured) // 2]:+.0f} cents from its roots (an octave off?)")
        if problems and not spec.get("allowPitchDrift"):
            fail(f"{name}:\n  " + "\n  ".join(problems))

        level = set_gain(zones, rendered, channels, gain, kind)

        licence_text = licence_file(name, spec)
        (out / "LICENSES.txt").write_text(licence_text)
        for path in sorted(p for p in out.rglob("*") if p.is_file()):
            relative = str(path.relative_to(out))
            files.append({"path": relative, "bytes": path.stat().st_size, "sha256": hashlib.sha256(path.read_bytes()).hexdigest()})
        manifest = {
            "format": FORMAT,
            "name": name,
            "version": str(spec["version"]),
            "title": spec["title"],
            "kind": kind,
            "sampleRate": RATE,
            "channels": channels,
            "release": float(spec.get("release", 0.25)),
            "gain": level,
            "zones": manifest_zones,
            "licence": spec["licence"],
            "credit": spec["credit"],
            "sources": [source_of(s).provenance() for s in unique_sources(spec)],
            "files": files,
        }
        (out / "manifest.json").write_text(json.dumps(manifest, indent=1) + "\n")
        size = sum(f["bytes"] for f in files)
        print(f"{name} {spec['version']}: {len(manifest_zones)} zones, {size / 1e6:.2f} MB, in {out}")
        return manifest
    finally:
        shutil.rmtree(work, ignore_errors=True)


# The loudness every set is levelled to: dBFS RMS over a typical note's first second, or over a
# kit's typical hit's first 100 ms (a hit is short). Low enough that most sets need no boost.
TARGET_RMS_DB = {"pitched": (-26.0, 1.0), "drums": (-24.0, 0.1)}


def set_gain(zones, rendered, channels, gain, kind):
    """The linear gain that brings a typical note to its target: the zone nearest middle C (or the
    kit's snare, brush tap or clap, else its first piece), at its loudest layer. Never above 1: the
    set's loudest sample is already at -1 dBFS, so more would clip it."""
    if kind == "pitched":
        root = min({z["root"] for z in zones}, key=lambda r: abs(r - 60))
        candidates = [i for i, z in enumerate(zones) if z["root"] == root]
    else:
        pieces = [z["piece"] for z in zones]
        piece = next((p for p in ("snare", "brush-tap", "clap") if p in pieces), pieces[0])
        candidates = [i for i, z in enumerate(zones) if z["piece"] == piece and z.get("rr") == 1]
    index = max(candidates, key=lambda i: zones[i]["velHi"])
    target, seconds = TARGET_RMS_DB[kind]
    window = rendered[index][: round(RATE * seconds) * channels]
    rms = math.sqrt(sum((v * gain) ** 2 for v in window) / max(1, len(window)))
    if rms == 0:
        return 1.0
    return round(min(1.0, 10 ** ((target - 20 * math.log10(rms)) / 20)), 3)


def unique_sources(spec):
    seen, out = set(), []
    for s in [spec["source"], *[p["source"] for p in spec["from"] if "source" in p]]:
        key = json.dumps(s, sort_keys=True)
        if key not in seen:
            seen.add(key)
            out.append(s)
    return out


def licence_file(name, spec):
    lines = [f"{spec['title']} ({name} {spec['version']}), built for Stackapella's real instruments.", ""]
    lines.append(f"Licence: {spec['licence']}. {spec['credit']}")
    lines.append("Samples trimmed, mixed and re-encoded from:")
    for s in unique_sources(spec):
        p = source_of(s).provenance()
        lines.append("  " + p["url"] + (f" at commit {p['commit']}" if "commit" in p else f" (SHA-256 {p['sha256']})"))
    return "\n".join(lines) + "\n"


def main():
    parser = argparse.ArgumentParser(description="Build Stackapella's real instruments.")
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("list")
    one = sub.add_parser("build")
    one.add_argument("name")
    one.add_argument("--out", help="where the built folders go (default: the cache's build/)")
    args = parser.parse_args()
    sets = json.loads((HERE / "sets.json").read_text())["instruments"]
    if args.command == "list":
        for name, spec in sets.items():
            print(f"{name} {spec['version']}: {spec['title']} ({spec['licence']})")
        return
    if shutil.which("ffmpeg") is None:
        fail("needs ffmpeg")
    names = list(sets) if args.name == "all" else [args.name]
    base = Path(args.out) if args.out else CACHE / "build"
    if REPO in base.resolve().parents or base.resolve() == REPO:
        fail("the built audio stays out of the repo: choose --out outside it")
    for name in names:
        if name not in sets:
            fail(f"no instrument {name} in sets.json")
        spec = sets[name]
        built = build(name, spec, base / name / str(spec["version"]))
        print(f"  upload: php artisan app:instruments:upload {name} {spec['version']} {base / name / str(spec['version'])}")
        del built


if __name__ == "__main__":
    main()
