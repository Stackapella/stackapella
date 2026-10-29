#!/bin/sh
# Pins a new commit of the courses repo in content.lock (docs/repos-plan.md, "A content change"):
# its tarball's sha256 worked out, then the content linked locally so the suites run against it.
# Land the change like any other: the gate runs the server and web suites on the new content.
#
#   sh server/tool/content-bump.sh <commit>
set -eu
here=$(cd "$(dirname "$0")/../.." && pwd)
commit=${1:?usage: content-bump.sh <full commit sha>}
case "$commit" in *[!0-9a-f]*|'') echo "Give the full 40-character commit."; exit 1 ;; esac
[ ${#commit} -eq 40 ] || { echo "Give the full 40-character commit."; exit 1; }
repo=$(sed -n 's/^repo=//p' "$here/.do/content.lock")
tarball=$(mktemp)
curl -fsSL --retry 3 -o "$tarball" "https://codeload.github.com/$repo/tar.gz/$commit"
sum=$(shasum -a 256 "$tarball" | cut -d' ' -f1)
rm -f "$tarball"
tmp=$(mktemp)
sed -e "s/^commit=.*/commit=$commit/" -e "s/^sha256=.*/sha256=$sum/" "$here/.do/content.lock" > "$tmp" && mv "$tmp" "$here/.do/content.lock"
echo "content.lock: $commit ($sum)."
sh "$here/server/tool/content.sh" link
