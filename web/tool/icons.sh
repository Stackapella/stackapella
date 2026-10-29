#!/usr/bin/env bash
# Renders the app icon's PNGs from web/icons/icon.svg (tool/icons.py writes it) in headless Chrome:
# icon-192, icon-512, icon-maskable-512 (the same full-bleed square; the bars sit in the safe zone)
# and apple-touch-icon (180), then the server's favicon.ico (16, 32 and 48) from icon-512 with Pillow.
# No npm; tool/cdp.py drives Chrome.
set -euo pipefail
cd "$(dirname "$0")/.."
python3 -I tool/icons.py
PORT="${PORT:-8199}"
php -S "127.0.0.1:$PORT" tool/serve.php > /dev/null 2>&1 &
SERVER=$!
trap 'kill $SERVER 2> /dev/null || true' EXIT
sleep 0.5
for spec in "icon-192:192" "icon-512:512" "icon-maskable-512:512" "apple-touch-icon:180"; do
  name="${spec%%:*}"; size="${spec##*:}"
  python3 -I tool/cdp.py shot "http://127.0.0.1:$PORT/icons/icon.svg" "icons/$name.png" --size "${size}x${size}" > /dev/null
done
python3 -c "from PIL import Image; Image.open('icons/icon-512.png').convert('RGBA').save('../server/public/favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])"
echo "icons rendered"
