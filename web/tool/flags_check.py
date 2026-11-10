"""Every switch the server defines (server/config/flags.php) has a label and a one-line hint in
Admin > Settings > Switches (js/admin/safety.js's FLAGS and FLAG_HINTS), so none shows as "a switch
this app doesn't know yet" (Hillel saw most of them that way, 2026-11-02). Run by tool/test.sh."""
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parent.parent
FLAGS_PHP = WEB.parent / "server" / "config" / "flags.php"
SAFETY = WEB / "js" / "admin" / "safety.js"


def keys_of(source, name):
    """The keys of `const <name> = { ... };` in a JS module."""
    match = re.search(rf"const {name} = \{{(.*?)\n\}};", source, re.S)
    return set(re.findall(r"^\s*([a-z_]+):", match.group(1), re.M)) if match else set()


def main():
    defined = set(re.findall(r"^\s*'([a-z_]+)' => \['kind'", FLAGS_PHP.read_text(), re.M))
    source = SAFETY.read_text()
    labels, hints = keys_of(source, "FLAGS"), keys_of(source, "FLAG_HINTS")
    problems = [f"js/admin/safety.js: switch '{flag}' has no label in FLAGS" for flag in sorted(defined - labels)]
    problems += [f"js/admin/safety.js: switch '{flag}' has no hint in FLAG_HINTS" for flag in sorted(defined - hints)]
    for problem in problems:
        print(problem)
    print(f"flags: {len(defined)} switches, {len(problems)} problems")
    sys.exit(1 if problems or not defined else 0)


if __name__ == "__main__":
    main()
