"""Checks every course file against the course format (web/course/format/steps.json): the free course
and each Plus course in web/course/plus/courses.json. It fails on an unknown step kind or field, a
word that isn't a { "en": ... } object, a lesson id used twice, a clip slot missing from
web/course/plus/clips.json, or a lesson's fact (the "Did you know?" card) that's malformed, more than two
a unit, or told in two courses; and it counts the lessons an app can't play yet (Flutter's "open this
lesson on the web"). Prints one line; `--apps` lists those lessons. Python's standard library only.
"""
import json
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parent.parent
FORMAT = json.loads((WEB / "course" / "format" / "steps.json").read_text())
STARS = re.compile(r"\bstars?\b", re.IGNORECASE)  # only game mode shows stars (docs/decisions.md)
KEBAB = re.compile(r"^[a-z0-9]+(-[a-z0-9]+)*$")
# A fact told twice: two facts in different courses sharing two names or years ("Beethoven", "1824").
NAME = re.compile(r"\b[A-Z][\w'’-]*(?:\s+(?:of\s+|de\s+|the\s+)?[A-Z][\w'’-]*)*")
YEAR = re.compile(r"\b1[0-9]{3}s?\b|\b20[0-2][0-9]\b")
COMMON = {"Did", "The", "In", "A", "An", "It", "Its", "This", "Today", "His", "Her", "He", "She", "They", "Their", "When", "By",
          "At", "On", "Your", "You", "From", "After", "Before", "Since", "About", "Many", "Most", "Some", "One", "Two", "Three",
          "Four", "Five", "Like", "And", "But", "For", "As", "Not", "With"}
problems = []


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
            elif "Did you know" in json.dumps(value, ensure_ascii=False) and key in FORMAT["words"]:
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
        web_only.update(k for k in n if k in FORMAT["note"]["webOnly"])


def exercise(where, ex, spec, web_only, clip_ids):
    if spec == "play":
        known(where + " exercise", ex, FORMAT["play"]["fields"])
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
    names = {w for w in NAME.findall(text) if not (len(w.split()) == 1 and w in COMMON)}
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


def course(path, seen, clip_ids, unplayable, facts):
    data = json.loads(path.read_text())
    name = path.relative_to(WEB / "course")
    known(str(name), data, FORMAT["course"])
    lessons = 0
    kept = set()  # Then and now: the ids kept so far in this course, lesson by lesson
    for unit in data.get("units", []):
        known(f"{name} {unit.get('id')}", unit, FORMAT["unit"])
        if sum(1 for lesson in unit.get("lessons", []) if "fact" in lesson) > 2:
            problem(f"{name} {unit.get('id')}", "more than two facts in a unit")
        for lesson in unit.get("lessons", []):
            lessons += 1
            lid = lesson.get("id", "?")
            where = f"{name} {lid}"
            known(where, lesson, FORMAT["lesson"])
            if lid in seen:
                problem(where, "lesson id used twice")
            seen.add(lid)
            words(where, {k: v for k, v in lesson.items() if k not in ("fact", "keepSong")})
            if "fact" in lesson:
                fact(where, lesson["fact"], facts, str(name))
            if "keepSong" in lesson:
                keep_song(where, lesson["keepSong"], lesson.get("steps", []))
            flutter = set()
            for i, step in enumerate(lesson.get("steps", [])):
                at = f"{where} step {i + 1}"
                kind = FORMAT["kinds"].get(step.get("kind"))
                if not kind:
                    problem(at, f"unknown step kind '{step.get('kind')}'")
                    continue
                known(at, step, kind["fields"])
                web_only = set()
                if "exercise" in step:
                    exercise(at, step["exercise"], kind.get("exercise", "play"), web_only, clip_ids)
                if "listen" in step:
                    exercise(at, step["listen"], "play", web_only, clip_ids)
                ex = step.get("exercise") if isinstance(step.get("exercise"), dict) else {}
                if ex.get("thenAndNow") and ex["thenAndNow"] not in kept:
                    problem(at, f"thenAndNow '{ex['thenAndNow']}' isn't kept by an earlier lesson of this course")
                if kind["flutter"] != "yes":
                    flutter.add(f"{step['kind']} ({kind['flutter']})")
                flutter.update(f"play.{k}" for k in web_only)
            kept.update(s["exercise"]["keep"] for s in lesson.get("steps", []) if isinstance(s.get("exercise"), dict) and s["exercise"].get("keep"))
            if flutter:
                unplayable[lid] = sorted(flutter)
    return lessons


def main():
    seen, unplayable, facts = set(), {}, {}
    clips_path = WEB / "course" / "plus" / "clips.json"
    clip_ids = {c["id"] for c in json.loads(clips_path.read_text())["clips"]} if clips_path.exists() else set()
    files = [WEB / "course" / "course.json"]
    catalogue = WEB / "course" / "plus" / "courses.json"
    if catalogue.exists():
        files += [WEB / "course" / "plus" / c["file"] for c in json.loads(catalogue.read_text())["courses"] if c.get("file")]
    lessons = sum(course(f, seen, clip_ids, unplayable, facts) for f in files if f.exists())
    told = list(facts.items())
    for i, (a, fa) in enumerate(told):
        for b, fb in told[i + 1:]:
            shared = fa["tokens"] & fb["tokens"]
            if fa.get("course") != fb.get("course") and len(shared) >= 2 and b not in fa["distinct"] and a not in fb["distinct"]:
                problem(fb["where"], f"fact '{b}' looks like '{a}' ({fa['where']}), sharing {', '.join(sorted(shared))}: tell it in one course, or list it in distinct")
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
