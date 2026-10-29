# The user guide: screenshots to shoot

**Status:** listed 2026-10-27 from the guide's words (`pages.help.pages.*`); the frontend's screenshot flow shoots them. Each is a pair, Warm light and Stage dark, as png and webp: `web/icons/help/<id>.png`, `<id>-dark.png`, and the same in `.webp`. A page shows a screenshot once all four files are there (`App\Support\Site\HelpGuide::shot`).

Back: [The user guide](user-guide-plan.md)


Each shot in Warm light and Stage dark (`<id>.png`, `<id>-dark.png`, with webp), per docs/user-guide-plan.md. Song: Amazing Grace (4 parts: Soprano, Alto, Tenor, Bass), sung as the Alto unless a row says otherwise. Phone is 390 by 844, desktop 1440 by 900.

| Shot id | Screen | Exact state | Size |
|---|---|---|---|
| `getting-started--home` | Home (`/`) | First visit, signed out, no lessons and no projects. The course band with "Learn to sing harmony, one part at a time.", the four lanes and Start singing, then the songs band titled "Hear a harmony first" starting to show. Install card not shown. | Phone |
| `getting-started--first-note` | Lesson 1, "Your comfortable note" (`/learn/1-comfortable`), step 2 of 2 | A note found: "Your comfortable note", A3, the line "Alto and tenor parts may suit you. The exercises start from here.", Play it and Sing again, Finish enabled. Signed out. | Phone |
| `getting-started--song` | Home, the song dialog open on Amazing Grace | Signed out, no projects. How many singers? set to 4, the four parts with their ranges, credits, Preview, Check my range and Start. | Phone |
| `getting-started--recorded` | Sing (`/p/<id>/sing`), Just recorded | Part by part, the Alto just recorded: "Alto recorded", the stack with the Alto outlined and the others as guides, How it went with 2 stars, Hear it with the others, Record again, Next: Tenor. Scroll so the heading and the two buttons show. | Phone |
| `your-voice--note` | Lesson 1, step 2, while listening | Holding a note: the ring around the mic, "That's it, keep holding", the marker on the bar zoomed to the octave around A3, the hold ring part filled. | Phone |
| `your-voice--map` | Course map (`/learn`) | Signed in, free plan. Unit 1 done with stars on each lesson, unit 2's first lesson marked Start singing, 2 of 3 days this week, Level 2. Top: the ring and level, Your weekly goal (3 days), Today's workout. | Phone |
| `your-voice--stars` | A lesson's end screen | Unit 2, "Hold a note", just finished with 2 stars: "Lovely work!", "+XP", Play again for more stars, Next lesson. After the celebration has faded. | Phone |
| `your-voice--voice` | Your voice (`/voice`) | Signed in, free plan. Range G3 to E5 lit on the keyboard with A3 marked, "That suits an alto part." with Find an alto part to sing, a few badges earned (First note, First lesson). Scrolled to show the range and the first badges. | Phone |
| `learn-a-part--steps` | Sing, Part by part | Learning the Alto, step 2 of 4, Sing along: the four steps with Listen ticked, the hint and "Sing along with phrase 2", then The phrases with phrase 1 ticked and phrase 2 as You are here. Not playing. | Phone |
| `learn-a-part--stage` | Sing, The whole song, Words | On your own, the Alto's turn mid-line: Your turn band at the top, the line big with the current syllable lit in the Alto's colour, the tuning meter's dot near the middle. | Phone |
| `learn-a-part--notes` | Sing, The whole song, Notes | On your own, the Alto's turn: the lane across the whole width (no parts listed at the side), the Alto's notes moving toward the now line, the voice line drawn, two or three notes lit, the other parts faint, the keyboard down the side. | Desktop |
| `learn-a-part--recording` | Sing, the recording stage | Recording the Alto at 0:09: the red Recording label and Take 1, the lane with the notes, the words with the syllable sung now highlighted, the time, on pitch so far, Start over and Stop and keep. |
| `learn-a-part--report` | Sing, the take's review | The Alto just recorded: One thing to try, the phrases with how much was on pitch, and phrase 1's pitch line with Play phrase 1 and Re-sing phrase 1. |
| `studio--overview` | Studio (`/p/<id>`) | Signed in. Soprano and Alto recorded (tagged You), Tenor and Bass as guides, The whole song under them with Hear it polished off, Export and Share, the red Record Tenor button in the transport. Stopped at 0:00. | Desktop |
| `studio--recording` | Studio, recording | Recording the Tenor at about 0:12: its row outlined in red with the live waveform and the mic level, "Recording Tenor 0:12" at the top, "Stop recording" in the transport. | Phone |
| `studio--trim` | Studio, Trim | Signed in, the Soprano and Alto recorded, the Alto a little trimmed at each end. The Trim sheet from its top: "Trim Alto", the take with its cut ends hatched and a grip at each, Keeping and the length, then Starts at and Ends at, each zoomed with its time, minus and plus (82.png). | Phone |
| `studio--mixer` | Studio | Signed in, all four parts recorded. The Tenor soloed (its S lit), the others showing "Not soloed", each track with S, M and its volume (117.png). | Desktop |
| `studio--export` | Studio, Export | Signed in, all four parts recorded, nothing soloed. The Export sheet from the header: The whole song chosen, Sound with Polished and its Room, File with the name and WAV, and the foot with the size and length (83.png). | Desktop |
| `studio--share` | Studio, Share | Signed in, all four parts recorded. The Share sheet from the header: the preview (the parts' colours, the title, "Four-part harmony", the length, polished), What to share with all the parts chosen, Polished on, the line about who can listen, and Create link (84.png). | Desktop |
| `settings--page` | Settings (`/settings`) | Signed in, free plan, with a name and an initial (no photo). The five sections down the side, and the profile card with Choose a photo, Your name, Handle and About you (87.png). | Desktop |
| `settings--theme` | How it looks (the theme button's sheet), from Settings | Mode on Auto, Warm chosen (light shot) or Stage (dark shot), the accent on the theme's own, Done at the bottom. | Phone |
| `settings--storage` | Storage (`/storage`) | Signed in, free plan: 3 of 3 songs as slots, Space, Space with each song's share, and three songs biggest first (88.png). Recently deleted, holding one replaced Alto take, is below the fold. | Phone |
