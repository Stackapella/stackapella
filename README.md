<p align="center">
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="web/icons/readme/banner-dark.jpg">
  <img src="web/icons/readme/banner-light.jpg" width="100%" alt="Stackapella: Harmony, made by you. Learn a part with a guide voice, then stack your own voices, exactly in time. Beside the words, Sing with a line of Amazing Grace lit, and the studio recording the Tenor.">
</picture>
</p>

# Stackapella

Stackapella is a web app for singing harmony on your own. You learn a part with a guide voice, then
record every part of a song yourself, one voice at a time, and hear them come together. Try it at
[stackapella.com](https://stackapella.com/?ref=github).

<table>
<tr>
<td width="50%">
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="web/icons/readme/sing-dark.jpg">
  <img src="web/icons/readme/sing-light.jpg" width="100%" alt="Sing on a bigger screen: your turn on a line of Amazing Grace, your notes lit on the lane as you hit them.">
</picture>
<p align="center"><b>Your notes light up as you hit them</b></p>
</td>
<td width="50%">
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="web/icons/readme/studio-dark.jpg">
  <img src="web/icons/readme/studio-light.jpg" width="100%" alt="The studio: Soprano, Alto and Tenor recorded and stacked in time, with the Bass ready to record.">
</picture>
<p align="center"><b>Record every part, exactly in time</b></p>
</td>
</tr>
<tr>
<td width="50%">
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="web/icons/readme/course-dark.jpg">
  <img src="web/icons/readme/course-light.jpg" width="100%" alt="The free course: the next lesson and today's workout, and a lesson's end with three stars and a Did you know? card.">
</picture>
<p align="center"><b>A free course to four-part harmony</b></p>
</td>
<td width="50%">
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="web/icons/readme/looks-dark.jpg">
  <img src="web/icons/readme/looks-light.jpg" width="100%" alt="The same line in Sing in both looks: the game, and the calm practice tool.">
</picture>
<p align="center"><b>Two looks: a game, or a calm practice tool</b></p>
</td>
</tr>
</table>

- Learn a part by ear from a guide voice, then sing it on your own while the other parts play.
- Record each part over the ones before, lined up exactly in time, and share the whole song.
- A free course from your first note to four-part harmony, and short courses on skills and styles.
- Practice tracks and choir groups for choirs.
- Two looks: a game with stars and combos, or a plain practice tool.

The server is Laravel and MySQL. The app is Vue loaded from a CDN, with no npm and no build step.
Recording uses Web Audio and an AudioWorklet, with every part scheduled on the audio clock, and your
pitch is tracked on your device.

## Running it

To run your own copy, start at [stackapella.com/selfhost](https://stackapella.com/selfhost).
[DEVELOPING.md](DEVELOPING.md) has the full setup, for an install or for working on the code.

## Licence

The code is under the Attribution Assurance License ([LICENSE](LICENSE)). The songs, courses,
guides, help and screenshots are under CC BY-SA 4.0 ([CONTENT-LICENSE](CONTENT-LICENSE)). The
Stackapella name and logo are covered by neither: see [TRADEMARK.md](TRADEMARK.md). More at
[stackapella.com/open-source](https://stackapella.com/open-source).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). To report a security problem, see [SECURITY.md](SECURITY.md).
