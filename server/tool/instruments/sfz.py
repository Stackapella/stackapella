"""A small SFZ reader: enough of the format for the CC0 sets we build from (docs/instrument-samples.md).

It reads #define and #include, the <control>, <global>, <master>, <group> and <region> headers with
their inheritance, and gives each region's opcodes as a flat dict, plus the <curve>s. Effects
and controller modulation are read but not interpreted here: the build premixes at the program's
default settings (build.py applies the level modulators).
"""
import re
from pathlib import PurePosixPath

HEADER = re.compile(r"<(\w+)>")
OPCODE = re.compile(r"([A-Za-z_][A-Za-z0-9_$]*)=")
NOTE = re.compile(r"^([a-gA-G])([#b]?)(-?\d+)$")
STEPS = {"c": 0, "d": 2, "e": 4, "f": 5, "g": 7, "a": 9, "b": 11}
LEVELS = {"global": 0, "master": 1, "group": 2, "region": 3}


def note_number(value, middle_c_octave=4):
    """A key as SFZ writes it: a number, or a name like c#4 (c4 is 60 by default)."""
    value = str(value).strip()
    if re.fullmatch(r"-?\d+", value):
        return int(value)
    match = NOTE.match(value)
    if not match:
        raise ValueError(f"not a note: {value}")
    letter, accidental, octave = match.groups()
    step = STEPS[letter.lower()] + (1 if accidental == "#" else -1 if accidental == "b" else 0)
    return 60 + (int(octave) - middle_c_octave) * 12 + step


def strip_comments(text):
    text = re.sub(r"/\*.*?\*/", " ", text, flags=re.S)
    return re.sub(r"//[^\n]*", "", text)


def expand(path, read, defines=None, depth=0, root=None, exists=None):
    """The program's text with its includes in place and its defines applied. `read(path)` gives
    a file's text, by its path from the repo's root. An include is found from the program's own
    folder (as ARIA and sfizz do), else from the file naming it; `exists(path)` tells which."""
    root = root or path
    if depth > 20:
        raise ValueError("includes nest too deep")
    defines = {} if defines is None else defines
    out = []
    for line in strip_comments(read(path)).splitlines():
        stripped = line.strip()
        define = re.match(r'#define\s+(\$\w+)\s+(.*)$', stripped)
        include = re.match(r'#include\s+"([^"]+)"', stripped)
        if define:
            defines[define.group(1)] = define.group(2).strip()
            continue
        if include:
            target = include.group(1).replace("\\", "/")
            for name in sorted(defines, key=len, reverse=True):
                target = target.replace(name, defines[name])
            found = resolve(root, target)
            if exists is not None and not exists(found):
                found = resolve(path, target)
            out.append(expand(found, read, defines, depth + 1, root, exists))
            continue
        for name in sorted(defines, key=len, reverse=True):
            line = line.replace(name, defines[name])
        out.append(line)
    return "\n".join(out)


def resolve(base, target):
    """An include's path: relative to the file that names it."""
    parts = []
    for part in (PurePosixPath(base).parent / target).parts:
        if part == "..":
            if parts:
                parts.pop()
        elif part != ".":
            parts.append(part)
    return "/".join(parts)


def opcodes(text):
    """key=value pairs from a header's body; a value runs to the next opcode (sample paths have spaces)."""
    found = {}
    matches = list(OPCODE.finditer(text))
    for i, match in enumerate(matches):
        end = matches[i + 1].start() if i + 1 < len(matches) else len(text)
        found[match.group(1)] = text[match.end():end].strip()
    return found


def curve_points(values):
    """A <curve>'s points (vNNN=y) as (x, y) pairs, x from 0 to 127."""
    return sorted((int(key[1:]), float(value)) for key, value in values.items() if re.fullmatch(r"v\d{3}", key))


def regions(path, read, exists=None):
    """Every region of the program, each with what it inherits, plus the <control> settings (its
    curves under "curves", by index)."""
    text = expand(path, read, exists=exists)
    control = {"curves": {}}
    scopes = [{}, {}, {}]
    found = []
    pieces = HEADER.split(text)
    # pieces: [before, header, body, header, body, ...]
    for i in range(1, len(pieces), 2):
        header, body = pieces[i].lower(), pieces[i + 1]
        values = opcodes(body)
        if header == "control":
            control.update(values)
        elif header == "curve":
            control["curves"][int(values.get("curve_index", -1))] = curve_points(values)
        elif header in LEVELS and header != "region":
            level = LEVELS[header]
            scopes[level] = dict(values)
            for lower in range(level + 1, 3):
                scopes[lower] = {}
        elif header == "region":
            merged = {}
            for scope in scopes:
                merged.update(scope)
            # A region's own key= sets its keys, over a lokey or hikey it inherits.
            if "key" in values:
                for inherited in ("lokey", "hikey", "pitch_keycenter"):
                    if inherited not in values:
                        merged.pop(inherited, None)
            merged.update(values)
            found.append(merged)
    return control, found


def region_fields(region, control, program):
    """What the build needs from a region: its sample's path from the repo root, keys, velocities,
    round robin and level."""
    octave_offset = int(control.get("octave_offset", 0))
    note_offset = int(control.get("note_offset", 0)) + 12 * octave_offset
    sample = (control.get("default_path", "") + region["sample"]).replace("\\", "/")
    key = region.get("key")
    lo = note_number(region.get("lokey", key if key is not None else 0))
    hi = note_number(region.get("hikey", key if key is not None else 127))
    # SFZ's default key center is 60 (middle C), not the region's lowest key.
    center = region.get("pitch_keycenter", key if key is not None else 60)
    if str(center).strip().lower() == "sample":
        raise ValueError(f"{program}: pitch_keycenter=sample (the root from the file) isn't read; name the key")
    center = note_number(center)
    return {
        "sample": resolve(program, sample),
        "lo": lo + note_offset,
        "hi": hi + note_offset,
        "root": center + note_offset,
        "velLo": int(region.get("lovel", 1)),
        "velHi": int(region.get("hivel", 127)),
        "tune": float(region.get("tune", 0)) + 100 * float(region.get("transpose", 0)),
        "volume": float(region.get("volume", 0)),
        "amplitude": float(region.get("amplitude", 100)),
        "seq": int(region.get("seq_position", 1)),
        "random": float(region.get("lorand", 0)),
        "trigger": region.get("trigger", "attack"),
        "offset": int(region.get("offset", 0)),
        "ccConditions": {k: v for k, v in region.items() if k.startswith(("locc", "hicc"))},
        "raw": region,
    }
