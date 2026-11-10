#!/bin/sh
# Pins a new commit of the courses repo in content.lock (docs/repos-plan.md, "A content change"):
# its tarball's sha256 worked out, then the content linked locally so the suites run against it.
# Land the change like any other: the gate runs the server and web suites on the new content.
#
# First it refuses a pin production's sync would refuse (sakura-sakura, 2026-11-09): a song whose
# notes changed since the pinned commit under the same version number (app:templates:versions).
#
#   sh server/tool/content-bump.sh <commit>
set -eu
here=$(cd "$(dirname "$0")/../.." && pwd)
commit=${1:?usage: content-bump.sh <full commit sha>}
case "$commit" in *[!0-9a-f]*|'') echo "Give the full 40-character commit."; exit 1 ;; esac
[ ${#commit} -eq 40 ] || { echo "Give the full 40-character commit."; exit 1; }
lock="$here/.do/content.lock"
repo=$(sed -n 's/^repo=//p' "$lock")
pinned=$(sed -n 's/^commit=//p' "$lock")
pinned_sum=$(sed -n 's/^sha256=//p' "$lock")
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
trap 'exit 130' INT TERM

# One commit's tarball, saved as $work/<name>.tar.gz and unpacked into $work/<name>-tree.
get() {
    curl -fsSL --retry 3 -o "$work/$1.tar.gz" "https://codeload.github.com/$repo/tar.gz/$2"
    mkdir "$work/$1-tree"
    tar -xzf "$work/$1.tar.gz" -C "$work/$1-tree" --strip-components=1
}

get new "$commit"
sum=$(shasum -a 256 "$work/new.tar.gz" | cut -d' ' -f1)

if [ -n "$pinned" ]; then
    get old "$pinned"
    [ "$(shasum -a 256 "$work/old.tar.gz" | cut -d' ' -f1)" = "$pinned_sum" ] \
        || { echo "The pinned commit's tarball doesn't match content.lock's sha256: not pinned."; exit 1; }
    (cd "$here/server" && php artisan app:templates:versions "$work/old-tree" "$work/new-tree") \
        || { echo "Not pinned: production's sync would refuse these songs. Give each a new version in the content repo, then pin that commit."; exit 1; }
fi

tmp=$(mktemp)
sed -e "s/^commit=.*/commit=$commit/" -e "s/^sha256=.*/sha256=$sum/" "$lock" > "$tmp" && mv "$tmp" "$lock"
echo "content.lock: $commit ($sum)."
sh "$here/server/tool/content.sh" link
