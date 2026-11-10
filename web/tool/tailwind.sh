#!/usr/bin/env bash
# Builds the stylesheets from css/input.css with Tailwind's standalone binary: no npm. css/site.css is
# the public pages' (all of it but the course pages' .cp-* rules), css/courses.css those rules alone,
# for the pages that show them, and css/app.css leaves out every rule only the site can use (rules that
# need a .s-* or .cp-* class: tool/app_css.py), for the app and the server's own pages. The binary is downloaded once, at
# the pinned version (with retries, as GitHub's release downloads sometimes answer 504), and refused unless its sha256
# matches (the same pins as iaw): checked on every run, so one restored from CI's cache, or left in bin/, is checked too.
#
#   tool/tailwind.sh           # build (minified)
#   tool/tailwind.sh --watch   # rebuild on every change
set -euo pipefail
cd "$(dirname "$0")/.."

VERSION=4.3.3
case "$(uname -s)-$(uname -m)" in
  Darwin-arm64) ASSET=tailwindcss-macos-arm64; SHA=cdf646702987a743464dff4d9c60fd4480d1c1e73dd819a9a67f1078815dce9d ;;
  Darwin-x86_64) ASSET=tailwindcss-macos-x64; SHA=7922e0953f2110c05976e3bf58f14e643d90427575e766b7d433f5f80cbee7e1 ;;
  Linux-x86_64) ASSET=tailwindcss-linux-x64; SHA=dc61b3ac6b8c9ca874c0cc4c57b2409791a64c5540404ca5f5367360babc313a ;;
  Linux-aarch64) ASSET=tailwindcss-linux-arm64; SHA=55fd0b241214eff3de1e8ee4f22796662f2d2e7a49bcfca7477cfd0bac398195 ;;
  *) echo "No pinned Tailwind binary for $(uname -s)-$(uname -m)" >&2; exit 1 ;;
esac

sha256_of() {
  if command -v sha256sum > /dev/null; then
    sha256sum "$1" | cut -d' ' -f1
  else
    shasum -a 256 "$1" | cut -d' ' -f1
  fi
}

BIN="bin/tailwindcss-$VERSION"
if [[ ! -x "$BIN" ]]; then
  mkdir -p bin
  curl -sSfL --retry 5 --retry-all-errors --retry-delay 5 -o "$BIN.download" "https://github.com/tailwindlabs/tailwindcss/releases/download/v$VERSION/$ASSET"
  if [[ "$(sha256_of "$BIN.download")" != "$SHA" ]]; then
    rm -f "$BIN.download"
    echo "Tailwind $VERSION checksum mismatch: refusing to run it" >&2
    exit 1
  fi
  mv "$BIN.download" "$BIN"
  chmod +x "$BIN"
elif [[ "$(sha256_of "$BIN")" != "$SHA" ]]; then
  echo "$BIN isn't the pinned Tailwind $VERSION (sha256 mismatch): refusing to run it. Delete it to download it again." >&2
  exit 1
fi

if [[ "${1:-}" == "--watch" ]]; then
  # While editing: both from all of input.css, so a change shows on either side at once (app.css a
  # superset until the next plain build).
  "$BIN" -i css/input.css -o css/app.css --watch &
  trap 'kill $! 2> /dev/null || true' EXIT
  exec "$BIN" -i css/input.css -o css/site.css --watch
fi
APP_INPUT=css/.app-input.css
APP_GAME_INPUT=css/.app-game-input.css
SITE_INPUT=css/.site-input.css
SITE_GAME_INPUT=css/.site-game-input.css
COURSES_INPUT=css/.courses-input.css
trap 'rm -f "$APP_INPUT" "$APP_GAME_INPUT" "$SITE_INPUT" "$SITE_GAME_INPUT" "$COURSES_INPUT"' EXIT
# The public pages: site.css without the course pages' rules (.cp-*), which are courses.css, linked only by a page
# that shows one (server/resources/views/public/layout.blade.php). Each of site.css and app.css without its game skin's
# rules, which are site-game.css and app-game.css, linked only in game mode, right after it (js/game-css.js).
python3 -I tool/app_css.py --site css/input.css "$SITE_INPUT" "$COURSES_INPUT" "$SITE_GAME_INPUT"
"$BIN" -i "$SITE_INPUT" -o css/site.css --minify
"$BIN" -i "$SITE_GAME_INPUT" -o css/site-game.css --minify
"$BIN" -i "$COURSES_INPUT" -o css/courses.css --minify
python3 -I tool/app_css.py css/input.css "$APP_INPUT" "$APP_GAME_INPUT"
"$BIN" -i "$APP_INPUT" -o css/app.css --minify
"$BIN" -i "$APP_GAME_INPUT" -o css/app-game.css --minify
