# Ideas for the courses

Status: ideas for Hillel to choose from, 2026-10-29, nothing started. The four course agents each sent
their top three after reviewing their courses, and this merges them with the lead's, ranked by value
for cost. Where several agents asked for the same thing, it's one idea.

| # | Idea | Who asked | Value | Cost |
|---|---|---|---|---|
| 1 | **A fact card.** An optional `fact` (with its source) on a lesson, shown as its own small "Did you know?" card on the lesson end in both skins, instead of folded into the "Scored on" line or the learn cards. The course check gives each fact an id, allows one or two a unit, and fails when two courses tell the same one. | skills, harmony, styles, lead | Facts stop competing with what was scored, every course shows them the same way, Flutter gets one field to show, and duplicates can't creep back | About half a day: one field, one card, a check rule, and a script to move the facts in (about 90 across the eleven courses) |
| 2 | **Record the clips.** Real singers for what the synth can't model: Pop's tone colours, Yodeling's flip pairs, Breath's breathy, pressed and balanced onsets, Barbershop's ring against shimmer. Later: real voices for Counterpoint's and Improvising's partner lines. | styles, skills, harmony | The listening rounds and models finally sound like people, which is what those lessons teach | About an hour of singing per session (`docs/courses/clips-to-record.md`). Slots already play once published. Partner lines (`with`) need clip support first |
| 3 | **Check the pitch tracker on a real phone** for "zz", lip trills and hums, which several courses use. | skills | Tells us whether those steps score fairly on the devices people use | An hour on two phones |
| 4 | **Then and now in every course** (built, 2026-10-29: ux-spec 5.7g). Vibrato's finale plays your lesson 1 take beside your last one. Every course's finale could do the same. | lead | The clearest proof of progress, and the most shareable moment | Content only for the courses built on wave steps; play steps need the frontend to keep a take, about a day |
| 5 | **Sharpen up** (built, 2026-10-29: ux-spec 5.7g). A short session per course replaying the two or three lessons with the fewest stars, mixed. | skills | Practice where it's needed, and a reason to come back after finishing a course | A day or two of frontend, no new content |
| 6 | **Keep the finale as a song.** Save "your duet" or "the trio of you" from a course finale as a project in Sing, every take a track, to replay, edit and share. | harmony | The course ends in something you keep | Medium, on the device only, no server change |
| 7 | **Score the register, not only the pitch.** The harmonics change at a yodel's flip, and breathiness for Pop's colours, so some self-checks become scored steps. | styles | Turns "listen back: did it sound lighter?" into a measured result | Two to three days for a new scorer, tuned on the recorded clips, so after idea 2 |

Later, needed before the apps teach the courses: Flutter support for the course step kinds. The
course check counts every Plus lesson as one the Flutter app can't play yet
(`docs/courses/flutter-course-support.md`).
