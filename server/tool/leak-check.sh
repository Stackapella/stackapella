#!/bin/sh
# Nothing unshareable in the repo, ever (docs/open-source-plan.md, 1.5): run before each push, and
# on launch day. Exits non-zero on anything found. Needs gitleaks (brew install gitleaks).
#
#   sh server/tool/leak-check.sh
#
# 1. gitleaks over the whole history, every branch and stash (.gitleaks.toml's rules);
# 2. no file that must never be committed, anywhere in the history (keys, keystores, env files,
#    store configs, data dumps);
# 3. gitleaks over the tree as it would be published (what's tracked, nothing ignored);
# 4. no made-up people on real consumer domains in the fixtures (example.com, .net and .org
#    instead; the word list's test of a real domain excepted).
set -eu
repo=$(git rev-parse --show-toplevel)
cd "$repo"
failed=0

version=$(gitleaks version 2>/dev/null | sed 's/^v//')
major=$(printf '%s' "$version" | cut -d. -f1); minor=$(printf '%s' "$version" | cut -d. -f2)
if [ -z "$major" ] || [ "$major" -lt 8 ] || { [ "$major" -eq 8 ] && [ "${minor:-0}" -lt 25 ]; }; then
    echo "gitleaks ${version:-missing} is too old: 8.25 or later (.gitleaks.toml's allowlist needs it)." >&2
    exit 1
fi

echo "1. Secrets in the history"
gitleaks git . --redact --no-banner --log-opts="--all" --exit-code 1 || failed=1

echo "2. Files that must never be committed"
bad=$(git log --all --name-only --format= | sort -u \
    | grep -iE '(^|/)\.env($|\.)|\.env$|\.pem$|\.key$|\.p8$|\.p12$|\.pfx$|\.jks$|keystore|key\.properties|google-services\.json|googleservice-info\.plist|\.mobileprovision$|\.csv$|\.sql$|\.sqlite$|\.dump$|(^|/)id_(rsa|ed25519)' \
    | grep -vE '(^|/)\.env\.example$|^server/resources/certs/[^/]+\.pem$' || true)
if [ -n "$bad" ]; then
    echo "$bad"
    failed=1
fi

echo "3. Secrets in the tree as published"
tree=$(mktemp -d)
git archive HEAD | tar -x -C "$tree"
(cd "$tree" && gitleaks dir . --config "$repo/.gitleaks.toml" --redact --no-banner --exit-code 1) || failed=1
rm -rf "$tree"

echo "4. Real-looking addresses in fixtures"
people=$(git grep -nIiE '[a-z0-9._%+-]+@(gmail|googlemail|yahoo|aol|hotmail|outlook|icloud|live|msn)\.(com|net|org|co\.uk|fr|de)([^a-z.]|$)' -- 'server/tests' 'contract/examples' 'web/tests' 'test' ':!server/tests/Unit/WordListsTest.php' || true)
if [ -n "$people" ]; then
    echo "$people"
    failed=1
fi

if [ "$failed" = 0 ]; then
    echo "Nothing unshareable found."
else
    echo "Found something above: nothing is pushed until it's explained or removed." >&2
fi

exit "$failed"
