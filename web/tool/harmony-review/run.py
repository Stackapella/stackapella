"""The harmony review: every library and free song's melody and ten hummed tunes, harmonized in each
style, judged as a choir course would (review.js), faults counted by kind. Writes every fault to
`out.json` for a closer look, and prints the totals (docs/handoff-arranger.md has the last table).
With --template, it judges a song file's own written parts instead (a new library song before it
lands), printing every fault with its beat.

    sh server/tool/content.sh link
    python3 -I web/tool/harmony-review/run.py [out.json]
    python3 -I web/tool/harmony-review/run.py --template ~/Code/content-arranger/templates/library/x.json
"""
import json
import sys
from pathlib import Path
from collections import Counter

sys.path.insert(0, str(Path(__file__).resolve().parent))  # python3 -I leaves it out
from common import review, songs  # noqa: E402

if len(sys.argv) > 2 and sys.argv[1] == "--template":
    song = json.loads(Path(sys.argv[2]).expanduser().read_text())
    with review() as run:
        judged = run(f"return m.judgeTemplate({json.dumps(song)});")
    print(song.get("id"), "as", " ".join(judged["voices"]), "top to bottom")
    for fault in sorted(judged["faults"], key=lambda f: f["beat"]):
        print(f"  beat {fault['beat']}: {fault['kind']} ({fault['detail']})")
    print(len(judged["faults"]), "faults;", dict(Counter(f["kind"] for f in judged["faults"])))
    sys.exit(0)

with review() as run:
    out = run(f"""const all = [...{json.dumps(songs())}, ...tunes.slice(0, 10).map((t) => m.hummed(t))];
  const rows = [];
  for (const song of all) for (const style of ['simple', 'warm', 'bright']) {{
    try {{ const r = m.judge(song, style); rows.push({{ id: song.id, style, faults: r.faults, chords: r.chords }}); }}
    catch (e) {{ rows.push({{ id: song.id, style, error: String(e).slice(0, 200) }}); }}
  }}
  return rows;""")
if len(sys.argv) > 1:
    with open(sys.argv[1], "w") as f:
        json.dump(out, f)
total = Counter()
for r in out:
    if "error" in r:
        print("ERROR", r["id"], r["style"], r["error"])
        continue
    total.update(x["kind"] for x in r["faults"])
print(len(out), "harmonies;", dict(total))
