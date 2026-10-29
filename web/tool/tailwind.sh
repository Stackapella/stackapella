#!/usr/bin/env bash
# Builds css/app.css with Tailwind's standalone binary: no npm. The binary is downloaded once, at
# the pinned version, and refused unless its sha256 matches (the same pins as iaw).
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

BIN="bin/tailwindcss-$VERSION"
if [[ ! -x "$BIN" ]]; then
  mkdir -p bin
  curl -sSfL -o "$BIN.download" "https://github.com/tailwindlabs/tailwindcss/releases/download/v$VERSION/$ASSET"
  if command -v sha256sum > /dev/null; then
    ACTUAL=$(sha256sum "$BIN.download" | cut -d' ' -f1)
  else
    ACTUAL=$(shasum -a 256 "$BIN.download" | cut -d' ' -f1)
  fi
  if [[ "$ACTUAL" != "$SHA" ]]; then
    rm -f "$BIN.download"
    echo "Tailwind $VERSION checksum mismatch: refusing to run it" >&2
    exit 1
  fi
  mv "$BIN.download" "$BIN"
  chmod +x "$BIN"
fi

if [[ "${1:-}" == "--watch" ]]; then
  exec "$BIN" -i css/input.css -o css/app.css --watch
fi
"$BIN" -i css/input.css -o css/app.css --minify
