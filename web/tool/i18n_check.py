"""Checks the app's words (docs/architecture.md, "Languages"), run by tool/test.sh:

- every key used in web/js (t('key'), tk('key')) is in locales/en.json, and every key in en.json
  is used;
- every message parses (balanced braces, plural cases with an "other");
- every other locales/<lang>.json has only keys en.json has, and the same {arguments};
- Vue templates hold no literal words: no text between tags and no plain title, aria-label,
  placeholder or alt attribute with letters (bound ones go through t());
- toast(), confirm() and alert() are never given a literal sentence.

Prints each problem and exits 1 if there are any. Python standard library only.
"""
import json
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parent.parent
problems = []


def flatten(node, prefix=""):
    out = {}
    for key, value in node.items():
        path = f"{prefix}{key}"
        if isinstance(value, dict):
            out.update(flatten(value, path + "."))
        else:
            out[path] = value
    return out


def arguments(message):
    return set(re.findall(r"\{\s*(\w+)\s*[,}]", message))


def parses(message):
    depth = 0
    for char in message:
        depth += char == "{"
        depth -= char == "}"
        if depth < 0:
            return False
    if depth != 0:
        return False
    for plural in re.finditer(r"\{\s*\w+\s*,\s*plural\s*,", message):
        if "other {" not in message[plural.end():]:
            return False
    return True


en = flatten(json.loads((WEB / "locales/en.json").read_text()))
for key, message in en.items():
    if not isinstance(message, str):
        problems.append(f"en.json: {key} is not a string")
    elif not parses(message):
        problems.append(f"en.json: {key} doesn't parse: {message}")
    elif "—" in message:
        problems.append(f"en.json: {key} has an em dash")

for path in sorted((WEB / "locales").glob("*.json")):
    if path.name == "en.json":
        continue
    other = flatten(json.loads(path.read_text()))
    for key, message in other.items():
        if key not in en:
            problems.append(f"{path.name}: {key} isn't in en.json")
        elif arguments(message) != arguments(en[key]):
            problems.append(f"{path.name}: {key} has other arguments than en.json")

used = set()
KEY = re.compile(r"""\bt[k]?\(\s*['"]([\w.]+)['"]""")
# sk('serious.key', 'game.key') (js/skin.js): both keys are used.
SKIN_KEYS = re.compile(r"""\bsk\(\s*['"]([\w.]+)['"]\s*,\s*['"]([\w.]+)['"]""")
TEMPLATE = re.compile(r"template:\s*`(.*?)`", re.S)
STATIC_ATTRIBUTE = re.compile(r"""(?<![:\w-])(title|aria-label|placeholder|alt|label)="([^"]*)\"""")
# A quoted sentence or capitalised word in code: 'Stop recording', "Saved", `Bar ${n}`.
WORDS = re.compile(r"""'([A-Z][a-z]+(?:[ ,.:!?][^']*)?)'|"([A-Z][a-z]+(?:[ ,.:!?'][^"]*)?)"|`([A-Z][a-z]+(?:[ ,.:!?'][^`]*)?)`""")
NOT_WORDS = re.compile(r"console\.|new Error|DOMException|TemplateError|throw |need\(|i18n-ignore|import |querySelector|\.name ===|error\?\.name|Intl\.|event\.(key|code)")
LITERAL_CALL = re.compile(r"""\b(toast|confirm|alert)\(\s*(['"`])""")
ALLOWED_TEXT = {"stackapella", "WAV", "MP3"}

for path in sorted((WEB / "js").rglob("*.js")):
    # Comments don't count (i18n.js shows examples in its header).
    source = "\n".join("" if line.strip().startswith(("//", "*", "/*")) else line for line in path.read_text().splitlines())
    name = path.relative_to(WEB)
    used.update(KEY.findall(source))
    used.update(key for pair in SKIN_KEYS.findall(source) for key in pair)
    for call in LITERAL_CALL.finditer(source):
        line = source.count("\n", 0, call.start()) + 1
        problems.append(f"{name}:{line}: {call.group(1)}() with a literal string; use t()")
    for template in TEMPLATE.finditer(source):
        markup = template.group(1)
        base = source.count("\n", 0, template.start(1)) + 1
        stripped = re.sub(r"\{\{.*?\}\}", " ", markup, flags=re.S)
        stripped = re.sub(r"<!--.*?-->", " ", stripped, flags=re.S)
        for text in re.finditer(r">([^<>]+)<", stripped):
            words = text.group(1).strip()
            if re.search(r"[A-Za-z]{2,}", words) and words not in ALLOWED_TEXT:
                line = base + stripped.count("\n", 0, text.start())
                problems.append(f"{name}:{line}: literal text in a template: {words[:60]!r}")
        for attribute in STATIC_ATTRIBUTE.finditer(markup):
            if re.search(r"[A-Za-z]{2,}", attribute.group(2)):
                line = base + markup.count("\n", 0, attribute.start())
                problems.append(f"{name}:{line}: literal {attribute.group(1)}=\"{attribute.group(2)[:40]}\"; bind it to t()")

for key in sorted(used - set(en)):
    problems.append(f"missing from en.json: {key}")
# Keys only the native apps use (they read this file too): listed in locales/app-only.txt, one a line.
app_only = {line.strip() for line in (WEB / "locales/app-only.txt").read_text().splitlines() if line.strip() and not line.startswith("#")}
for key in sorted(app_only - set(en)):
    problems.append(f"app-only.txt: {key} isn't in en.json")
# Keys the public site and the guide name (AppWords::get, ::fill or ::format('...') in the server's views): used too, and
# their removal is caught here before the server's AppWordsTest catches it.
site_words = set()
for path in (WEB.parent / "server/resources/views").rglob("*.blade.php"):
    site_words |= set(re.findall(r"AppWords::(?:get|fill|format)\(\s*'([a-zA-Z0-9_.]+)'", path.read_text()))
for key in sorted(site_words - set(en)):
    problems.append(f"the site names {key}, which isn't in en.json")
for key in sorted(set(en) - used - app_only - site_words):
    problems.append(f"unused in en.json: {key}")

# Words in code outside t(): in templates' {{ }} and bound attributes, and in plain code.
for path in sorted((WEB / "js").rglob("*.js")):
    if path.name == "i18n.js":
        continue
    for number, line in enumerate(path.read_text().splitlines(), 1):
        if line.strip().startswith(("//", "*", "/*")) or NOT_WORDS.search(line):
            continue
        for match in WORDS.finditer(line):
            words = next(group for group in match.groups() if group)
            if words in ALLOWED_TEXT or re.fullmatch(r"[A-G]b", words) or re.fullmatch(r"[A-Z][a-z]+(Error|Dialog|View|Bar|Lane|Note|Lyrics|Toggle)", words):
                continue
            problems.append(f"{path.relative_to(WEB)}:{number}: words outside t(): {words[:50]!r}")

for path in sorted((WEB / "locales").glob("*.json")):
    for key, message in flatten(json.loads(path.read_text())).items():
        if isinstance(message, str) and not parses(message) and path.name != "en.json":
            problems.append(f"{path.name}: {key} doesn't parse: {message}")

for problem in problems:
    print(problem)
print(f"words: {len(en)} keys, {len(problems)} problems")
sys.exit(1 if problems else 0)
