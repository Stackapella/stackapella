"""The singability check (docs/arrangements-voicings-plan.md, "Singable by a beginner"): each of the
six rules (as the courses lead checked them), over every part of every song (so every voicing,
which is a set of parts). A beta bass
(Zvi, 2026-11-01): "people trying to learn to sing wont be able to breathe that quickly".

    sh server/tool/content.sh link
    python3 -I web/tool/harmony-review/singable.py [-v] [--choir] [song.json ...]   # the linked songs when none given

--choir adds a light choir check (no browser), for rewriting parts: parallel fifths and octaves in
similar motion between any two voices (not across a rest), crossings (not in barbershop, whose
baritone goes over the lead), and a doubled leading tone. The full judge is run.py --template.

Prints a count per rule for each song (-v: every problem with its part and beat); exits 1 when any.
"""
import json
import math
import sys
from collections import Counter
from pathlib import Path

WEB = Path(__file__).resolve().parents[2]
LONG_LINE = 8.0  # rule 1: a line this long or longer earns a full breath
FULL = (1.0, 0.6)  # rule 1: a full breath, in beats and seconds
QUICK = (0.5, 0.5)  # rule 1: after a shorter line
LONGEST = 10.0  # rule 2: seconds on one breath
FLOOR = 0.15  # rule 3: seconds, the shortest syllable
SHORT = 0.25  # rule 3: a syllable under this is the short half of a pair or a pickup, never two in a row
RATE = 3.0  # rule 3: syllables a second in a line
EDGE_SECONDS = 4.0  # rule 4: seconds in a row near an edge
FINAL = 4.0  # rule 6: seconds
# js/theory.js VOICE_RANGES, the app's beginner ranges; a barbershop tenor sings as an alto, its lead as a tenor.
RANGES = {"soprano": (62, 76), "alto": (55, 72), "tenor": (48, 65), "baritone": (45, 62), "bass": (41, 58)}
HUMS = {"oo", "ooh", "ah", "aah", "mm", "mmm", "hm", "hmm", "loo", "doo", "dm", "dum"}


def breath_beats(tempo, line_seconds=LONG_LINE):
    """The breath before a new line, in beats (up to a quarter beat): full after a long line, quick after a short one."""
    beats, seconds = FULL if line_seconds >= LONG_LINE - 1e-6 else QUICK
    return max(beats, math.ceil(seconds * tempo / 60 * 4 - 1e-9) / 4)


def role(song, part):
    barbershop = any(p["key"] in ("lead", "baritone") for p in song["parts"])
    if barbershop:
        return {"tenor": "alto", "lead": "tenor", "baritone": "baritone", "bass": "bass"}.get(part["key"])
    return part["key"] if part["key"] in RANGES else None


def lines_of(song):
    return [tuple(p) for s in song.get("sections", []) for p in s.get("phrases", [])]


MINOR_THIRD = {"min", "min7", "min6", "dim", "dim7", "hdim7"}


def sounding(notes, beat):
    for n in notes:
        if n["beat"] <= beat + 1e-6 < n["beat"] + n["length"]:
            return n["pitch"]
    return None


def third_to_third(song, tune_notes, part, x, y):
    """Rule 5, as the courses lead refined it: a third or a fourth from one chord's third to the next
    chord's third, across the chord change, is an easy move for a close-harmony part, unless it lands in
    unison with the tune or makes a parallel fifth or octave with another part."""
    d = abs(y["pitch"] - x["pitch"])
    if not 3 <= d <= 5:
        return False
    chords = song.get("chords") or []
    at = lambda b: next((c for c in reversed(chords) if c["beat"] <= b + 1e-6), None)
    a, b = at(x["beat"]), at(y["beat"])
    if not a or not b or a is b:
        return False
    third = lambda c: (c["root"] + (3 if c["quality"] in MINOR_THIRD else 4)) % 12
    if x["pitch"] % 12 != third(a) or y["pitch"] % 12 != third(b):
        return False
    if sounding(tune_notes, y["beat"]) == y["pitch"]:
        return False
    for other in song["parts"]:
        if other is part:
            continue
        ns = other["guide"]["notes"]
        p0, p1 = sounding(ns, x["beat"]), sounding(ns, y["beat"])
        if p0 is None or p1 is None or p0 == p1:
            continue
        was, now = (x["pitch"] - p0) % 12, (y["pitch"] - p1) % 12
        if was == now and now in (0, 7) and (y["pitch"] > x["pitch"]) == (p1 > p0):
            return False
    return True


def check(song):
    """Every problem: (rule, part, beat, what)."""
    seconds = 60 / song["tempo"]
    need = breath_beats(song["tempo"], 0)  # the least that counts as a breath
    out = []
    tune = next((p for p in song["parts"] if p.get("melody")), song["parts"][0])
    tune_notes = sorted(tune["guide"]["notes"], key=lambda n: n["beat"])
    tune_shortest = min((n["length"] for n in tune_notes), default=0)
    lines = lines_of(song)
    in_line = lambda notes, a, b: [n for n in notes if a - 1e-6 <= n["beat"] < b - 1e-6]
    for part in song["parts"]:
        key = part["key"]
        notes = sorted(part["guide"]["notes"], key=lambda n: n["beat"])
        if not notes:
            continue
        # Rules 1 and 2: the rest before each new line, and the longest run on one breath.
        start = notes[0]["beat"]
        for prev, note in zip(notes, notes[1:]):
            gap = note["beat"] - (prev["beat"] + prev["length"])
            wanted = breath_beats(song["tempo"], (prev["beat"] + prev["length"] - start) * seconds)
            if note.get("br") and gap < wanted - 1e-6:
                out.append((1, key, note["beat"], f"{gap:g} beats' rest before its new line, under {wanted:g}"))
            if gap >= need - 1e-6:
                if (prev["beat"] + prev["length"] - start) * seconds > LONGEST + 1e-6:
                    out.append((2, key, start, f"{(prev['beat'] + prev['length'] - start) * seconds:.1f} s on one breath"))
                start = note["beat"]
        if (notes[-1]["beat"] + notes[-1]["length"] - start) * seconds > LONGEST + 1e-6:
            out.append((2, key, start, f"{(notes[-1]['beat'] + notes[-1]['length'] - start) * seconds:.1f} s on one breath"))
        # Rule 3: syllables quick enough to sing (the tune's and each part's own words).
        sung_words = [n for n in notes if n.get("lyric")]
        for i, n in enumerate(sung_words):
            if n["length"] * seconds < FLOOR - 1e-6:
                out.append((3, key, n["beat"], f"a {n['length'] * seconds:.2f} s syllable"))
            elif i and n["length"] * seconds < SHORT - 1e-6 and sung_words[i - 1]["length"] * seconds < SHORT - 1e-6:
                out.append((3, key, n["beat"], "two quick syllables in a row"))
        for a, b in lines:
            ns = in_line(notes, a, b)
            sung = sum(n["length"] for n in ns) * seconds
            syllables = sum(1 for n in ns if n.get("lyric"))
            if sung > 0 and syllables / sung > RATE + 1e-6:
                out.append((3, key, a, f"{syllables / sung:.1f} syllables a second"))
        # Rule 4: the comfortable middle.
        r = role(song, part)
        if r:
            low, high = RANGES[r]
            for n in notes:
                if not low <= n["pitch"] <= high:
                    out.append((4, key, n["beat"], f"{n['pitch']} outside {low} to {high} ({r})"))
            total = sum(n["length"] for n in notes)
            middle = sum(n["pitch"] * n["length"] for n in notes) / total
            span = high - low
            if not low + span / 4 <= middle <= high - span / 4:
                out.append((4, key, 0, f"its middle {middle:.1f} outside {low + span / 4:g} to {high - span / 4:g}"))
            for zone, test in (("top", lambda p: p >= high - 2), ("bottom", lambda p: p <= low + 1)):
                run, since = 0.0, None
                for prev, n in zip([None, *notes], notes):
                    joined = prev is not None and abs(prev["beat"] + prev["length"] - n["beat"]) < need - 1e-6
                    if test(n["pitch"]):
                        run = (run if joined and since is not None else 0) + n["length"] * seconds
                        since = n["beat"] if since is None or not joined else since
                        if run > EDGE_SECONDS + 1e-6:
                            out.append((4, key, since, f"{run:.1f} s at the {zone} of the range"))
                            run, since = -1e9, since
                    else:
                        run, since = 0.0, None
        # Rule 5: harmony parts easier than the tune.
        if part is not tune:
            word = None
            for prev, n in zip([None, *notes], notes):
                if n.get("lyric"):
                    word = n["lyric"].strip(".,;:!?'\"-").lower()
                elif prev is not None and abs(prev["beat"] + prev["length"] - n["beat"]) < 1e-6 and word not in HUMS:
                    out.append((5, key, n["beat"], "a melisma"))
                if n["length"] < tune_shortest - 1e-6:
                    out.append((5, key, n["beat"], f"a note of {n['length']:g} beats, shorter than the tune's shortest"))
            starts = {round(a, 6) for a, _ in lines}
            ends = {round(b, 6) for _, b in lines}
            line_start = lambda n: n.get("br") or round(n["beat"], 6) in starts
            for a, b in lines:
                ns = in_line(notes, a, b)
                leaps = sum(1 for x, y in zip(ns, ns[1:]) if abs(y["pitch"] - x["pitch"]) > 5)
                if leaps > 1:
                    out.append((5, key, a, f"{leaps} leaps over a fourth in the line"))
            moves = [(x, y, y["pitch"] - x["pitch"]) for x, y in zip(notes, notes[1:])]
            if r == "bass" or key == "bass":
                for i, (x, y, d) in enumerate(moves):
                    if abs(d) > 12:
                        out.append((5, key, y["beat"], f"a leap of {abs(d)}"))
                    if abs(d) in (6, 10, 11):
                        out.append((5, key, y["beat"], f"a leap of {abs(d)} (a tritone or seventh)"))
                    if abs(d) > 7 and not (line_start(y) or any(abs(y["beat"] + y["length"] - e) < 1 for e in ends) or any(abs(x["beat"] + x["length"] - e) < 1 for e in ends)):
                        out.append((5, key, y["beat"], f"a leap of {abs(d)} away from a line's start or cadence"))
                    if i and abs(d) > 2 and abs(moves[i - 1][2]) > 2 and (d > 0) == (moves[i - 1][2] > 0) and abs(d + moves[i - 1][2]) > 12:
                        out.append((5, key, y["beat"], "two leaps the same way past an octave"))
            else:
                easy = sum(1 for x, y, d in moves if abs(d) <= 2 or third_to_third(song, tune_notes, part, x, y))
                if moves and easy / len(moves) < 0.7 - 1e-6:
                    out.append((5, key, 0, f"{easy} of {len(moves)} moves repeat, step or go third to third, under 70%"))
                for i, (x, y, d) in enumerate(moves):
                    if abs(d) > 7 and not (abs(d) == 12 and line_start(y)):
                        out.append((5, key, y["beat"], f"a leap of {abs(d)}"))
                    if abs(d) in (6, 10, 11):
                        out.append((5, key, y["beat"], f"a leap of {abs(d)} (a tritone or seventh)"))
                    if abs(d) >= 5 and i + 1 < len(moves) and not line_start(moves[i + 1][1]):
                        # Holding the note is fine; going on the same way, or leaping again, isn't.
                        after = moves[i + 1][2]
                        if (after != 0 and (after > 0) == (d > 0)) or abs(after) > 2:
                            out.append((5, key, moves[i + 1][1]["beat"], f"no step back after a leap of {abs(d)}"))
        # Rule 6: a final note a beginner can hold.
        if notes[-1]["length"] * seconds > FINAL + 1e-6:
            out.append((6, key, notes[-1]["beat"], f"a final note of {notes[-1]['length'] * seconds:.1f} s"))
    return out


PITCH_CLASS = {"C": 0, "D": 2, "E": 4, "F": 5, "G": 7, "A": 9, "B": 11}


def choir(song):
    """The light choir check: ("C", part pair, beat, what)."""
    key = song.get("key", "C")
    tonic = (PITCH_CLASS[key[0]] + (1 if key[1:2] == "#" else -1 if key[1:2] == "b" else 0)) % 12
    leading = (tonic + 11) % 12
    middle = lambda p: sum(n["pitch"] * n["length"] for n in p["guide"]["notes"]) / max(1e-9, sum(n["length"] for n in p["guide"]["notes"]))
    voices = sorted([p for p in song["parts"] if p["guide"]["notes"]], key=middle, reverse=True)
    barbershop = any(p["key"] in ("lead", "baritone") for p in song["parts"])
    # A round's parts are one tune, offset: they cross and meet by design, so only its leading tone counts.
    round_ = "round" in [g.lower() for g in song.get("genre", [])]

    def at(p, beat):
        for n in p["guide"]["notes"]:
            if n["beat"] <= beat + 1e-5 < n["beat"] + n["length"]:
                return n
        return None

    # Onsets to 6 places, as the templates write them: at 4, a note on a third of a beat was read
    # before it started, so the chord compared was the one before it.
    beats = sorted({round(n["beat"], 6) for p in voices for n in p["guide"]["notes"]})
    out, prev = [], None
    for beat in beats:
        ns = [at(p, beat) for p in voices]
        if None in ns:
            prev = None
            continue
        v = [n["pitch"] for n in ns]
        if not barbershop and not round_:
            for i in range(len(v) - 1):
                if v[i + 1] > v[i]:
                    out.append(("C", f"{voices[i + 1]['key']} over {voices[i]['key']}", beat, "a crossing"))
        if sum(1 for x in v if x % 12 == leading) > 1:
            out.append(("C", "all", beat, "a doubled leading tone"))
        if prev and not round_ and not any(e < beat - 1e-6 for e in prev[1]):
            for i in range(len(v)):
                for j in range(i + 1, len(v)):
                    x0, y0, x1, y1 = prev[0][i], prev[0][j], v[i], v[j]
                    if x0 == x1 or y0 == y1 or (x1 > x0) != (y1 > y0):
                        continue
                    was, now = (x0 - y0) % 12, (x1 - y1) % 12
                    if was == now and now in (0, 7):
                        out.append(("C", f"{voices[i]['key']}-{voices[j]['key']}", beat, f"parallel {'octaves' if now == 0 else 'fifths'}"))
        prev = (v, [n["beat"] + n["length"] for n in ns])
    return out


def songs(paths):
    paths = [Path(p).expanduser() for p in paths] or [*(WEB / "templates").glob("*.json"), *(WEB.parent / "server" / "database" / "templates").glob("*.json")]
    for path in sorted(paths, key=lambda p: p.name):
        song = json.loads(path.read_text())
        if isinstance(song, dict) and song.get("parts"):
            yield song


def main():
    args = [a for a in sys.argv[1:] if a not in ("-v", "--choir")]
    with_choir = "--choir" in sys.argv
    verbose = "-v" in sys.argv
    total = Counter()
    for song in songs(args):
        problems = check(song) + (choir(song) if with_choir else [])
        counts = Counter(rule for rule, *_ in problems)
        total.update(counts)
        print(f"{song['id']} (v{song['version']}, {song['tempo']} bpm): " + (", ".join(f"rule {r}: {c}" for r, c in sorted(counts.items(), key=lambda rc: str(rc[0]))) or "singable"))
        if verbose:
            for rule, key, beat, what in sorted(problems, key=lambda p: (str(p[0]), p[1], p[2])):
                print(f"  rule {rule}, {key} at beat {beat:g}: {what}")
    print("singable: " + (", ".join(f"rule {r}: {c}" for r, c in sorted(total.items(), key=lambda rc: str(rc[0]))) or "every song passes"))
    sys.exit(1 if total else 0)


if __name__ == "__main__":
    main()
