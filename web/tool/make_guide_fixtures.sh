#!/usr/bin/env bash
# Makes the recorded-guide test fixtures in tests/fixtures/: a master WAV (chirps at uneven gaps over
# a hum, 3 s, mono 16-bit) at 48 and 44.1 kHz, and each encoded to AAC in an MP4 exactly as the
# server publishes a guide (server/app/Services/Audio/Ffmpeg.php: aac 128k, faststart, with the
# edit list that hides the encoder's priming). tests/guides.test.js checks a decoded guide lines up
# with its master to the sample. Needs python3 and ffmpeg; run it only to remake the fixtures.
set -euo pipefail
cd "$(dirname "$0")/.."
for rate in 48000 44100; do
  python3 -I - "$rate" "tests/fixtures/guide-$rate.wav" <<'PY'
import math, struct, sys, wave
rate, path = int(sys.argv[1]), sys.argv[2]
frames = 3 * rate
samples = [0.08 * math.sin(2 * math.pi * 196 * i / rate) for i in range(frames)]
gaps = [0.31, 0.27, 0.35, 0.29, 0.33, 0.26, 0.37]
at = int(0.2 * rate)
for k in range(8):
    n = int(0.01 * rate)
    for i in range(n):
        t = i / rate
        window = 0.5 - 0.5 * math.cos(2 * math.pi * i / (n - 1))
        samples[at + i] += 0.8 * window * math.sin(2 * math.pi * (1000 * t + 0.5 * 700000 * t * t))
    at += int(gaps[k % len(gaps)] * rate)
with wave.open(path, "wb") as w:
    w.setnchannels(1); w.setsampwidth(2); w.setframerate(rate)
    w.writeframes(b"".join(struct.pack("<h", max(-32768, min(32767, round(s * 32767)))) for s in samples))
PY
  ffmpeg -hide_banner -nostats -loglevel error -y -i "tests/fixtures/guide-$rate.wav" -c:a aac -b:a 128k -ar "$rate" -ac 1 -movflags +faststart -f mp4 "tests/fixtures/guide-$rate.m4a"
done
ls -l tests/fixtures/
