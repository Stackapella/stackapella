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

# One turn at a time on the shared checkout (~/Code/stackapella-courses-content): a lock like
# land-lock's, taken over only when its holder is surely gone (ps can't see it, and it's over 10
# minutes old); given back when this run ends.
SHARED_LOCK=/tmp/stackapella-content.lock
shared_turn() {
    waited=
    while ! mkdir "$SHARED_LOCK" 2> /dev/null; do
        pid=$(cat "$SHARED_LOCK/pid" 2> /dev/null || true)
        if [ -n "$pid" ] && ! ps -p "$pid" > /dev/null 2>&1 \
            && [ -n "$(find "$SHARED_LOCK" -maxdepth 0 -mmin +10 2> /dev/null)" ]; then
            rm -rf "$SHARED_LOCK"
            continue
        fi
        if [ -z "$waited" ]; then
            echo "Waiting for the content checkout ($(cat "$SHARED_LOCK/who" 2> /dev/null || echo 'another worktree'))" >&2
            waited=1
        fi
        sleep 2
    done
    echo $$ > "$SHARED_LOCK/pid"
    echo "$here" > "$SHARED_LOCK/who"
    trap 'rm -rf "$SHARED_LOCK"' EXIT
    trap 'exit 130' INT TERM
}

# A git step on the shared checkout. Someone else's git there (a person, an editor) can still hold
# its index.lock: wait for it to go and try again, a minute at most. The lock is never deleted.
shared_git() {
    dir=$1
    shift
    tries=0
    until git -C "$dir" "$@"; do
        tries=$((tries + 1))
        if [ ! -e "$dir/.git/index.lock" ] || [ $tries -gt 30 ]; then
            echo "content.sh: git $* failed in $dir$( [ -e "$dir/.git/index.lock" ] && echo ' (its index.lock is still there after a minute: is a git command running there?)')." >&2
            return 1
        fi
        sleep 2
    done
}

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
            # The shared checkout is one for every worktree: one turn at a time, from the checkout to
            # the end of the copy, so no other worktree moves it to its own pin halfway through.
            shared_turn
            test -d "$src/.git" || git clone -q "https://github.com/$repo.git" "$src"
            shared_git "$src" fetch -q origin
            shared_git "$src" checkout -q "$commit"
        fi
        place "$(cd "$src" && pwd)" "$here" link
        echo "content copied from $src$( [ -n "${CONTENT_DIR:-}" ] && echo ' (CONTENT_DIR: your working copy, not the pin)' || echo " at $commit" )."
        ;;
    *)
        sed -n '2,12p' "$0"
        exit 1
        ;;
esac
