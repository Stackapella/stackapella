# Third-party notices

Stackapella's own code is under the Attribution Assurance License (`LICENSE`), and its content
under `CONTENT-LICENSE`. This file lists what in it, or loaded by it, is someone else's, under
which licence, and where. Thank you to everyone below.

## Loaded from a CDN (not in this repository)

Each is loaded unmodified, pinned to an exact version with a Subresource Integrity hash, and never
bundled into our code (there's no npm; `web/index.html`'s import map and `web/js/mp3.js`).

| What | Version | Licence | From |
|---|---|---|---|
| [Vue](https://vuejs.org) | 3.5.44 | MIT, Copyright (c) 2018-present, Yuxi (Evan) You | jsDelivr (`vue.esm-browser.prod.js`) |
| [lamejs](https://github.com/zhuker/lamejs) (MP3 export) | 1.2.1 | LGPL-3.0, by Alex Zhukov | jsDelivr (`lame.min.js`), loaded only when someone exports an MP3 |
| [Figtree](https://github.com/erikdkennedy/figtree) (weights 300 to 800) | Google Fonts | SIL Open Font License 1.1 | fonts.googleapis.com |
| [Noto Music](https://fonts.google.com/noto/specimen/Noto+Music) (a few music signs) | Google Fonts | SIL Open Font License 1.1 | fonts.googleapis.com |

**lamejs is under the LGPL-3.0.** It stays a separate file, loaded unmodified at run time, so
it can be replaced; if you copy it to your own server, keep it a separate file with its licence
(https://www.gnu.org/licenses/lgpl-3.0.html).

Two services load their own scripts, only where they're turned on: Cloudflare Turnstile
(`challenges.cloudflare.com`, when shield mode is on and its keys are set) and Google's and
Apple's sign-in. They're services, under their own terms, not code in this repository.

## In this repository

| What | Licence | Where |
|---|---|---|
| Figtree Medium, Bold and ExtraBold (TTF), Copyright 2022 The Figtree Project Authors | SIL Open Font License 1.1 (its text beside the fonts) | `server/resources/fonts/OFL.txt`, `server/resources/promo/showreel/fonts/OFL.txt` |
| Tailwind CSS's compiled output | MIT, Copyright (c) Tailwind Labs, Inc. (its banner kept at the top of the file) | `web/css/app.css`. The standalone Tailwind 4.3.3 binary is downloaded by `web/tool/tailwind.sh`, checked by its SHA-256, and never committed |
| The 10,000 most common passwords, from [SecLists](https://github.com/danielmiessler/SecLists) (`Passwords/Common-Credentials/10k-most-common.txt`) | MIT, Copyright (c) 2018 Daniel Miessler (in full below) | `server/resources/data/common-passwords.txt` |
| Disposable email domains, from [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains) | CC0 1.0 | `server/resources/data/disposable-domains.txt` |
| Apple Root CA - G3, Apple's public root certificate (to check App Store notices) | Published by Apple for this use | `server/resources/certs/AppleRootCA-G3.pem` |
| The app's five sounds (stars, the mic heard, special moments) | CC0 1.0, credited for provenance in `web/sounds/CREDITS.md`: HappyPizzaBread, Rob_Marion and PhonosUPF (Freesound), Kenney (Interface Sounds), EZduzziteh (OpenGameArt) | `web/sounds/` |

### SecLists' licence

```
MIT License

Copyright (c) 2018 Daniel Miessler

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## The real instruments (on our CDN, not in this repository)

stackapella.com's instrument samples are built from CC0 sample sets by
`server/tool/instruments/build.py` (the sources pinned in `server/tool/instruments/sets.json`,
each build's `LICENSES.txt` beside its samples). CC0 asks for no credit; we give it gladly:

| Instrument | From | Licence |
|---|---|---|
| Grand piano | Versilian Studios Chamber Library (VCSL), Grand Piano, Kawai | CC0 1.0 |
| Electric piano (FM) | Versilian Studios Chamber Library (VCSL), TX81Z FM Piano | CC0 1.0 |
| Nylon-string guitar | FreePats, Spanish classical guitar | CC0 1.0 |
| Ukulele | FreePats, Ukulele (Mateusz Dąbrowski) | CC0 1.0 |
| Upright bass | Karoryfer Samples, Meatbass (pizzicato) | CC0 1.0 |
| Electric bass | Karoryfer Samples, Swagbass (flatwounds) | CC0 1.0 |
| Drawbar organ | FreePats, Drawbar organ emulation (rendered from setBfree) | CC0 1.0 |
| String section | Versilian Studios, VSCO 2 Community Edition (cello, viola and violin sections) | CC0 1.0 |
| Drum kit (sticks) | Versilian Studios and Karoryfer Samples, Virtuosity Drums | CC0 1.0 |
| Drum kit (brushes) | Karoryfer Samples, Swirly Drums | CC0 1.0 |
| Claps, snaps and stomps | Karoryfer Samples, body_percussion; VCSL, Shaker, Small | CC0 1.0 |

A self-hosted install has none of these until it builds and uploads its own
(`docs/instrument-build.md`).

## Server packages (not in this repository)

The server's PHP packages are installed by Composer from `server/composer.lock` and aren't
committed. They're under MIT, BSD-2-Clause, BSD-3-Clause and Apache-2.0 licences (the Apache-2.0
ones: `aws/aws-sdk-php`, `aws/aws-crt-php`, `phpoption/phpoption`); each package's own `LICENSE`
and `NOTICE` are in `server/vendor/` after `composer install`. Keep them if you ship a build with
`vendor/` included.
