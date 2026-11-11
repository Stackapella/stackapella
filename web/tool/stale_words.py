"""Which words of a language need translating again (docs/languages-plan.md, "Keeping current").

Each translated language keeps, next to locales/<lang>.json, the English each key was translated from
(locales/<lang>.source.json, the same shape). This lists, per language:
  new      keys in en.json with no translation yet
  changed  keys whose English has changed since they were translated (the app shows the new English until then)
  orphan   translated keys en.json no longer has (safe to drop)

  python3 tool/stale_words.py              # every language, counts and the keys
  python3 tool/stale_words.py es --json    # one language, as JSON for the weekly sweep
  python3 tool/stale_words.py es --mark    # after a drafting pass: record the English each translated key came from

Exits 0 always: English moving on is normal; the app never shows a stale sentence (i18n.js compares at run time).
"""
import json
import sys
from pathlib import Path

LOCALES = Path(__file__).resolve().parent.parent / "locales"


def flat(node, path=""):
    out = {}
    for key, value in node.items():
        full = f"{path}.{key}" if path else key
        if isinstance(value, dict):
            out.update(flat(value, full))
        elif isinstance(value, str):
            out[full] = value
    return out


def nest(pairs):
    root = {}
    for key, value in sorted(pairs.items()):
        node = root
        *parents, last = key.split(".")
        for part in parents:
            node = node.setdefault(part, {})
        node[last] = value
    return root


def report(lang, english):
    words = flat(json.loads((LOCALES / f"{lang}.json").read_text()))
    source_file = LOCALES / f"{lang}.source.json"
    source = flat(json.loads(source_file.read_text())) if source_file.exists() else {}
    return {
        "new": sorted(k for k in english if k not in words),
        "changed": sorted(k for k in english if k in words and source.get(k) != english[k]),
        "orphan": sorted(k for k in words if k not in english),
        "keys": len(english),
        "translated": sum(1 for k in english if k in words and source.get(k) == english[k]),
    }


def main(args):
    english = flat(json.loads((LOCALES / "en.json").read_text()))
    langs = [a for a in args if not a.startswith("--")] or sorted(p.stem for p in LOCALES.glob("*.json") if p.stem != "en" and not p.stem.endswith(".source"))
    for lang in langs:
        if "--mark" in args:
            words = flat(json.loads((LOCALES / f"{lang}.json").read_text()))
            (LOCALES / f"{lang}.source.json").write_text(json.dumps(nest({k: english[k] for k in words if k in english}), ensure_ascii=False, indent=4) + "\n")
            print(f"{lang}: recorded the English of {sum(1 for k in words if k in english)} keys")
            continue
        r = report(lang, english)
        if "--json" in args:
            print(json.dumps({lang: r}, ensure_ascii=False, indent=2))
            continue
        print(f"{lang}: {r['translated']} of {r['keys']} keys current; {len(r['new'])} new, {len(r['changed'])} changed, {len(r['orphan'])} orphan")
        for kind in ("changed", "new", "orphan"):
            for key in r[kind][:40]:
                print(f"  {kind:8} {key}")
            if len(r[kind]) > 40:
                print(f"  {kind:8} ... and {len(r[kind]) - 40} more")
    if not langs:
        print("No translated languages yet.")


if __name__ == "__main__":
    main(sys.argv[1:])
