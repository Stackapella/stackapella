"""A look at one fault: the four parts and the chord, every half beat from `from` to `to`, for a song
(its id) or a hummed tune (hum:<id>, from tests/fixtures/hum-tunes.json) in one style.

    python3 -I web/tool/harmony-review/probe.py loch-lomond warm 0 8
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))  # python3 -I leaves it out
from common import review, song  # noqa: E402

name, style, start, end = sys.argv[1], sys.argv[2], float(sys.argv[3]), float(sys.argv[4])
hummed = name.startswith("hum:")
with review() as run:
    pick = f"m.hummed(tunes.find((t) => t.id === {json.dumps(name[4:])}))" if hummed else json.dumps(song(name))
    out = run(f"return m.probe({pick}, {json.dumps(style)}, {start}, {end});")
print(out["key"], out["ts"], out["pickup"])
for row in out["rows"]:
    print(*row)
