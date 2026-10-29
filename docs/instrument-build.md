# Building the real instruments

**Status (2026-10-27): built by the backend agent.** How the CC0 sample sets in
[instrument-samples.md](instrument-samples.md) become what the players download, in the format of
[contract/instruments.md](../contract/instruments.md).

## The pieces

- `server/tool/instruments/sets.json`: every instrument, its source pinned to a commit (GitHub) or
  a SHA-256 (an archive), what to keep, and its version. A change to what's built is a new version.
- `server/tool/instruments/build.py`: the build (Python 3 and ffmpeg with libmp3lame; no npm, no
  pip packages). `sfz.py` beside it reads the SFZ programs.
- `php artisan app:instruments:upload <name> <version> <folder>`: checks the folder (the format,
  every file's size and SHA-256, the name and version) and uploads it to the media CDN, then
  points `instruments/index.json` at it. `--check` checks only.

## Running it

```sh
cd server
python3 -I tool/instruments/build.py list
python3 -I tool/instruments/build.py build piano      # or: build all
php artisan app:instruments:upload piano 1 ~/Library/Caches/stackapella-samples/build/piano/1 --check
php artisan app:instruments:upload piano 1 ~/Library/Caches/stackapella-samples/build/piano/1
```

Everything downloaded and built lives in the cache (`$STACKAPELLA_SAMPLES_CACHE`, else
`~/Library/Caches/stackapella-samples`), never in the repo: the build refuses an `--out` inside
it. Only the files a build uses are fetched: GitHub's file list once per pinned commit, then each
sample it keeps, raw. FreePats' archives are a few MB each and checked by their SHA-256. A first
`build all` fetches a few hundred MB (most of it the drum kits' mics); later builds use the cache.
Uploading needs the media disk's keys (production's, so it's for whoever deploys).

## What a build does

1. **Reads the regions**: from an SFZ program (with its `#include`s and `#define`s; the regions
   that sound at the program's default controllers), or from file names (VCSL and VSCO 2 CE ship
   only WAVs named by note and layer).
2. **Keeps a few**: every 3rd or 4th sampled semitone (`step`), 1 to 3 velocity layers spread from
   softest to loudest (`layers`), one take per note (drums keep 2 or 3 round robins). Each kept
   note covers the keys halfway to its neighbours; the layers split velocities 1 to 127 evenly.
3. **Renders each zone**: a drum hit sums its mics (close mics, overheads and rooms, at the
   program's levels: its `amplitude_onccN` and `volume_onccN` at its default controllers, through
   its curves, so a part they turn all the way down isn't mixed; envelope, tuning and pan
   controllers aren't applied, and the build lists them); a piece can name the controllers it's
   played at (`{"key": 46, "cc": {"4": 127}}`: the open hi-hat, pedal up); every sample starts 3 ms before its attack, lasts at most `seconds`, and fades out over
   its last `fade`. One gain for the whole set puts its loudest sample at -1 dBFS, so the layers
   keep their balance.
4. **Encodes** MP3: 128 kbps mono, 160 kbps stereo (piano and kits).
5. **Measures**: each file's encoder delay (the Xing/LAME header's figure plus the decoder's 529,
   checked against where the attack lands in a decode that keeps the priming, within 64 samples);
   its onset; the set's `gain` (a typical note at -20 dBFS RMS). For pitched sets, each sample's
   pitch against its root (YIN, below C6): a sample more than 60 cents out, or a whole set an
   octave out (a file-naming convention misread), stops the build.
6. **Writes** `manifest.json` (contract/instruments.md, format 1) and `LICENSES.txt` (each source,
   its URL and commit or SHA-256, and the credit).

## The sets

| Name | From | Notes |
|---|---|---|
| `piano` | VCSL Grand Piano, Kawai | Every 4th semitone, A1 to C7, 3 layers, stereo. VCSL names middle C "C3". |
| `electric-piano` | VCSL TX81Z FM Piano | The backup for jRhodes until Jeff Learman replies. |
| `nylon-guitar` | FreePats Spanish classical guitar | The backup for the Martin HD28 until Jeff Learman replies. |
| `ukulele` | FreePats Ukulele (its GitHub repo) | The 7z release uses a filter macOS's tar can't read; the repo has the same files. |
| `upright-bass` | Karoryfer Meatbass, pizzicato | Sampled every 3rd semitone already. |
| `electric-bass` | Karoryfer Swagbass | Notes only (not the fret noises). |
| `organ` | FreePats Drawbar Organ Emulation | Long notes, not looped (contract: no loops). |
| `strings` | VSCO 2 CE cello, viola and violin sections | One layer, long notes, for pads. |
| `drums` | Virtuosity Drums, basic kit | 13 pieces, 3 layers, 2 round robins, all mics summed. Version 2: offsets cut at the samples' 48 kHz. |
| `brushes` | Karoryfer Swirly Drums, basic kit | Brush taps and sweeps. Version 2: the open hat open, muted parts left out. |
| `body-percussion` | Karoryfer body_percussion, VCSL Shaker | Clap, snap, stomp and shaker. |

When Jeff Learman confirms CC0 for jRhodes and the Martin HD28, they're added as new sets
(`electric-piano` version 2 from jRhodes, `guitar` from the Martin), and the backups stay.

---

[Back: The docs guide](../README.md#a-guide-to-the-docs)
