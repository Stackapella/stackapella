"""Checks every course file against the course format (web/course/format/steps.json): the free course
and each Plus course in web/course/plus/courses.json. It fails on an unknown step kind or field, a
word that isn't a { "en": ... } object, a lesson id used twice, a clip slot missing from
web/course/plus/clips.json, or a lesson's fact (the "Did you know?" card) that's malformed or told in
two courses; and it counts the lessons an app can't play yet (Flutter's "open this
lesson on the web"). A song course's exercise (course/songs, `song`) must name a song that's here, one
of its parts and phrases it has, and never carry notes of its own. Prints one line; `--apps` lists
those lessons. Python's standard library only.
"""
import json
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parent.parent
FORMAT = json.loads((WEB / "course" / "format" / "steps.json").read_text())
# Where the songs are: the free four and the library (a song held out of the library fails its lessons).
SONG_DIRS = [WEB / "templates", WEB.parent / "server" / "database" / "templates"]
_experiments = WEB / "course" / "format" / "experiments.json"
EXPERIMENTS = set(json.loads(_experiments.read_text())["experiments"]) if _experiments.exists() else set()
STARS = re.compile(r"\b(stars?|estrellas?|sterne?)\b", re.IGNORECASE)  # only game mode shows stars (docs/decisions.md), in any language
KEBAB = re.compile(r"^[a-z0-9]+(-[a-z0-9]+)*$")
# A fact told twice: two facts in different courses sharing two names or years ("Beethoven", "1824").
NAME = re.compile(r"\b[A-Z][\w'’-]*(?:\s+(?:of\s+|de\s+|the\s+)?[A-Z][\w'’-]*)*")
YEAR = re.compile(r"\b1[0-9]{3}s?\b|\b20[0-2][0-9]\b")
COMMON = {"Did", "The", "In", "A", "An", "It", "Its", "This", "Today", "His", "Her", "He", "She", "They", "Their", "When", "By",
          "At", "On", "Your", "You", "From", "After", "Before", "Since", "About", "Many", "Most", "Some", "One", "Two", "Three",
          "Four", "Five", "Like", "And", "But", "For", "As", "Not", "With"}
problems = []
songs = {}


def problem(where, what):
    problems.append(f"{where}: {what}")


def known(where, obj, allowed):
    for key in obj:
        if key not in allowed:
            problem(where, f"unknown field '{key}'")


def words(where, obj):
    """Every word people read is { "en": "..." }."""
    if isinstance(obj, dict):
        for key, value in obj.items():
            if key in FORMAT["words"] and not (isinstance(value, dict) and isinstance(value.get("en"), str) and value["en"].strip()):
                problem(where, f"'{key}' must be {{ \"en\": \"...\" }}")
            elif "—" in json.dumps(value, ensure_ascii=False) and key in FORMAT["words"]:
                problem(where, f"'{key}' has an em dash")
            elif STARS.search(json.dumps(value, ensure_ascii=False)) and key in FORMAT["words"]:
                problem(where, f"'{key}' says stars, which serious mode hides: say what it's scored on")
            elif re.search(r"Did you know|¿Sabías|Schon gewusst|Wusstest du", json.dumps(value, ensure_ascii=False)) and key in FORMAT["words"]:
                problem(where, f"'{key}' has a fact: it belongs in the lesson's fact, the lesson end's card")
            if key not in FORMAT["words"]:
                words(where, value)
    elif isinstance(obj, list):
        for item in obj:
            words(where, item)


def notes(where, items, web_only):
    for n in items or []:
        known(where + " note", n, FORMAT["note"]["fields"])
        if not isinstance(n.get("beat"), (int, float)) or not isinstance(n.get("length"), (int, float)) or n["length"] <= 0:
            problem(where, "a note needs a beat and a positive length")
        if isinstance(n.get("choices"), list) and "step" in n and n["step"] not in n["choices"]:
            problem(where, f"the note at beat {n.get('beat')} sings step {n['step']}, not one of its own choices")
        web_only.update(k for k in n if k in FORMAT["note"]["webOnly"])


def template(song_id):
    """A song's template by id, or None when it isn't here."""
    if song_id not in songs:
        path = next((d / f"{song_id}.json" for d in SONG_DIRS if (d / f"{song_id}.json").exists()), None)
        songs[song_id] = json.loads(path.read_text()) if path else None
    return songs[song_id]


# The course being checked's parts (steps.json courseParts), for its "yours" songs and byPart words.
PARTS_NOW = None


def course_parts(where, parts):
    """A course's parts { template, choose, default }: the singer picks one, and "part": "yours" sings it."""
    if not (isinstance(parts, dict) and set(parts) == {"template", "choose", "default"}):
        problem(where, "parts is { template, choose, default }")
        return None
    t = template(parts["template"])
    if t is None:
        problem(where, f"parts' song '{parts['template']}' isn't one of the songs")
        return None
    keys = [p["key"] for p in t["parts"]]
    choose = parts["choose"]
    if not (isinstance(choose, list) and len(choose) >= 2 and len(set(choose)) == len(choose) and all(c in keys for c in choose)):
        problem(where, f"parts' choose is two or more of {t['id']}'s parts")
        return None
    if parts["default"] not in choose:
        problem(where, "parts' default is one of its choose")
        return None
    return {"template": t["id"], "choose": choose, "default": parts["default"]}


def yours_fits(song_id):
    """"part": "yours" is a song step of the course's own parts song."""
    return PARTS_NOW is not None and PARTS_NOW["template"] == song_id


def by_part_keys(where, value, what):
    """byPart and textByPart are keyed by the course's parts other than its default (whose words are the step's own)."""
    if PARTS_NOW is None:
        problem(where, f"{what} needs the course's parts")
        return []
    if not (isinstance(value, dict) and value and set(value) <= set(PARTS_NOW["choose"]) - {PARTS_NOW["default"]}):
        problem(where, f"{what} is keyed by the course's parts other than its default")
        return []
    text = json.dumps(value, ensure_ascii=False)
    if "\u2014" in text or STARS.search(text):
        problem(where, f"{what} has an em dash or a star")
    return list(value.items())


def by_part(where, value):
    for part, words in by_part_keys(where, value, "byPart"):
        cards = words.get("steps") if isinstance(words, dict) else None
        if not (isinstance(words, dict) and set(words) == {"headline", "steps"} and is_words(words["headline"])
                and isinstance(cards, list) and 1 <= len(cards) <= 3
                and all(isinstance(c, dict) and set(c) == {"title", "detail"} and is_words(c["title"]) and is_words(c["detail"]) for c in cards)):
            problem(where, f"byPart {part} is {{ headline, steps: [{{ title, detail }}] }}, one to three cards")


def text_by_part(where, value):
    for part, words in by_part_keys(where, value, "textByPart"):
        if not is_words(words):
            problem(where, f"textByPart {part} is words")


def song(where, s):
    """An exercise's notes from a song: { template, part, phrases, with? }."""
    if not isinstance(s, dict):
        problem(where, "song must be { template, part, phrases }")
        return
    known(where + " song", s, ["template", "part", "phrases", "with"])
    t = template(s.get("template"))
    if t is None:
        problem(where, f"song '{s.get('template')}' isn't one of the songs")
        return
    if s.get("part") == "yours":
        if not yours_fits(t["id"]):
            problem(where, f"part 'yours' needs the course's parts, for '{t['id']}'")
    elif s.get("part") not in {p["key"] for p in t["parts"]}:
        problem(where, f"song '{t['id']}' has no part '{s.get('part')}'")
    count = sum(len(section["phrases"]) for section in t["sections"])
    phrases = s.get("phrases")
    if not (isinstance(phrases, list) and phrases and all(isinstance(i, int) and 0 <= i < count for i in phrases)):
        problem(where, f"song '{t['id']}' phrases must be a list of its {count} phrases (from 0)")
    if s.get("with", "others") != "others":
        problem(where, "song's with can only be others")


BAR = {"4/4": 4, "3/4": 3, "2/4": 2, "6/8": 3}  # a bar in quarter-note beats


def course_song(where, folder, step):
    """A record step's own song (`courseSong`): a template in the course's own folder, songs/<id>.json."""
    name = step.get("courseSong")
    if "template" in step:
        problem(where, "a record step has template or courseSong, never both")
    if not isinstance(name, str) or not re.fullmatch(r"songs/[a-z0-9-]+\.json", name):
        problem(where, "courseSong must be songs/<id>.json, in the course's own folder")
        return
    path = folder / name
    if not path.exists():
        problem(where, f"courseSong '{name}' isn't in the course's folder")
        return
    try:
        t = json.loads(path.read_text())
    except ValueError as error:
        problem(where, f"courseSong '{name}' isn't JSON: {error}")
        return
    if t.get("id") != path.stem:
        problem(where, f"courseSong '{name}': its id must be '{path.stem}'")
    parts = {p.get("key"): p for p in t.get("parts") or []}
    if not parts:
        problem(where, f"courseSong '{name}' has no parts")
    for key, part in parts.items():
        span = part.get("range") or {}
        for n in (part.get("guide") or {}).get("notes") or []:
            if not isinstance(n.get("pitch"), int) or not span.get("low", 0) <= n["pitch"] <= span.get("high", 127):
                problem(where, f"courseSong '{name}' part {key}: a note outside its range")
                break
    wanted = step.get("part") if isinstance(step.get("part"), list) else [step.get("part")]
    if step.get("part") != "all" and (not wanted or any(k not in parts for k in wanted)):
        problem(where, f"part '{step.get('part')}' isn't one of courseSong '{name}''s parts (a part, a list of parts, or all)")
    voicing = (t.get("voicings") or {}).get(str(step.get("singers")))
    if voicing and step.get("part") != "all" and any(k not in voicing for k in wanted):
        problem(where, f"part '{step.get('part')}' isn't sung by {step.get('singers')} singers in courseSong '{name}'")
    singers = step.get("singers")
    if singers is not None and str(singers) not in (t.get("voicings") or {}):
        problem(where, f"singers {singers}: courseSong '{name}' has no voicing for it")


def stave(where, staff, notes):
    """A line read from the stave (Sight-singing): its names, look, count-in, time and rhythm."""
    ok = (isinstance(staff, dict) and set(staff) <= {"names", "look", "count", "time", "rhythm"}
          and staff.get("names", "on") in ("on", "faint", "off")
          and isinstance(staff.get("look", 4), (int, float)) and 0 <= staff.get("look", 4) <= 10
          and isinstance(staff.get("count", 0), int) and 0 <= staff.get("count", 0) <= 8
          and staff.get("time", "4/4") in BAR and isinstance(staff.get("rhythm", False), bool))
    if not ok:
        return problem(where, "staff is { names: on | faint | off, look: 0 to 10, count: 0 to 8, time: 4/4 | 3/4 | 2/4 | 6/8, rhythm }")
    end = max((n.get("beat", 0) + n.get("length", 0) for n in notes), default=0)
    bar = BAR[staff.get("time", "4/4")]
    if staff.get("time") and end % bar:
        problem(where, f"a {staff['time']} line fills whole bars: it ends at beat {end:g}")


def whole(value, low, high):
    return isinstance(value, int) and not isinstance(value, bool) and low <= value <= high


def limits(where, ex):
    """A play exercise's keyRange { down, up } (0 to 12) and retries (1 to 10): steps.json play.fields."""
    if "keyRange" in ex:
        key_range = ex["keyRange"]
        if not isinstance(key_range, dict) or not key_range:
            problem(where, "keyRange must be { down, up }")
        else:
            for key, value in key_range.items():
                if key not in ("down", "up"):
                    problem(where, f"unknown keyRange field '{key}'")
                elif not whole(value, 0, 12):
                    problem(where, f"keyRange {key} must be a whole number, 0 to 12")
    if "retries" in ex and not whole(ex["retries"], 1, 10):
        problem(where, "retries must be a whole number, 1 to 10")


def daily_cap(where, cap):
    """A course's dailyCap { minutes } (1 to 60): steps.json courseDailyCap."""
    if not isinstance(cap, dict) or set(cap) != {"minutes"} or not whole(cap["minutes"], 1, 60):
        problem(where, "dailyCap must be { minutes }, a whole number of minutes, 1 to 60")


def exercise(where, ex, spec, web_only, clip_ids):
    if spec == "play":
        known(where + " exercise", ex, FORMAT["play"]["fields"])
        if "rhythm" in ex:
            if ex["rhythm"] not in (True, "swing"):
                problem(where, "rhythm is true or \"swing\"")
            elif "score" in ex:
                problem(where, "rhythm only on a default-scored step: a step with its own score keeps it")
        limits(where, ex)
        staff = ex.get("staff")
        if staff is not None:
            stave(where, staff, ex.get("notes") or [])
        if "song" in ex:
            song(where, ex["song"])
            if "notes" in ex:
                problem(where, "an exercise has song or notes, never both")
        web_only.update(k for k in ex if k in FORMAT["play"]["webOnly"])
        notes(where, ex.get("notes"), web_only)
        notes(where, ex.get("answer") if isinstance(ex.get("answer"), list) else [], web_only)
        return
    known(where + " exercise", ex, spec)
    for seg in ex.get("target", []):
        known(where + " target", seg, FORMAT["segment"]["fields"])
        if seg.get("kind") not in FORMAT["segment"]["kinds"]:
            problem(where, f"unknown target kind '{seg.get('kind')}'")
        if not (isinstance(seg.get("seconds"), (int, float)) and 0 < seg["seconds"] <= 15):
            problem(where, "a target segment needs 0 to 15 seconds")
    for r in ex.get("rounds", []):
        known(where + " round", r, FORMAT["round"]["fields"])
        options = r.get("options") or r.get("clips") or []
        if not isinstance(r.get("answer"), int) or not 0 <= r["answer"] < len(options):
            problem(where, "a round's answer must index its options (or clips)")
        for c in r.get("clips", []):
            known(where + " clip", c, FORMAT["clip"]["fields"])
            notes(where, c.get("notes"), set())
            if "clip" in c and c["clip"] not in clip_ids:
                problem(where, f"clip slot '{c['clip']}' isn't in clips.json")
    if "model" in ex:
        known(where + " model", ex["model"], FORMAT["model"]["fields"])
        notes(where, ex["model"].get("notes"), set())


def is_words(value):
    return isinstance(value, dict) and isinstance(value.get("en"), str) and bool(value["en"].strip())


def fact(where, f, facts, course_name):
    """A lesson's fact: { id, text, source, url?, distinct? } (steps.json lessonExtras)."""
    if not isinstance(f, dict):
        return problem(where, "a fact must be { id, text, source }")
    known(where + " fact", f, FORMAT["lessonExtras"]["fact"]["fields"])
    if not KEBAB.match(str(f.get("id", ""))):
        problem(where, "a fact needs a kebab-case id")
    elif f["id"] in facts:
        problem(where, f"fact id '{f['id']}' used twice")
    for key in ("text", "source"):
        if not is_words(f.get(key)):
            problem(where, f"a fact's '{key}' must be {{ \"en\": \"...\" }}")
    if "url" in f and not str(f["url"]).startswith("https://"):
        problem(where, "a fact's url must be https")
    text = f.get("text", {}).get("en", "") if isinstance(f.get("text"), dict) else ""
    for word in (text, f.get("source", {}).get("en", "") if isinstance(f.get("source"), dict) else ""):
        if "—" in word:
            problem(where, "a fact has an em dash")
        if STARS.search(word):
            problem(where, "a fact says stars")
    # One letter alone is a note name or an initial ("F, C, G", "W. E. B."), never a name to match.
    names = {w for w in NAME.findall(text) if len(w) > 1 and not (len(w.split()) == 1 and w in COMMON)}
    distinct = f.get("distinct", [])
    if not (isinstance(distinct, list) and all(isinstance(d, str) for d in distinct)):
        problem(where, "a fact's distinct is a list of fact ids")
        distinct = []
    facts.setdefault(f.get("id"), {"where": where, "course": course_name, "tokens": names | set(YEAR.findall(text)), "distinct": set(distinct)})


def keep_song(where, k, steps):
    """A finale kept as a song: { title, takes: [{ step, name }], from, partners?, chords? }."""
    known(where + " keepSong", k, FORMAT["lessonExtras"]["keepSong"]["fields"])
    if not is_words(k.get("title")):
        problem(where, "keepSong needs a title")
    sung = [i for i, s in enumerate(steps) if s.get("kind") not in ("learn", "choose", "selfcheck", "home")]
    for take in k.get("takes", []) if isinstance(k.get("takes"), list) else [None]:
        if not (isinstance(take, dict) and take.get("step") in sung and is_words(take.get("name"))):
            problem(where, "keepSong's takes are [{ step, name }], each a sung step")
    if "from" in k and k["from"] not in sung:
        problem(where, "keepSong's from must be a sung step")
    if not all(is_words(p) for p in k.get("partners", [])):
        problem(where, "keepSong's partners are words")
    if "chords" in k and not is_words(k["chords"]):
        problem(where, "keepSong's chords is words")


def unscored(where, lesson):
    """A practice-only lesson (the cool-down): nothing in it is scored, and its end has a line."""
    if lesson["unscored"] is not True:
        problem(where, "unscored is true or absent")
    if not is_words(lesson.get("endLine")):
        problem(where, "an unscored lesson needs an endLine")
    if "minutes" in lesson and not (isinstance(lesson["minutes"], int) and 0 < lesson["minutes"] <= 5):
        problem(where, "minutes is a whole number from 1 to 5")
    for i, step in enumerate(lesson.get("steps", [])):
        ex = step.get("exercise") if isinstance(step.get("exercise"), dict) else {}
        if step.get("kind") in ("play", "record", "use", "home") or "score" in ex or "measures" in step:
            problem(f"{where} step {i + 1}", "an unscored lesson has no scored step")
        if any(seg.get("scored") is not False for seg in ex.get("target", [])):
            problem(f"{where} step {i + 1}", "every target in an unscored lesson is scored: false")


def variant(where, key, value, lesson):
    """An A/B arm's steps for a lesson: keyed "<experiment id>-<arm>", the id in course/format/experiments.json."""
    m = re.match(r"^([a-z0-9]+(?:-[a-z0-9]+)*)-([b-z])$", str(key))
    if not m or m.group(1) not in EXPERIMENTS:
        problem(where, f"variant '{key}' isn't '<experiment>-<arm>' for an experiment in course/format/experiments.json")
        return False
    if not (isinstance(value, dict) and set(value) <= {"steps", "minutes"} and isinstance(value.get("steps"), list) and value["steps"]):
        problem(where, f"variant '{key}' is {{ steps, minutes? }} with at least one step")
        return False
    if lesson.get("findsHome") and not any(s.get("kind") == "home" for s in value["steps"]):
        problem(where, f"variant '{key}' drops the step that finds the singer's note")
    return True


GAME_RULES = {"replayXp": bool, "combo": bool, "streak": bool, "meter": str}


def game_rules(where, game):
    """A course's game-mode rules (steps.json courseGame): only these keys, booleans, meter "plain"."""
    if not isinstance(game, dict):
        problem(where, "game must be an object")
        return
    for key, value in game.items():
        if key not in GAME_RULES:
            problem(where, f"unknown game rule '{key}'")
        elif not isinstance(value, GAME_RULES[key]):
            problem(where, f"game {key} must be {GAME_RULES[key].__name__}")
    if "meter" in game and game["meter"] != "plain":
        problem(where, "game meter's only value is \"plain\"")


def course(path, seen, clip_ids, unplayable, facts):
    data = json.loads(path.read_text())
    name = path.relative_to(WEB / "course")
    known(str(name), data, FORMAT["course"])
    global PARTS_NOW
    PARTS_NOW = course_parts(str(name), data["parts"]) if "parts" in data else None
    if "game" in data:
        game_rules(str(name), data["game"])
    if "dailyCap" in data:
        daily_cap(str(name), data["dailyCap"])
    if "headphones" in data and data["headphones"] != "needed":
        problem(str(name), "headphones is \"needed\" (its only value), or absent")
    lessons = 0
    kept = set()  # Then and now: the ids kept so far in this course, lesson by lesson
    for unit in data.get("units", []):
        known(f"{name} {unit.get('id')}", unit, FORMAT["unit"])
        for lesson in unit.get("lessons", []):
            lessons += 1
            lid = lesson.get("id", "?")
            where = f"{name} {lid}"
            known(where, lesson, FORMAT["lesson"])
            if "quiet" in lesson and not isinstance(lesson["quiet"], bool):
                problem(where, "quiet must be true or false")
            if "headphones" in lesson and lesson["headphones"] != "needed":
                problem(where, "headphones is \"needed\" (its only value), or absent")
            if lid in seen:
                problem(where, "lesson id used twice")
            seen.add(lid)
            words(where, {k: v for k, v in lesson.items() if k not in ("fact", "keepSong")})
            if "fact" in lesson:
                fact(where, lesson["fact"], facts, str(name))
            if "keepSong" in lesson:
                keep_song(where, lesson["keepSong"], lesson.get("steps", []))
            if lesson.get("unscored"):
                unscored(where, lesson)
            elif "endLine" in lesson:
                problem(where, "endLine belongs to an unscored lesson")
            flutter = set()
            # The lesson's steps, then each A/B variant's (docs/experiments.md), checked the same way.
            step_lists = [("", lesson.get("steps", []))]
            for key, value in (lesson.get("variants") or {}).items():
                if variant(where, key, value, lesson):
                    step_lists.append((f" {key}", value["steps"]))
            for label, steps in step_lists:
                flutter |= check_steps(f"{where}{label}", steps, path, clip_ids, kept)
            kept.update(s["exercise"]["keep"] for s in lesson.get("steps", []) if isinstance(s.get("exercise"), dict) and s["exercise"].get("keep"))
            if flutter:
                unplayable[lid] = sorted(flutter)
        if "boss" in unit:
            boss(f"{name} {unit.get('id')}", unit["boss"], seen, path, clip_ids)
    return lessons


BOSS_KINDS = ("play",)  # a boss is one go: a record step would leave it for Sing


def boss(where, b, seen, path, clip_ids):
    """A unit's boss round (docs/boss-round-plan.md): { id, title, steps }, beside the unit's lessons
    and never in them; 2 to 4 sung steps of the kinds that exist (play, record), checked as a lesson's
    are, with no keep or Then and now (a boss starts nothing and compares nothing)."""
    if not isinstance(b, dict):
        problem(where, "boss must be { id, title, steps }")
        return
    known(where + " boss", b, ["id", "title", "steps"])
    bid = b.get("id")
    if not isinstance(bid, str) or not re.fullmatch(r"[a-z0-9]+(-[a-z0-9]+)*-boss", bid):
        problem(where, "a boss's id is the unit's prefix and number then -boss, as 3-boss")
    elif bid in seen:
        problem(where, f"boss id '{bid}' used twice")
    else:
        seen.add(bid)
    words(where + " boss", {"title": b.get("title")})
    steps = b.get("steps")
    if not isinstance(steps, list) or not 2 <= len(steps) <= 4:
        problem(where, "a boss has 2 to 4 steps")
        return
    for i, step in enumerate(steps):
        if step.get("kind") not in BOSS_KINDS:
            problem(f"{where} boss step {i + 1}", f"a boss's steps are sung: {', '.join(BOSS_KINDS)}")
        ex = step.get("exercise") if isinstance(step.get("exercise"), dict) else {}
        if ex.get("keep") or ex.get("thenAndNow"):
            problem(f"{where} boss step {i + 1}", "a boss keeps nothing and plays no Then and now")
    words(where + " boss", {"steps": steps})
    check_steps(f"{where} boss", steps, path, clip_ids, set())


def under(where, value, index):
    """An earlier take to sing over (steps.json play.under): { step }, or layers { steps: [n | { step, loop }],
    loop? }, each an earlier step of this lesson, each loop a length in beats (Live looping)."""
    def earlier(n):
        return isinstance(n, int) and not isinstance(n, bool) and 0 <= n < index
    def length(x):
        return isinstance(x, (int, float)) and not isinstance(x, bool) and x > 0
    if not isinstance(value, dict):
        problem(where, "under is { step } or { steps, loop? }")
    elif "step" in value:
        if set(value) != {"step"} or not earlier(value["step"]):
            problem(where, "under { step } names an earlier step of this lesson")
    elif not (set(value) <= {"steps", "loop"} and isinstance(value.get("steps"), list) and value["steps"]):
        problem(where, "under is { step } or { steps: [...], loop? }")
    else:
        if "loop" in value and not length(value["loop"]):
            problem(where, "under's loop is a length in beats")
        for layer in value["steps"]:
            if isinstance(layer, dict):
                if not (set(layer) <= {"step", "loop"} and earlier(layer.get("step")) and ("loop" not in layer or length(layer["loop"]))):
                    problem(where, "an under layer is { step, loop? }: an earlier step, a length in beats")
            elif not earlier(layer):
                problem(where, "an under layer names an earlier step of this lesson")


def check_steps(where, steps, path, clip_ids, kept):
    """Each step's kind and fields, its song and exercise; returns what Flutter can't play yet."""
    flutter = set()
    for i, step in enumerate(steps):
        at = f"{where} step {i + 1}"
        kind = FORMAT["kinds"].get(step.get("kind"))
        if not kind:
            problem(at, f"unknown step kind '{step.get('kind')}'")
            continue
        known(at, step, kind["fields"])
        if step.get("part") == "yours" and not yours_fits(step.get("template")):
            problem(at, "a record step's part 'yours' needs the course's parts, for its song")
        if "byPart" in step:
            by_part(at, step["byPart"])
        if "textByPart" in step:
            text_by_part(at, step["textByPart"])
        if "template" in step and template(step["template"]) is None:
            problem(at, f"song '{step['template']}' isn't one of the songs")
        if "courseSong" in step:
            course_song(at, path.parent, step)
        web_only = set()
        if "exercise" in step:
            exercise(at, step["exercise"], kind.get("exercise", "play"), web_only, clip_ids)
        if "listen" in step:
            exercise(at, step["listen"], "play", web_only, clip_ids)
        ex = step.get("exercise") if isinstance(step.get("exercise"), dict) else {}
        if "under" in ex:
            under(at, ex["under"], i)
        if ex.get("thenAndNow") and ex["thenAndNow"] not in kept:
            problem(at, f"thenAndNow '{ex['thenAndNow']}' isn't kept by an earlier lesson of this course")
        if kind["flutter"] != "yes":
            flutter.add(f"{step['kind']} ({kind['flutter']})")
        flutter.update(f"play.{k}" for k in web_only)
    return flutter


WARMUP_SOUNDS = ("ah", "ee", "oo", "oh", "hum", "trill")  # js/warmup.js SOUNDS


def warmups():
    """The public warm-up player's list (course/warmups.json): each entry's course step exists and
    still has its `pattern`, every note stays inside `bounds` at the full climb, and the tempo,
    climb and sound are inside the warm-up engine's limits (js/warmup.js LIMITS)."""
    path = WEB / "course" / "warmups.json"
    if not path.exists():
        return
    data = json.loads(path.read_text())
    catalogue = json.loads((WEB / "course" / "plus" / "courses.json").read_text())["courses"]
    files = {c["id"]: WEB / "course" / "plus" / c["file"] for c in catalogue if c.get("file")}
    files["free"] = WEB / "course" / "course.json"
    ids = set()
    for w in data.get("warmups", []):
        where = f"warmups.json {w.get('id')}"
        if not re.fullmatch(r"[a-z0-9]+(-[a-z0-9]+)*", str(w.get("id", ""))) or w["id"] in ids:
            problem(where, "id must be a unique kebab-case slug")
        ids.add(w.get("id"))
        for key in ("name", "instruction", "stop"):
            if not isinstance(w.get(key), dict) or not w[key].get("en"):
                problem(where, f"{key} needs its English words")
            else:
                words(where + " " + key, w[key])
        if w.get("sound") not in WARMUP_SOUNDS:
            problem(where, f"sound must be one of {', '.join(WARMUP_SOUNDS)}")
        src = w.get("from", {})
        f = files.get(src.get("course"))
        if f is None or not f.exists():
            problem(where, f"course '{src.get('course')}' isn't a course file")
            continue
        lesson = next((l for u in json.loads(f.read_text())["units"] for l in u["lessons"] if l["id"] == src.get("lesson")), None)
        steps = lesson.get("steps", []) if lesson else []
        if not isinstance(src.get("step"), int) or not 0 <= src["step"] < len(steps):
            problem(where, f"lesson '{src.get('lesson')}' has no step {src.get('step')}")
            continue
        notes = sorted((steps[src["step"]].get("exercise") or {}).get("notes") or [], key=lambda n: n["beat"])
        if not 3 <= len(notes) <= 16:
            problem(where, "its step needs 3 to 16 notes, as a warm-up does")
            continue
        pattern = [n["step"] - notes[0]["step"] for n in notes]
        if pattern != w.get("pattern"):
            problem(where, f"its step's notes are now {pattern}, not the pattern {w.get('pattern')}: the course changed, so check the warm-up again")
        tempo, climb, bounds = w.get("tempo", {}), w.get("climb", {}), w.get("bounds", {})
        if not (50 <= tempo.get("min", 0) <= tempo.get("default", 0) <= tempo.get("max", 0) <= 120):
            problem(where, "tempo needs min <= default <= max, all from 50 to 120")
        if not (0 <= climb.get("default", -1) <= climb.get("max", -1) <= 7):
            problem(where, "climb needs 0 <= default <= max <= 7")
        low, high = notes[0]["step"] + min(pattern), notes[0]["step"] + max(pattern) + climb.get("max", 0)
        if not (isinstance(bounds.get("low"), int) and isinstance(bounds.get("high"), int)) or low < bounds["low"] or high > bounds["high"]:
            problem(where, f"its notes run from {low:+d} to {high:+d} at the full climb, outside its bounds")


def main():
    seen, unplayable, facts = set(), {}, {}
    clips_path = WEB / "course" / "plus" / "clips.json"
    clips = json.loads(clips_path.read_text()) if clips_path.exists() else {"clips": []}
    clip_ids = {c["id"] for c in clips["clips"]}
    for c in clips["clips"] if "skills" in clips else []:  # Lend your voice: each slot names the skill it needs, from the list's own skills
        if c.get("skill") not in clips["skills"]:
            problem(f"clips.json {c.get('id')}", f"skill '{c.get('skill')}' isn't one of the list's skills")
    files = [WEB / "course" / "course.json"]
    if (WEB / "course" / "cooldown.json").exists():
        files.append(WEB / "course" / "cooldown.json")  # the cool-down: one unscored lesson
    catalogue = WEB / "course" / "plus" / "courses.json"
    if catalogue.exists():
        files += [WEB / "course" / "plus" / c["file"] for c in json.loads(catalogue.read_text())["courses"] if c.get("file")]
    # The song courses (tools/song_course.py), one a song; their index.json isn't a course.
    files += sorted(f for f in (WEB / "course" / "songs").glob("*.json") if f.name != "index.json")
    lessons = sum(course(f, seen, clip_ids, unplayable, facts) for f in files if f.exists())
    told = list(facts.items())
    for i, (a, fa) in enumerate(told):
        for b, fb in told[i + 1:]:
            shared = fa["tokens"] & fb["tokens"]
            if fa.get("course") != fb.get("course") and len(shared) >= 2 and b not in fa["distinct"] and a not in fb["distinct"]:
                problem(fb["where"], f"fact '{b}' looks like '{a}' ({fa['where']}), sharing {', '.join(sorted(shared))}: tell it in one course, or list it in distinct")
    warmups()
    for f in files:
        if not f.exists():
            problem(str(f.relative_to(WEB)), "the catalogue names a file that isn't there")
    for p in problems:
        print(p)
    print(f"courses: {len(files)} files, {lessons} lessons, {len(problems)} problems; Flutter can't verify or play {len(unplayable)} lessons yet")
    if "--apps" in sys.argv:
        for lid, why in sorted(unplayable.items()):
            print(f"  {lid}: {', '.join(why)}")
    sys.exit(1 if problems else 0)


main()
