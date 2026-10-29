# Audio feasibility spike

**Question:** can Flutter web plus the browser's Web Audio API record a new vocal part while
earlier parts play, with every part lined up to the sample, in Chrome, Safari (macOS and iOS) and
Firefox?

**Status:** The automated half is done. In Chrome, the engine's timing is exact to the sample.
The real-device half needs a person and a microphone; see [Your runs](#your-runs). It's
milestone M0 in [roadmap.md](roadmap.md), before the beta singers record on their phones.

The prototype is in `web/spike_audio/` and is deleted before the MVP. README has the commands
under "Running the audio spike".

## How it works

| Part | File | Job |
|---|---|---|
| Engine | `web/engine/engine.js` | Owns the `AudioContext`. Schedules every sound in frames on the audio clock (`AudioBufferSourceNode.start(frame / sampleRate)`). No timers. |
| Recorder | `web/engine/recorder-worklet.js` | An `AudioWorkletProcessor` that copies exactly frames A to B of its input, using the `currentFrame` stamp on each render block. |
| Bridge | `lib/engine_bridge.dart` | `dart:js_interop` extension types over `window.stackEngine`. Audio crosses as `Float32List`. |
| DSP | `lib/dsp.dart` | Makes every signal (chirps, clicks, test parts). Finds chirps again by FFT cross-correlation with a parabolic sub-sample peak. Measures drift and tap timing. |
| Controller and screen | `lib/spike_controller.dart`, `lib/main.dart` | A `ChangeNotifier` and one screen with the steps and a JSON results log. |

**Why the parts line up by construction:**
1. All tracks start on the same frame S.
2. Sound that leaves the speakers at frame F reaches the recorder at F plus the round trip R.
3. A take therefore records frames S + R to S + R + length.
4. R is measured once (calibration), so no part of the timing depends on when JavaScript or Dart
   happens to run.

**Calibration:**
- The chirps are spaced at uneven gaps (0.26 to 0.37 s), so a delay longer than a gap (Bluetooth)
  can't be mistaken for a shorter one.
- Each run plays 8 chirps and records them.
- Five runs give a median and a spread.
- The result is saved per mic and sample rate in `localStorage`.

## Automated results (headless Chrome 155, macOS, 2026-10-25)

`tool/selftest.sh` runs the self-test in headless Chrome with the audio muted. In loopback mode the
played audio goes straight into the recorder (no speaker or mic), so every offset must come out at
exactly zero.

| Check | JS build (canvaskit) | Wasm build (skwasm) |
|---|---|---|
| Calibration finds a zero round trip (8 of 8 chirps) | 0 frames | 0 frames |
| Compensation moves the capture by exactly the latency (300 frames) | -300.000 | -300.000 |
| A take over two parts (one offset +101 frames at half gain) equals their mix | max diff 0.0 | max diff 0.0 |
| The same with no count-in and a part offset -2400 frames | max diff 0.0 | max diff 0.0 |
| No drift over 10 s (20 of 20 chirps) | 0.000 ms/min | 0.000 ms/min |
| Mic opens with echo cancellation, noise suppression and AGC all off | yes (fake mic) | yes (fake mic) |
| Runs with COOP/COEP (cross-origin isolated) | yes | yes |
| Runs without COOP/COEP | yes | yes, single-threaded skwasm |

`flutter test`: 13 tests cover the FFT, calibration through a simulated noisy room with an echo,
a 400 ms delay longer than every chirp gap, a missing chirp, silence, a negative residual, 100 ppm
clock drift (6 ms a minute) and tap matching.

**What this proves:** the scheduling, capture window, trimming, offsets and mixing are exact to
the sample in Chrome, in both Flutter renderers.

**What it can't prove:** real speaker-to-mic latency, the iOS audio route, Safari and Firefox
behaviour, and dropouts under load. Those need the runs below.

**Notes from the automated runs:**
- Chrome reports `baseLatency` 5.3 ms. `outputLatency` is 0 in headless (no real device). Chrome
  also exposes `AudioContext.playbackStats` (underruns), and the spike logs it before and after
  each take.
- The multi-threaded skwasm build stalls when the server handles one request at a time: its worker
  never loads. With `PHP_CLI_SERVER_WORKERS=4` it loads. Real hosting (App Platform's static
  site, a CDN) serves requests in parallel, so this only matters for local tools.
- One early run of the release JS build failed with "Null check operator used on a null value" and
  did not happen again in the next runs. Repeat-run results: see [Open items](#open-items).

## Your runs

Run these on each device. When you're done, tap **Copy results as JSON** and paste the result back
to me. I'll turn it into the table below.

**Before you start:**
1. Start the server (README, "Running the audio spike").
2. Open the page.
3. Tap **Unlock audio**, then **Open mic**, and allow the mic.
4. Run the steps below in order.

**The steps:**

| Step | Do this | It answers |
|---|---|---|
| A | Headphones off, volume up, quiet room. Tap **Speakers**. | Speaker round trip, and how steady it is across 5 runs |
| B | Tap **Check compensation**. | Residual after compensation. Pass: within 1 ms. |
| C | Hold a wired earbud or earcup against the mic. Tap **Wired earcup**. | Headphone round trip |
| D | The same with Bluetooth headphones. Tap **Bluetooth earcup**. | Bluetooth delay, and how much it varies |
| E | Headphones on. Tap **Tap along** and tap on each of the 16 clicks. | Whether tap-along is a usable fallback |
| F | **Add test part** twice. Headphones on, **Record a take** and sing along. Then **Play all**. Try the nudge slider. | It sounds lined up. No clicks or gaps (`emptyQuanta` 0). |
| G | Headphones off. **Drift: 3 minutes**. | Clock drift. Pass: under 1 ms a minute. |
| H | (Optional) Repeat F with a USB mic, if you have one. | Drift and latency with separate input and output clocks |

**Devices:**
- Chrome, macOS: this Mac.
- Safari, macOS: this Mac.
- Firefox, macOS: this Mac.
- Safari, iOS: over the HTTPS tunnel (README).
- Chrome, Android: optional.

**On iOS, also note:**
- Whether audio plays with the silent switch on.
- Whether playback moves to the earpiece or gets quieter when the mic opens.
- Whether the sample rate changes after **Open mic** (shown in the status card).
- Whether audio survives locking the screen or a phone call (look at the `stateLog` in the
  results for `interrupted`).

## Results by browser

To fill in from your runs. Times are in ms.

| Browser | Sample rate | Base + output (reported) | Speaker round trip (median, spread) | Residual after compensation | Wired | Bluetooth | Tap offset | Drift (ms/min) | Constraints honoured | Issues |
|---|---|---|---|---|---|---|---|---|---|---|
| Chrome macOS (Scarlett 18i20) | 48000 | 14.3 + 2.7 input | 57.13 (0.030) | **0.008 (pass)** | | | | **0.004, no jumps (pass)** | yes (opened stereo) | one 29 ms underrun in 258 s |
| Chrome macOS (iPhone mic) | 48000 | 17.3 + 2.7 input | 146.44 (0.011) | **+43.6 (fail)** | | | | 0.002 + 1 jump (-2.66 ms) | yes | built-in mic recorded silence; iPhone mic delay moved ~44 ms within a minute |
| Safari macOS | | | | | | | | | | |
| Firefox macOS | | | | | | | | | | |
| Safari iOS | | | | | | | | | | |

## Run log

### 2026-10-25, Chrome 152, MacBook Pro

**Built-in mic, three runs: pure digital silence.** Every sample was exactly 0 (peak -inf dB),
although `getUserMedia` succeeded, the track was live and not muted, and the constraints were
honoured (echo cancellation, noise suppression and AGC off, 48 kHz). The Mac's chosen input was
the iPhone (Continuity). Chrome had opened the built-in mic, which hears nothing in this setup.

Lessons for the MVP:
- Always show a mic picker and a live input meter.
- Spot a silent mic (all zeros) and say so plainly: "We can't hear your mic. Pick another one or
  check its settings."
- Never trust "the mic opened" to mean "the mic hears".

**Speakers to the iPhone Continuity mic** (mics offered: built-in, iPhone, Scarlett 18i20, and a
"Default" entry pointing at the iPhone):

| Measure | Result |
|---|---|
| Round trip (median of 5 runs) | **146.44 ms** |
| Spread within a run (8 chirps) | 0.004 to 0.008 ms |
| Spread across the 5 runs | 0.011 ms (about half a sample at 48 kHz) |
| Chirps found | 8 of 8 in every run |
| Browser's own estimate (base + output + input) | 20.00 ms: off by 126 ms |
| Recording level | peak -19 dB, RMS -45 dB |
| Match | prominence 65 to 70; per-chirp scores 0.39 to 0.49 (threshold 0.3) |

**Check compensation, 55 s later: fail.** The chirps landed **43.6 ms late** (8 of 8 found, scores
0.41 to 0.46), so the round trip had grown from 146.4 ms to about 190.1 ms. The chirps within that
one run also spread by 0.228 ms, against 0.006 ms during calibration, so the delay was still moving.
The self-test rules out the compensation maths: it shifts the capture by exactly the latency.
The likely cause is the Continuity link, whose buffering changes over time. Next: the 3-minute
drift test on the iPhone mic, and calibrate plus check on the wired Scarlett 18i20 to compare.

**Drift, 3 minutes, iPhone mic:** 180 of 180 chirps found. Chirp by chirp
(`tool/analyze_recording_test.dart` on the saved WAV):
- The delay held at 42.67 ms over the calibration (about 189.1 ms in all) for 147 s, within
  plus or minus 0.01 ms.
- At 148 s it jumped once, by -2.663 ms, and then held again at 40.02 ms.
- With the jump taken out, the steady drift is **0.002 ms a minute**.
- The first fit read -0.78 ms a minute, but that was a straight line through the jump. The drift
  analysis now reports jumps separately.

So the iPhone and Mac clocks stay locked, and the wireless link **re-buffers in sudden jumps**:
+43.6 ms once (between calibrating and checking), then -2.7 ms once.

Conclusions so far:
1. The measurement is precise and repeatable to a fraction of a sample.
2. The browser's reported latency is useless for a wireless mic, so measured calibration is
   required.
3. The per-chirp scores leave a modest margin over the threshold. Tune the detector on the saved
   WAVs before the MVP.
4. No clock drift on the iPhone mic (0.002 ms a minute), but its delay jumps without warning, by
   tens of ms at a time. A one-time calibration can't be trusted with a wireless mic.
   Product options (Hillel to decide once the wired results are in):
   - re-measure just before each take;
   - recommend wired and warn on wireless;
   - offer a quick "re-check timing" button.

**Speakers to the Scarlett 18i20 (wired USB interface, mic on input 1):** passes everything.

| Measure | Result |
|---|---|
| Round trip (median of 5 runs) | **57.13 ms** |
| Spread within a run | 0.000 to 0.005 ms |
| Spread across runs | 0.030 ms (the first run read 57.161, the rest 57.132 or 57.133) |
| Check compensation | **residual 0.008 ms: pass** |
| Drift, 3 minutes | **0.004 ms a minute, no jumps, 180 of 180 chirps: pass** |
| Browser's estimate | 19 ms: off by 38 ms (speaker processing and distance are invisible to it) |
| Match | scores 0.88 to 0.94, prominence about 340, at a peak of only -42.5 dB |
| Other | the device opened in stereo despite `channelCount: 1` (input 1 is used); Chrome logged one 29 ms underrun in 258 s |

Conclusions:
5. With a wired interface, the design works as intended in Chrome. One calibration holds to a
   hundredth of a millisecond, and takes land within 0.01 ms.
6. The timing problem is the wireless iPhone link, not Web Audio, Chrome or the engine.
7. Browser-reported latency is wrong even when wired, so measuring is required, not a nicety.

## Blockers and risks

None found yet. To be decided by the runs above:
1. **Headphone latency.** The app can't measure it without the earcup trick, and Bluetooth may vary
   from run to run.
2. **The iOS audio route** when the mic is open.
3. **Whether Safari and Firefox honour** `echoCancellation: false` and friends.
4. **Clock drift** with separate input and output devices.

## Open items

- The one-off null-check failure in the release JS build: 10 repeat runs since (5 with and 5
  without COOP/COEP) all passed. The self-test now records the failing step's stack. Watch for it
  in your runs; if it shows up again, the stack in the results says where.
- Recommendation: written after your runs.

---

[Back: Invoice Ninja](invoice-ninja-integration-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs)
