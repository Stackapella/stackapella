# Real instruments: free sample sets for Stackapella

Research date: 27 Oct 2026. Every licence marked "verified" was read at the primary source: the author's own site, or a repo the author commits to (checked by commit author), or the LICENSE file in it. Nothing large was downloaded; sizes come from repo trees and download pages, and formats from reading the first 4 KB of a few files.

## Bottom line

- **Pick CC0 sets wherever possible.** With CC0 there is no credit duty for us or for users publishing mixes. CC BY sets are legal, but CC BY 3.0/4.0 requires attribution when the material is shared "including in modified form", and whether a user's song counts as that is unclear. That would put a duty on users we can't enforce, so CC BY is backup only.
- There is a verified **CC0 pick for every role**. Most of them come from Versilian Studios (VCSL, VSCO 2 CE, Virtuosity Drums), Karoryfer Samples (D. Smolken) and FreePats.
- **Avoid:** Hydrogen GMRockKit (GPL), FreePats steel-string guitar (GPL 3+ with exception), MusyngKite and FatBoy (CC BY-SA), the jRhodes3c/3d full sets (NonCommercial), and Splendid Grand Piano (its "public domain" claim comes only from a third party).
- **Size:** a full "Real instruments" set trimmed as below is about **12 to 18 MB in total**, loaded per instrument. A typical song needs 3 to 8 MB.
- **Format:** use our own JSON manifest plus MP3 (or AAC) files, generated at build time from the upstream SFZ. Don't use SF2 or SFZ at runtime. TinySoundFont is MIT and portable, but it only reads uncompressed SF2, so it's not our runtime engine.

## Recommended shortlist

Size estimates assume trimmed one-shots with a fade-out, 1 round robin, and MP3/AAC at 128 kbps mono (16 KB/s) or 160 kbps stereo (20 KB/s).

| Role | Pick (licence) | Backup (licence) | Est. download |
|---|---|---|---|
| Acoustic piano | VCSL "Grand Piano, Kawai" (CC0, verified) | Salamander Grand Piano V3 (CC BY 3.0, verified) | 5.5 MB mono / 7 MB stereo |
| Electric piano | jRhodes GM subset in Discord SFZ GM Bank (CC0 in author's header; see risk) | Greg Sullivan Wurlitzer EP200 (CC BY 4.0, verified) or VCSL TX81Z "FM Piano" (CC0, verified) | about 1 MB |
| Acoustic guitar | Jeff Learman Martin HD28, in Discord SFZ GM Bank (CC0, author's header) | FreePats Spanish classical guitar, nylon (CC0, verified) | 0.6 MB (Martin), 2 MB (nylon) |
| Ukulele | FreePats Ukulele (CC0, verified) | none verified; keep the synth | about 1 MB |
| Upright bass | Karoryfer Meatbass, pizzicato (CC0, verified) | Karoryfer Sneakybass (CC0, verified) | 1 to 1.5 MB |
| Electric bass | Karoryfer Swagbass, flatwounds (CC0, verified) | FreePats Bass Guitar YR (CC0, verified) or Karoryfer Black And Blue Basses (CC0) | 1 to 1.5 MB |
| Drum kit (sticks) | Virtuosity Drums (CC0, verified) | DrumGizmo MuldjordKit (CC BY 4.0) or AVL Drumkits (CC BY-SA 3.0) | 1 to 1.5 MB |
| Brushes | Karoryfer Swirly Drums (CC0, verified) | none verified | 1 to 1.5 MB |
| Claps, snaps, shaker | Karoryfer body_percussion (claps, finger snaps, stomps; CC0) plus VCSL Shaker and Claps (CC0) | FreePats World Percussion (clap, egg shaker, maracas, tambourine, cajon; CC0) | 0.3 to 0.5 MB |
| Organ | FreePats Drawbar Organ Emulation (CC0, verified) | VCSL Pipe Organ (CC0, verified) | about 1.5 MB |
| Pad | VSCO 2 CE string sections, sustain (CC0, verified) | FreePats Synth Pad (licence not checked) | about 3 MB |

**Credit text:** none is needed for the CC0 picks. As a courtesy, and for provenance, add a credits page:

> Real instruments use public-domain (CC0) samples from Versilian Studios (VCSL, VSCO 2 CE, Virtuosity Drums), Karoryfer Samples, FreePats and Jeff Learman. Thank you.

Credit text if a backup is used:

- Salamander: "Salamander Grand Piano V3 by Alexander Holm, licensed CC BY 3.0 (creativecommons.org/licenses/by/3.0/). Samples trimmed and re-encoded."
- Greg Sullivan: "Wurlitzer EP200 samples by Greg Sullivan (sullivang.net), licensed CC BY 4.0. Trimmed and re-encoded."
- MuldjordKit: "MuldjordKit by Lars Muldjord (DrumGizmo), CC BY 4.0."

## Per-candidate detail

### Acoustic piano

- **VCSL Grand Piano, Kawai (pick).** https://github.com/sgossner/VCSL (commit c1ea7bc).
  - Licence: the README says "This collection is under a Creative Commons 0 license. Essentially it's Public Domain- you can do whatever you want with these sounds (even make commercial software), no royalties, no credit, no special terms." https://versilian-studios.com/vcsl/ adds "This includes integrating it in projects such as generative music, DAWs, granular synthesizers, and samplers."
  - Fit: 44.1 kHz/16-bit stereo WAV, about 38 pitches (roughly whole tones), 4 velocity layers, 460 MB. Keep every 4th semitone from C1 to C7 (19 notes), 3 layers, about 6 s each.
- **VCSL Upright Piano, Yamaha.** Also CC0, but sampled only on C and G (about 6 semitones apart) with 3 layers, so stretched notes will sound uneven. 44.1/24 stereo, 248 MB, about 4.7 MB trimmed. Good for an "old upright" doo-wop or barbershop flavour.
- **Salamander Grand Piano V3 (backup).** Alexander Holm, Yamaha C5, two AKG C414s in AB.
  - Licence: archive.org metadata (https://archive.org/details/SalamanderGrandPianoV3) gives licenseurl creativecommons.org/licenses/by/3.0, and the description says "Licence: CC-by". The SFZ remap (https://github.com/sfzinstruments/SalamanderGrandPiano) also says CC BY 3.0.
  - Commercial use and redistribution are allowed with credit. The credit duty may also reach users' published mixes (see Bottom line).
  - Fit: 48 kHz/24-bit, 16 layers, minor-third spacing (1.4 GB). Trimmed to 3 layers: 7 to 9 MB stereo.
  - Quality: the best regarded of these pianos.
- **Others:**
  - Karoryfer Osiris Piano (Yamaha C2, CC0, still beta, 2 layers, 490 MB).
  - FreePats Upright Piano KW (CC0, Kawai upright recorded on a Zoom H1 in a living room, 32 MB FLAC).
  - Iowa MIS piano. The site says it "may be downloaded and used for any projects, without restrictions" (https://theremin.music.uiowa.edu/MIS.html). That is usable but informal; the recordings are mono and anechoic.
  - Not recommended: Splendid Grand Piano (Akai "public domain" stated only by the remapper) and YDP Grand (CC BY 3.0).

### Electric piano

- **jRhodes GM subset (pick, one check needed).** It's in https://github.com/sfzinstruments/Discord-SFZ-GM-Bank, file `Discord GM/Melodic/005-Electric Piano 1.sfz`.
  - Licence: the header reads "Author: Jeff Learman ... License: Creative Commons CC0, Jeff Learman", and Jeff Learman (jlearman) committed the file himself in Oct 2020.
  - Fit: 15 mono 44.1/16 samples, 1 velocity layer, about 6 MB WAV.
  - **Risk:** the full jRhodes3c set (https://github.com/sfzinstruments/jlearman.jRhodes3c) says "To distribute the samples themselves, such as in an application ... licensed under CC BY-NC-SA 4.0 ... To use the samples in a commercial product, please contact me". The GM subset is a separate CC0 grant, but the two statements conflict. Send Jeff a one-line email to confirm, or use a backup.
- **Greg Sullivan Wurlitzer EP200 / CP80 / Pianet T (backup).**
  - Licence: https://www.sullivang.net/ says "Licensing (updated 21-Oct-2026): All these instruments I host here now have Creative Commons Attribution 4.0 International licensing. (CC BY 4.0)". That's commercial use and redistribution with credit.
  - Fit: Wurlitzer has 4 layers, but these are 1999-era samples (2.4 MB FLAC in the SFZ remap). Expect lo-fi.
- **VCSL TX81Z "FM Piano".** CC0, verified. An FM electric piano tone at 44.1/16 stereo (68 MB). This is the cleanest-licence fallback.

### Acoustic guitar

- **Martin HD28 by Jeff Learman (pick).** File `026-Acoustic Guitar (steel).sfz` in the same GM bank.
  - Licence: the header says "License: Creative Commons CC0", committed by the author.
  - Fit: 15 mono 44.1/16 samples, minor-third spacing from E2 to B5, 1 velocity layer (the mapping uses a velocity filter), 3.2 MB WAV.
  - It's single notes only. Build strums by staggering the strings 5 to 25 ms apart on the AudioContext clock.
- **FreePats Spanish classical guitar (backup, nylon).** https://freepats.zenvoid.org/Guitar/acoustic-guitar.html says "Published under the terms of the Creative Commons CC0 1.0 public domain dedication." Recorded in 2008 with an AKG Perception 120 in "far from ideal" conditions and filtered for noise. 4.5 MB FLAC.
- **Avoid:**
  - FreePats FSS Steel-String: GPL v3+ "with a special exception" covering documents made with it. The exception means mixes are fine, but distributing the samples from our CDN puts them under GPL obligations, and GPL files in an App Store app are best avoided.
  - Karoryfer Emilyguitar and Shinyguitar are CC0 but electric/archtop.
  - Ovation Guitar has no licence file.

### Ukulele

- **FreePats Ukulele (pick).** https://freepats.zenvoid.org/GuitarFamily/ukulele.html: "Published under the terms of the Creative Commons CC0 1.0 public domain dedication."
  - Recording: Flight Fireball tenor ukulele and a RØDE Podcaster, by Mateusz Dabrowski. Version 2026-08-11.
  - Range: C4 to C6 only, plus body-tap and chuck articulations. 2.3 MB FLAC.
  - Risk: a very new, single-contributor set with a podcast mic, so expect modest quality.
- No backup verified. LMNO "Bad Baritone" and PatchArena FLukelele have no licence I could verify.

### Bass

All the Karoryfer picks are on https://github.com/sfzinstruments, committed by D. Smolken (Karoryfer). Each has a CC0 1.0 LICENSE file and a README saying "Royalty-free for all commercial and non-commercial use." Karoryfer's shop (shop.karoryfer.com) returned HTTP 429 during this check, so the repos are the primary source used.

- **Meatbass (pick, upright).** A 1958 Otto Rubner double bass, pizzicato with 4 velocity layers and 4 round robins. Recorded by Ludwik Zamenhof.
- **Sneakybass (backup).** Same bass played quietly, 44.1/24 stereo, CC0.
- **Swagbass (pick, electric).** Ibanez bass with old flatwound strings, neck pickup, DI, 3 layers. Suits 50s and 60s styles.
- **FreePats Bass Guitar YR (backup).** Yamaha RBX: "under the terms of the Creative Commons CC0 1.0 public domain dedication", 3 MB FLAC.
- Black And Blue Basses (CC0, 2023) is a larger, newer alternative.
- Trimmed size: keep E1 to G3, every 3rd semitone (10 notes), 2 or 3 layers, about 3 s mono. That's 1 to 1.5 MB per bass.

### Drums and percussion

- **Virtuosity Drums (pick).** Versilian Studios with Karoryfer: a jazz kit played with sticks by Austin McMahon, 6 mic positions, 48 kHz/24-bit stereo FLAC, 1.1 GB.
  - Licence: CC0 LICENSE in https://github.com/sfzinstruments/virtuosity_drums, and the author's page https://versilian-studios.com/virtuosity-drums/ says "open source (Creative Commons 0)".
  - It also has shaker, cabasa, tambourine, claves, cowbell and congas from VSCO 2 Pro.
  - Plan: render one premixed stereo kit (for example close plus room) offline. Keep kick, snare, cross-stick, hats (closed, open, pedal), ride and crash at 2 or 3 layers: about 25 one-shots, 1 to 1.5 MB.
- **Swirly Drums (pick, brushes).** Karoryfer's brush kit, with snare stirs, flutters, digs, hats, toms and cajon kick.
  - Licence: CC0 `license` file; the changelog says "License changed to free". It was a paid product until then.
  - The full set is 1.7 GB, mostly the "wet" folders. The dry one-shots are small.
- **body_percussion (pick, claps and snaps).** Handclap, finger clap, snaps and stomps, 44.1/24 stereo, 62 MB. The readme says "CC 0 license".
- **VCSL Shaker, Small / Claps / Hi-Hat / Cajon.** All CC0.
- **Backups:**
  - FreePats World Percussion (CC0).
  - FreePats MuldjordKit: "Creative Commons Attribution 4.0", 157 MB FLAC.
  - AVL Drumkits (https://www.bandshed.net/avldrumkits/): CC BY-SA 3.0. The page says "You are free to distribute or sell any music you create with these kits without attribution", but the samples we host would carry BY-SA and need credit.
- **Avoid:** Hydrogen GMRockKit. Its drumkit.xml in hydrogen-music/hydrogen says `<license>GPL</license>` with a GPL v3 header.

### Organ and pad

- **FreePats Drawbar Organ Emulation (pick).** https://freepats.zenvoid.org/Organ/electric-organ.html: "Published under the terms of the Creative Commons CC0 1.0 public domain dedication." It's rendered from the setBfree software organ (a Hammond-style sound) and is 5.8 MB WAV. The Percussive and Rock variants are also CC0.
- **VCSL Pipe Organ (backup).** CC0, 44.1/16 stereo, 145 MB. A church sound, good for gospel or barbershop.
- **VSCO 2 CE string sections for a pad.** https://github.com/sgossner/VSCO-2-CE has a CC0 LICENSE.
- Sustained sounds need loop points. Lossy encoding can click at a loop seam, so bake a crossfade loop into the files before encoding, or ship 8 to 10 s non-looped notes.

### Leads checked and not recommended

- **FluidR3_GM.** Debian's copyright file quotes Frank Wen: "I hereby release Fluid under the MIT license", and also "Fluid was constructed in part from samples found in the public domain". Provenance is unclear.
- **gleitz midi-js-soundfonts.** The renders are labelled CC BY 3.0 (FluidR3) and CC BY-SA 3.0 (MusyngKite, FatBoy), which conflicts with MIT upstream. The audio is also 8-bit-era GM quality. https://github.com/gleitz/midi-js-soundfonts
- **MusyngKite and FatBoy.** ShareAlike.
- **Iowa MIS.** "without restrictions", but mostly orchestral instruments, mono before 2011, and anechoic.

## Shared format and engine

**Recommendation: our own JSON manifest plus compressed audio, generated at build time from the upstream SFZ.**

- **SFZ as the authoring source.** All picks ship SFZ. A PHP CLI converter (no npm) reads the regions and writes our manifest. Our pipeline: pick regions, trim with a fade, normalise, render the drum mic mix, encode.
- **Why not SFZ at runtime.** The picks use ARIA extensions, `#define`, includes, CC curves and keyswitches. That's a large parser surface to duplicate in JS and C++.
- **Why not SF2 at runtime.** Samples must be uncompressed 16/24-bit PCM, so downloads are 5 to 10 times bigger. SF3 (Ogg) is a non-standard extension that TinySoundFont doesn't read, and the web would need an SF2 parser anyway.
- **Manifest per instrument.** Fields: region file, root key, key range, velocity range, tune cents, gain dB, attack/release, loop start/end, round-robin group, plus licence, source URL and source commit.
- **Keep a `LICENSES/` provenance file next to the audio on the CDN.** List each set's source URL and commit: VCSL c1ea7bc, Virtuosity 9f04cf9, Swirly c40dafe, body_percussion 4ac9d89, Meatbass ac9e859, Swagbass 9d10fca, Discord GM 7a9c478, VSCO2CE 4403009.
- **Codec.** MP3 decodes in every browser's `decodeAudioData`; AAC fails on some Linux Firefox builds. Natively, minimp3 (CC0, verified: github.com/lieff/minimp3) gives one C++ decode path on iOS, Android, desktop and WebAssembly. AAC works too if we use the platform decoders.
- **Encoder delay.** Both MP3 and AAC add priming silence, and decoders trim it differently. For tight drum timing, find the onset at load (first sample above a threshold) or store a measured offset in the manifest. Don't trust the decoder.

**TinySoundFont** (https://github.com/schellingb/TinySoundFont)

- **Licence:** MIT. The header says "LICENSE (MIT) Copyright (C) 2017-2025 Bernhard Schelling", based on SFZero.
- **Portability:** a single C header with no dependencies, so it builds for iOS, Android, desktop and WebAssembly.
- **Real-time use:** it is usable in real time if we call `tsf_set_max_voices` and serialise note calls with the audio thread. Its own notes say thread safety "has not been tested much".
- **Format:** SF2 only. No SFZ, no compressed samples.
- **Policy:** the repo has a "Strict No LLM / No AI Policy" for contributions. That's irrelevant to using it, but agent-written patches can't go upstream.
- **Verdict:** good reference code for voice and envelope handling, but the wrong format. Our own small player reading the JSON manifest stays simpler and smaller.
- sfizz (BSD-2-Clause) is the SFZ-native C++ alternative, but it's heavy for our needs.

## Risks

1. **jRhodes GM and the Martin guitar** rest on CC0 headers inside a community repo, not a LICENSE file, and jRhodes has a conflicting NC licence elsewhere. Email Jeff Learman once to confirm both, or use the VCSL TX81Z and FreePats nylon backups.
2. **CC0 provenance is only as good as the uploader.** That's low risk for Versilian and Karoryfer, who record their own instruments. FreePats Ukulele is a new single contributor; keep the source page archived.
3. **Quality gaps.** The ukulele (podcast mic), the nylon guitar (noisy 2008 recording) and the Sullivan EPs (1999) are modest. Single-layer guitar and EP need velocity-to-filter shaping. The Yamaha upright's wide spacing will make stretched notes sound uneven.
4. **No CC0 strum samples exist**, so strums must be synthesised from single notes.
5. **Lossy loops and encoder delay** need the handling above, or drums will flam against the vocals.
6. **Karoryfer's shop page was rate-limited (HTTP 429)**, so its own licence wording was not re-read there. The author-committed repos carry it.

How they're built: [instrument-build.md](instrument-build.md).
