"""Fails on a count the app would drop (2026-11-24: Hear yourself as a choir, Ready by rehearsal, take-away tracks and
lesson 1's live chord were counted on the page but never sent, as js/usage.js count() keeps only the kinds it knows).
Every literal count('kind') (and countChoir, countVoice) in js/ must be a kind js/usage.js lists, a detailed kind
(`<prefix>:<id>`, its DETAILED set), or one of its pattern families (library_filter:, sh:, aic:, ai_card:, ai_use:).
A kind the server's config/counts.php doesn't list is said as a warning (the server drops it), not a failure here.
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
USAGE = (ROOT / "js" / "usage.js").read_text()
SERVER = ROOT.parent / "server" / "config" / "counts.php"
CALL = re.compile(r"\b(?:count|countChoir|countVoice)\('([a-z0-9_:.\-]+)'\)")
FAMILIES = ("library_filter:", "sh:", "aic:", "ai_card:", "ai_use:")

listed = set(re.findall(r"'([a-z0-9_:.\-]+)'", USAGE))
detailed = set(re.findall(r"'([a-z]+_[a-z])'", re.search(r"const DETAILED = new Set\(\[([^\]]*)\]\)", USAGE).group(1)))
server = set(re.findall(r"'([a-z0-9_:.\-]+)'", SERVER.read_text())) if SERVER.exists() else None
# The server's detailed kinds: 'bv_stop' => 'bv_stops', and 'bv_stops' => ['age', ...]: bv_stop:age is one.
details = {}
if SERVER.exists():
    text = SERVER.read_text()
    lists = {m.group(1): set(re.findall(r"'([a-z0-9_\-]+)'", m.group(2))) for m in re.finditer(r"'([a-z_]+)' => \[([^\]]*)\]", text)}
    for m in re.finditer(r"'([a-z_]+)' => '([a-z_]+)'", text):
        details[m.group(1)] = lists.get(m.group(2), set())


def on_server(kind):
    prefix, _, detail = kind.partition(":")
    return kind in server or (detail and detail in details.get(prefix, set()))


problems, warnings = [], []
for path in sorted((ROOT / "js").rglob("*.js")):
    if path.name == "usage.js":
        continue
    for number, line in enumerate(path.read_text().splitlines(), 1):
        for match in CALL.finditer(line.split("//")[0]):
            kind = match.group(1)
            known = kind in listed or kind.split(":")[0] in detailed or kind.startswith(FAMILIES)
            if not known:
                problems.append(f"{path.relative_to(ROOT)}:{number}: count('{kind}') is dropped: add it to js/usage.js (and the server's config/counts.php)")
            elif server is not None and not on_server(kind) and not kind.startswith(FAMILIES) and kind.split(":")[0] not in detailed:
                warnings.append(f"{path.relative_to(ROOT)}:{number}: count('{kind}') isn't in the server's config/counts.php: the server drops it")
for line in problems + [f"warning: {w}" for w in warnings]:
    print(line)
print(f"counts: {len(problems)} problems, {len(warnings)} warnings")
sys.exit(1 if problems else 0)
