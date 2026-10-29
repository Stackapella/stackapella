# Real instruments: the sample manifest

The shared format of the real-instrument sample sets (docs/decisions.md, "voices first,
instruments welcome"; docs/instrument-samples.md for the sources). The backend builds and uploads
them (`server/tool/instruments/build.py`, `php artisan app:instruments:upload`); the web's and the
native apps' sample players read them. A change to this format is a new `format` number, agreed
by the backend, the frontend and the mobile agent, and logged in [log.md](log.md).

Status: draft, from the backend (2026-10-27), for the frontend's and the mobile agent's agreement.

## Where they are

- `auth.config.instrumentsIndexUrl` (null until real instruments are on) is the index:
  `{ "instruments": [{ "name": "piano", "version": "1", "manifest": "<url>", "updatedAt": "..." }] }`,
  cached for five minutes.
- Each `manifest` URL is `.../instruments/<name>/<version>/manifest.json`. Everything under a
  version never changes (a year's cache, immutable): a fix is a new version. A player keeps what it
  downloaded by name and version, and fetches a new version only when the index names one.
- File paths in a manifest are relative to the manifest's folder.

## The manifest

```json
{
  "format": 1,
  "name": "piano",
  "version": "1",
  "title": "Grand piano",
  "kind": "pitched",
  "sampleRate": 44100,
  "channels": 2,
  "release": 0.35,
  "gain": 0.84,
  "zones": [
    { "file": "s/060-085.mp3", "frames": 264600, "encoderDelay": 1105, "onset": 0.003,
      "root": 60, "lo": 59, "hi": 62, "velLo": 43, "velHi": 85, "tune": 0 }
  ],
  "licence": "CC0-1.0",
  "credit": "Versilian Studios Chamber Library (VCSL), Grand Piano, Kawai.",
  "sources": [{ "url": "https://github.com/sgossner/VCSL", "commit": "c1ea7bc..." }],
  "files": [{ "path": "s/060-085.mp3", "bytes": 52341, "sha256": "..." }]
}
```

| Field | Meaning |
|---|---|
| `format` | 1. A player that doesn't know the number uses the synthesis instead. |
| `name`, `version` | As in the index. `name` is `^[a-z][a-z0-9-]{1,39}$`. |
| `title` | What Settings shows ("Grand piano"). |
| `kind` | `pitched` (zones by key) or `drums` (zones by piece). |
| `sampleRate`, `channels` | What every file decodes to (44100; 1 or 2). |
| `release` | Seconds of fade when a pitched note is let go (drums ring out). |
| `gain` | Optional, linear (default 1): multiply every zone by it to level the sets against each other and the voices. The build sets it so a typical note has the same loudness in every set: the zone nearest middle C at its loudest layer at -26 dBFS RMS over its first second, or a kit's snare, brush tap or clap at -24 dBFS RMS over its first 100 ms. It's never above 1 (the set's loudest sample is at -1 dBFS), so a set that's quiet even then is quieter. |
| `zones` | The samples, below. |
| `licence`, `credit`, `sources` | Provenance. All sets are CC0: nothing must be shown, but the credits page lists `credit`. |
| `files` | Every file with its size and SHA-256 (the uploader checks them; a player may too). |

### A zone

Every zone has:
- `file`: an MP3 (MPEG-1 Layer III, constant bitrate, with a Xing/Info header).
- `frames`: the sample's length at `sampleRate`, before encoding.
- `encoderDelay`: how many frames of priming come before the sound in a decode that keeps them
  (the encoder's delay plus the decoder's 529).
- `onset`: seconds from the sample's start (after the delay is skipped) to its attack. It's about
  0.003: the build trims each sample to start just before its attack.
- `velLo`, `velHi`: the MIDI velocities (1 to 127) it's for. A piece's or key's zones cover 1 to
  127 without gaps.

A `pitched` zone also has `root` (the MIDI note it was sampled at), `lo` and `hi` (the keys it
plays, inclusive; together the zones cover 0 to 127 without gaps) and `tune` (cents to add).

A `drums` zone also has `piece` (below), `key` (its General MIDI note, or null) and `rr` (round
robin, 1 upwards).

### Pieces

`kick`, `snare`, `rim` (cross-stick), `hat-closed`, `hat-pedal`, `hat-open`, `ride`, `crash`,
`tom-low`, `tom-high`, `tambourine`, `shaker`, `cowbell`, `clap`, `snap`, `stomp`,
`brush-tap`, `brush-sweep`. A kit has some of them; a player asks for a piece by name and plays
nothing (or its synthesis) when the kit hasn't got it. New names may be added without a new
`format`; a player ignores names it doesn't know.

## Playing a sample

1. **Choose the zone.** Pitched: the zone with `lo <= note <= hi` and `velLo <= velocity <= velHi`.
   Drums: the piece's zones for the velocity, taking their `rr` in turn (1, 2, ..., 1).
2. **Find where the sound starts in your decode.** Decoders differ: some keep the encoder's
   priming, some trim it (browsers differ by engine and version). After decoding, compare the
   decoded length with `frames`: if it's at least `frames + encoderDelay / 2`, the priming is there
   and playback starts `encoderDelay` frames in; if not, it starts at frame 0. (Half the delay is
   the line because the two cases are a whole delay apart, and a decoder that keeps the priming may
   still drop a partial last frame.) For example, MediaCodec and minimp3 keep it; AVAudioFile trims
   it. **A decoder that resamples** (the web's `decodeAudioData` decodes to the `AudioContext`'s
   rate, often 48000) scales both by `rate / sampleRate`: compare the decoded length with
   `(frames + encoderDelay / 2) * rate / sampleRate`, and start `encoderDelay / sampleRate`
   seconds in. (In seconds it's the same rule at any rate.)
3. **Line the attack up with the beat.** To have the attack at time `t` on the audio clock (the
   `AudioContext` on the web), start the source at `t - onset` (from the position in step 2). This
   is what keeps drums from flamming against the vocals. Never schedule with timers.
4. **Pitch.** Pitched: playback rate `2 ^ ((note - root) / 12 + tune / 1200)`. Drums play at rate 1.
5. **Level.** Multiply by the set's `gain`. Velocity picks the layer; within a layer, scale the
   gain by `(velocity / velHi) ^ 1.5` or so, to smooth between layers. The build keeps the layers' own balance (one gain for the
   whole set, its loudest sample at -1 dBFS).
6. **Release.** Pitched: on note-off, ramp the gain to 0 over `release` seconds, then stop.
   Drums: let the sample end (a closed or pedal hat may cut an open one: `hat-closed` and
   `hat-pedal` end a sounding `hat-open` within 50 ms).

A sample is at most a few seconds long, faded to silence at its end, and not looped: a note held
longer than its sample fades out with it.

## Sizes

About 2 to 6 MB per instrument (mono 128 kbps, stereo 160 kbps). A player downloads an instrument
only when a song uses it, and can start before all zones are in: the zone nearest the note, at
the nearest velocity, is a fine stand-in while the rest load.

---

[Back: the API contract](api.md)
