# Launch QA: the ten launch courses, as a learner

Status: one pass, 2026-10-29 (courses-styles, for the coordinator), and its fixes checked the same
day. Each finding has an owner; nothing here was fixed by the QA pass in another agent's course. All
ten courses are live in the pinned content with no star, XP, combo or streak words, and the course
check is clean (200 lessons). Nothing open: the frontend's last two are fixed (4fa064ba, 47f6f827).
The first week as a whole is in [launch-qa-journey.md](../launch-qa-journey.md).

Back: [Plus courses](../plus-courses-plan.md) · [Docs guide](../../README.md#a-guide-to-the-docs)

## How it was checked

- **A flow over every lesson** (`web/tests/flows/launch_qa.py`), on the pinned content (981438e9
  plus the courses then live): as staff, each of the ten courses' pages and all 200 lessons, step by
  step to the lesson's end screen (Continue, or Skip where a step needs singing), checking every step
  for sideways scroll, raw translation keys, "undefined" or "NaN", and a step it couldn't get past.
  Then, as a free account with the Plus courses on, lesson 1 of each course open and lesson 2 asking
  for Plus. Run on the phone in the game skin and on the desktop in the serious skin; the course
  page and lesson 1 also shot on the phone in the serious skin and the desktop in the game skin, each
  in light and dark (`~/Code/shots-review/launch-qa/`).
- **A check of every course file:** each lesson's length by the live test's own count (5 or under),
  ending on something sung or answered, a measures line on the last scored step that says what it
  scores, no dashes or empty words, sung notes inside the range rule, and a safety line in lesson 1
  or on the course's page.
- **The site's course pages:** each launch course's slug has its page on the site (SitePages and
  its words); the words for Folk, Yodeling and Pop checked against the built courses.
- Not checked here: a scored take with real singing in every lesson (each course's own flow does
  that for its steps), and audio by ear.

## What the flow found

Nothing in either full pass: every lesson of all ten courses opens and steps through to its end at
both sizes and in both skins, with no sideways scroll, raw keys, "undefined" or "NaN"; every
course's lesson 1 is free and its lesson 2 asks a free account for Plus.

## Findings, by course

### Every course page (frontend)

1. **The serious skin still shows stars on a course page.** On the phone in the serious skin, Up
   next says "~3 min · up to 3 stars" and every unit shows "12/12 ★" (seen on Barbershop's page;
   the course page is the same for every course). Serious mode should show the measures instead. **Up next fixed** (bc679151, the lead); the unit
   cards now count lessons in serious mode (4fa064ba, the frontend).

### Ear training (courses-skills, with the frontend for the lane)

2. **Lesson 1's listening lane clips its notes** on the desktop (ear-1-songs, the first step):
   short notes' syllables are cut to "l…", and the last notes are cut at the lane's right edge. It may
   be the lane drawing a short note's label (frontend) rather than the course. **The syllables fixed**
   (content 606143e, courses-skills: no lyric on those notes); the lane now fades at its right edge
   where more is to come, and a brick shows its word only when all of it fits (47f6f827, the
   frontend).
3. **ear-1-songs has no safety line** of its own (stop if anything tightens or hurts). The course
   page's general line covers it; one card in the free lesson would match the other courses. **Fixed** (606143e).

### Vibrato (courses lead)

4. **vib-1-swing's cards say "Keep it soft, in the middle of your voice" but not "stop if anything
   tightens or hurts"**, which the build plan (3.7) asks lesson 1's Why to say once. The course page's
   general line covers it, and Vibrato has no catalogue `safety` note. **Fixed** (content d6676fb:
   the line in lesson 1, and a course safety note).
5. Its measures lines are already "Scored on" (931d61d), once pinned.

### Perfect your pitch, Breath and stamina (courses-skills)

6. Nothing found beyond the "Scored on" wording, already done (a063f4c), once pinned.

### Counterpoint, Barbershop and tags, Improvising a harmony (courses-harmony)

7. **Their measures lines still say "Your stars are for …"**, which reads wrong in the serious skin
   (the lead's convention: "Scored on …", no star words). The lead's star check waits on this. **Fixed** (4a5dc6a); the star rule is now on in both
   checkers (e53bfe10).
8. **imp-1-third has no safety line** in the free lesson (its third card is about headphones). Its
   course page's general line covers it. **Fixed** (a safety card, and its scored step generous).
9. Improvising a harmony was still "soon"; **now live** (ad77c7a5).

### Folk and early music, Yodeling, Pop and country (courses-styles)

10. **Measures lines said "Your stars are for …"**: fixed in content commit 43821e2 (all three
    courses, "Scored on …", no star, XP, combo or streak words), and Yodeling's two flip lines in
    en.json ("pitchOnly" and "none") fixed in main.
11. The site's Folk page says "A straight tone"; the course teaches and scores a plain, steady tone
    (a light wave is fine). A small wording change for the marketing agent: "A plain, steady tone". **Fixed** (821cca4b).

## Owners

| Owner | Findings |
|---|---|
| frontend | 1 (the unit cards' stars), 2 (the lane's right edge): fixed |
| courses-skills | 2 (the syllables), 3: fixed; Barbershop is now theirs too |
| courses (lead) | 4: fixed |
| courses-harmony | 7, 8: fixed |
| courses-styles | 10 (done) |
| marketing | 11: fixed |
