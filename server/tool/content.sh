#!/bin/sh
# The courses and songs, from their own repo (docs/repos-plan.md), at the commit content.lock pins.
#
#   sh server/tool/content.sh fetch [root]   the pinned commit's tarball from GitHub, checked
#                                            against its sha256, unpacked into root's three paths
#                                            (the Dockerfile and CI; root defaults to the repo)
#   sh server/tool/content.sh link           a local checkout copied into the three paths: the one
#                                            in CONTENT_DIR (an agent's working copy; run it again
#                                            after a change), else ~/Code/stackapella-courses-content
#                                            at the pinned commit
#
# Copies, never symlinks: the app's static file checks (WebAppController, web/tool/serve.php)
# refuse a file whose real path is outside web/, as production's copies never are.
#
# While content.lock names no commit, the content is still in this tree and both do nothing.
set -eu
here=$(cd "$(dirname "$0")/../.." && pwd)
lock="$here/.do/content.lock"
value() { sed -n "s/^$1=//p" "$lock"; }
repo=$(value repo)
commit=$(value commit)
sum=$(value sha256)

if [ -z "$commit" ]; then
    echo "content.lock pins no commit: the content is in this tree."
    exit 0
fi

# Where each part of the content repo goes.
place() {
    src=$1 root=$2 how=$3
    for pair in "course:web/course" "templates/free:web/templates" "templates/library:server/database/templates"; do
        from="$src/${pair%%:*}" to="$root/${pair#*:}"
        test -d "$from" || { echo "The content has no ${pair%%:*}/."; exit 1; }
        # Never over a path this repo still tracks (the move's steps 4 and 5): that's the tree's copy.
        if [ "$how" = link ] && git -C "$root" ls-files --error-unmatch "${pair#*:}" > /dev/null 2>&1; then
            echo "${pair#*:} is still in this tree: left as it is."
            continue
        fi
        rm -rf "$to"
        mkdir -p "$(dirname "$to")" && cp -R "$from" "$to"
    done
}

case "${1:-}" in
    fetch)
        root=${2:-$here}
        tmp=$(mktemp -d)
        trap 'rm -rf "$tmp"' EXIT
        curl -fsSL --retry 3 -o "$tmp/content.tar.gz" "https://codeload.github.com/$repo/tar.gz/$commit"
        if command -v sha256sum > /dev/null; then got=$(sha256sum "$tmp/content.tar.gz" | cut -d' ' -f1); else got=$(shasum -a 256 "$tmp/content.tar.gz" | cut -d' ' -f1); fi
        [ "$got" = "$sum" ] || { echo "content $commit: sha256 $got, content.lock says $sum"; exit 1; }
        tar -xzf "$tmp/content.tar.gz" -C "$tmp"
        place "$(find "$tmp" -mindepth 1 -maxdepth 1 -type d | head -n 1)" "$root" copy
        echo "content $commit in place."
        ;;
    link)
        src=${CONTENT_DIR:-$HOME/Code/stackapella-courses-content}
        if [ -z "${CONTENT_DIR:-}" ]; then
            test -d "$src/.git" || git clone -q "https://github.com/$repo.git" "$src"
            git -C "$src" fetch -q origin
            git -C "$src" checkout -q "$commit"
        fi
        place "$(cd "$src" && pwd)" "$here" link
        echo "content copied from $src$( [ -n "${CONTENT_DIR:-}" ] && echo ' (CONTENT_DIR: your working copy, not the pin)' || echo " at $commit" )."
        ;;
    *)
        sed -n '2,12p' "$0"
        exit 1
        ;;
esac
