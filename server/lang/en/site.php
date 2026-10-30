<?php

declare(strict_types=1);

// The public site's pages (docs/public-site-plan.md): features, who it's for, about and press.
// Sentence case, plain words, no em dashes. Numbers from config are placeholders (:free_songs,
// :price), never typed here.
return [
    'features' => [
        'index' => [
            'title' => 'What Stackapella does',
            'description' => 'Learn a harmony part with a guide voice, take the course, stack every part exactly in time, and share it. Everything Stackapella does, and what\'s coming.',
            'headline' => 'Everything you need to sing harmony',
            'lead' => 'Learn a part with a guide voice, record it, then stack the next one on top. Here\'s what the app does today, and what\'s on the way.',
        ],
        'pages' => [
            'learn-a-part' => [
                'end' => ['title' => 'Learn your first part today.', 'body' => 'Pick a song and a part, and the guide sings it first.'],
                'pic' => 'lane',
                'name' => 'Learn a part',
                'summary' => 'Hear a guide sing your part, sing along, and watch your notes light up.',
                'title' => 'Learn a harmony part by ear, with a guide voice',
                'description' => 'Learn any part of a harmony by ear: a guide voice sings it first, you sing along, and your notes light up as you hit them. Free in your browser.',
                'headline' => 'Learn your part, one phrase at a time',
                'lead' => 'Pick a song and a part. A guide voice sings it first, then you sing with it, then on your own, until it\'s yours.',
                'sections' => [
                    [
                        'pic' => 'chooser',
                        'heading' => 'Part by part, or the whole song',
                        'body' => 'Part by part takes you through the song in steps, phrase by phrase. The whole song lets you sing straight through, with the words big on a stage or the notes moving toward you on a lane.',
                        'points' => [
                            'Listen, sing along, on your own, then record',
                            'The words on a stage, or the notes on a lane',
                            'A staff for anyone who reads music',
                        ],
                    ],
                    [
                        'pic' => 'shot:learn-a-part--recording',
                        'heading' => 'See how you\'re doing as you sing',
                        'body' => 'On the lane, your voice is a glowing dot in your part\'s colour with a trail behind it, and each note lights up when you hit it. A missed note simply stays unlit; it never turns red. After a take you see how it went, what went well, and something to try.',
                    ],
                    [
                        'pic' => 'practice',
                        'heading' => 'Help when a part is hard',
                        'body' => 'Loop a tricky phrase, slow the song down, or put soft chords underneath so you have something to tune to. If one phrase of a take didn\'t sit right, sing just that phrase again.',
                        'points' => [
                            'Chords underneath, as a pad or a piano',
                            'Slow practice at 85% or 70%, at the same pitch',
                            'Note names as letters, do re mi, or numbers',
                            'A range check that suggests your part and key',
                        ],
                    ],
                    [
                        'pic' => 'shot:learn-a-part--notes',
                        'heading' => 'No music reading needed',
                        'body' => 'You learn each part by ear, with the notes drawn as shapes you can follow. Real singers sing a growing number of the parts, and a practice voice follows any key and speed.',
                    ],
                ],
            ],

            'course' => [
                // A real screen of the app in the head (web/icons/help, both themes), and what it shows.
                'shot' => 'your-voice--map',
                'shot_alt' => 'The course map in the app: the next lesson, today\'s workout, and the week\'s days with your goal.',
                'end' => ['title' => 'Start with your comfortable note.', 'body' => 'Lesson 1 takes a minute, and it\'s free.'],
                'pic' => 'course',
                'name' => 'The course',
                'summary' => 'Short lessons that take you from your first note to every part of a song.',
                'title' => 'A singing course you play, from first note to harmony',
                'description' => 'Short singing lessons that build to harmony, with clear feedback after each one. Units 1 to 7 are free, ending with every part of Amazing Grace.',
                'headline' => 'Learn to sing harmony, one short lesson at a time',
                'lead' => ['game' => 'The course is a map of short lessons. Each one teaches one thing, lets you try it, then scores it with stars.', 'serious' => 'The course is a map of short lessons. Each one teaches one thing, lets you try it, then tells you how close you were.'],
                'sections' => [
                    [
                        'pic' => 'lane',
                        'heading' => 'From your first note to the whole song',
                        'body' => 'You start by finding a comfortable note and matching notes, then hold steady notes, move between them and sing a tune. From unit 5 you sing harmony: thirds and a round, then the bass and the tenor, until you record all four parts of Amazing Grace yourself.',
                        'points' => [
                            'Find your voice',
                            'Steady notes',
                            'Moving notes',
                            'Singing a tune',
                            'Your first harmony',
                            'Low and inner voices',
                            'The whole song is you',
                            'Expression',
                        ],
                    ],
                    [
                        // Game mode's stars, XP and goal; serious mode's measures and calendar (docs/skins-plan.md, 3).
                        'pic' => 'stars',
                        'serious_pic' => 'course',
                        'heading' => ['game' => 'Stars, levels and a weekly goal', 'serious' => 'Clear measures, and your week'],
                        'body' => [
                            'game' => 'Each exercise earns up to three stars, and you can play again for more. Practice earns XP and levels, so effort counts. The weekly goal asks for a few days a week, and a missed week simply starts fresh.',
                            'serious' => 'Each exercise tells you how close you were, as a share of notes on pitch, and you can sing it again. A calendar shows the days you practised this week.',
                        ],
                    ],
                    [
                        'pic' => 'workout',
                        'heading' => 'Today\'s workout',
                        'body' => 'One button a day: a warm-up in your range, a weak spot from your recent singing, a quick review, and a phrase from the song you\'re learning.',
                        'points' => [
                            'Free: one workout a day',
                            'Plus: as many as you like, with the full exercise library',
                        ],
                    ],
                    [
                        'heading' => 'Free to start, open all the way',
                        'body' => 'Units 1 to 7 are free. Plus opens the rest of the course, starting with unit 8, Expression. The course never locks the songs: you can sing any song you have, any time.',
                    ],
                ],
                // The designed page (site/features/course): its own sections and words.
                'eyebrow' => 'The free course · 8 units, 32 lessons',
                'hero_title' => 'From your first note to four-part harmony.',
                'hero_lead' => 'Start by finding a note that\'s easy for your voice. Short lessons take you through steady notes, moving notes and a whole tune, then into harmony: thirds, a round, the bass and the tenor, until you sing every part of Amazing Grace yourself.',
                'hero_facts' => ['Units 1 to 7 free', 'No account needed to start', 'No reading music'],
                'see_lessons' => 'See the lessons',
                'titles' => [
                    'outcomes' => 'By the end, you can',
                    'lesson' => 'How a lesson goes',
                    'units' => 'The map, unit by unit',
                    'stack' => 'The whole song is you',
                    'try' => 'Try this now: your comfortable note',
                    'facts' => 'Did you know?',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'related' => 'Then, on Plus',
                ],
                'outcomes' => [
                    'Find a comfortable note, and match the notes you hear.',
                    'Hold a steady note, move between notes, and sing a whole tune.',
                    'Sing harmony: a third above a tune, a round, then the bass and the tenor.',
                    'Sing all four parts of Amazing Grace yourself, and hear them come together.',
                ],
                'lesson' => [
                    ['Learn', 'One idea at a time, drawn so you can see it.'],
                    ['Sing', 'Your notes light up on the lane as you hit them.'],
                    ['Hear yourself', ['game' => 'Up to three stars and XP, and a weekly goal. Play again for more.', 'serious' => 'How close you were, as a share of notes on pitch. Sing it again whenever you like.']],
                ],
                'units_say' => [
                    'Your comfortable note, matching notes, higher and lower, breathing, and five notes sung back.',
                    'Holding a note, against a drone, on different vowels, and for eight beats.',
                    'Steps up and down, small jumps, and the opening of Ode to Joy.',
                    'Following the words, phrasing, and the first verse of Amazing Grace.',
                    'Humming against a drone, finding the third, a round, two parts in thirds, and recording the round\'s second voice.',
                    'Holding your own note, the bass line, the tenor, and recording the bass of Amazing Grace.',
                    'Hearing the blend, then all four parts of Amazing Grace.',
                    'Loud and soft, a gentle vibrato, high notes, shaping a line, and Danny Boy.',
                ],
                'plus_unit' => 'Plus',
                'stack' => [
                    'lead' => 'In unit 7 you sing all four parts of Amazing Grace, one at a time, each over the ones before, and hear them together. Every voice in the chord is yours.',
                    'body' => 'The app lines your parts up exactly in time, so the song sounds like a choir of you.',
                ],
                'try' => [
                    'lead' => 'Lesson 1, without the app. It takes a minute.',
                    'steps' => [
                        'Hum a note: not too high, not too low.',
                        'Find where it\'s easy: no push, no strain.',
                        'Sing it on "oo", soft and steady, and hold it for a few seconds.',
                        'That\'s your comfortable note. Every voice has an easy note, and there\'s no wrong one.',
                    ],
                    'after' => 'In the app, lesson 1 hears your note, names it, and shows the parts that may suit your voice.',
                ],
                'facts' => [
                    ['Your vocal folds open and close once for every wave of a note: about 110 times a second in a typical man\'s speaking voice, about 210 in a woman\'s.', 'Titze, Principles of Voice Production (1994)', ''],
                    ['John Newton, a slave-ship captain who became a clergyman, wrote Amazing Grace for his New Year\'s Day sermon in 1773; words and tune met in print in 1835.', 'Hymnology Archive, Amazing Grace', 'https://www.hymnologyarchive.com/amazing-grace'],
                    ['In 1951 Les Paul layered take after take of Mary Ford\'s voice on one tape for "How High the Moon", nine weeks a US number one.', 'Sound on Sound, Classic Tracks: How High The Moon', 'https://www.soundonsound.com/techniques/classic-tracks-les-paul-mary-ford-how-high-moon'],
                ],
                'who' => [
                    'Anyone who wants to sing, starting from the very first note.',
                    'Singers who can carry a tune and want to learn harmony.',
                    'Choir singers who want to hold their own part.',
                ],
                'need' => [
                    'A quiet spot and a few minutes a day.',
                    'Headphones for the harmony units, so the other parts stay out of your recording.',
                    'No reading music, and no account to start.',
                ],
                'safety' => [
                    'Keep everything easy: stop if anything tightens or hurts.',
                    'Find your note with no push and no strain, soft and steady.',
                    'No app can replace a real voice teacher.',
                ],
                'faq' => [
                    ['Is it really free?', 'Units 1 to 7 are free, from your first note to all four parts of Amazing Grace. Plus opens unit 8, Expression, and the Plus courses.'],
                    ['Do I need an account?', 'No. You can start without one, and your progress stays on this device. A free account saves it to your account.'],
                    ['Can I learn harmony if I\'ve never sung?', 'Yes, that\'s what it\'s for. The first units find your voice and steady your notes before any harmony.'],
                    ['Do I need to read music?', 'No. You learn every part by ear, with the notes drawn as shapes on a lane.'],
                ],
                'related' => ['vibrato', 'ear-training', 'barbershop'],
            ],

            'studio' => [
                'end' => ['title' => 'Stack your first harmony.', 'body' => 'Record one part, then sing the next over it, exactly in time.'],
                'pic' => 'stack',
                'name' => 'The studio',
                'summary' => 'Record each part over the others, exactly in time.',
                'title' => 'Stack harmony parts exactly in time, in your browser',
                'description' => 'Record each part while you hear the others. A timing check lines every take up exactly. Then trim, level and polish the mix. Instruments welcome.',
                'headline' => 'Every part, exactly in time',
                'lead' => 'Record one part, then the next while you hear the first. Each part is a row in its own colour, and each one lands exactly where you sang it.',
                'sections' => [
                    [
                        'pic' => 'timing',
                        'heading' => 'A quick timing check',
                        'body' => 'Every phone, computer and pair of headphones adds its own delay. A short timing check measures yours, so new parts line up exactly. If a take still sounds off, nudge it earlier or later by ear, or let the app line it up.',
                    ],
                    [
                        'pic' => 'shot:studio--trim',
                        'heading' => 'Tidy each part',
                        'body' => 'Trim the breath at the start and the reach for the stop button at the end. Trimming never changes the recording, and you can undo it.',
                        'points' => [
                            'Solo, mute, level and pan for each part',
                            'Trim the silence in one tap',
                            'A count-in and a click when you want them',
                        ],
                    ],
                    [
                        'pic' => 'shot:studio--mixer',
                        'heading' => 'Polished, with one switch',
                        'body' => 'One switch levels the parts, evens out loud and quiet moments, and adds a room: dry, a small room, a hall or a chapel. Hear it polished before you share it. Your takes stay as you sang them.',
                    ],
                    [
                        'pic' => 'parts',
                        'heading' => 'Voices first, instruments welcome',
                        'body' => 'Any part can be an instrument. Record a ukulele, a guitar or a beatbox, name the part, and stack it with the voices.',
                    ],
                ],
            ],

            'real-voices' => [
                'end' => ['title' => 'Learn from a real singer.', 'body' => 'Pick a song with a real voice, and sing along with them.'],
                'pic' => 'modes',
                'name' => 'Real voices',
                'summary' => 'Guide parts sung by real singers, each one credited.',
                'title' => 'Learn harmony from real singers\' voices',
                'description' => 'A growing number of guide parts on Stackapella are sung by real singers, credited on the part. Choose a version, or a practice voice that follows any key and speed.',
                'headline' => 'Learn from a real singer',
                'lead' => 'A real voice shows you how a part should feel: the breath, the vowels, the shape of each phrase. Real singers sing a growing number of our parts, and each one is credited.',
                'sections' => [
                    [
                        'pic' => 'parts',
                        'heading' => 'Credited on the part',
                        'body' => 'Each real voice says who sang it: "Sung by" and their name, or "one of our singers" if they\'d rather stay anonymous. Where a part has no real voice yet, the practice voice sings it.',
                    ],
                    [
                        'pic' => 'voices',
                        'heading' => 'Choose your guide',
                        'body' => 'Some parts have more than one recorded version. One plays first, and you can switch to another, or to the practice voice, at any time.',
                    ],
                    [
                        'pic' => 'lane',
                        'heading' => 'In your key, at your speed',
                        'body' => 'Real singers record in the song\'s own key, at full speed. If you sing in another key, or slow the song down, the practice voice takes over, because it follows any key and speed. Going back to the real voice is one tap away.',
                    ],
                    [
                        'pic' => 'public',
                        'heading' => 'Your voice could be the guide',
                        'body' => 'Most songs start with a practice voice, and real voices join them part by part. Yours can be one of them: when you make a harmony public, we may choose your part as the voice everyone learns from, credited to you by your profile name.',
                        'points' => [
                            'Public only when you say yes to featuring it as you share, for singers 18 and over, with everyone who sang on it agreeing',
                            'A harmony you only share by link is never used',
                            'Untick featuring any time, and your parts stop being guides',
                        ],
                    ],
                ],
            ],

            'create' => [
                'end' => ['title' => 'Make a song your own.', 'body' => 'Sing the tune or write it, and hear the other parts suggested.'],
                'pic' => 'create',
                'name' => 'Create',
                'summary' => 'Make your own arrangement: sing the tune, and get the other parts suggested.',
                'title' => 'Write your own harmony arrangement',
                'description' => 'Sing a tune line by line, or write the notes, and Stackapella suggests the alto, tenor and bass. Share your arrangement by link for friends to learn.',
                'headline' => 'Make your own arrangement',
                'lead' => 'Sing your melody or write it note by note. Stackapella suggests the other parts, and you can change anything.',
                'sections' => [
                    [
                        'pic' => 'singline',
                        'heading' => 'Sing it, or write it',
                        'body' => 'Paste the words, then sing the melody a line at a time with the words in front of you. The app finds the notes and fits each syllable. Or enter the notes yourself, in an editor where every note sounds as you place it.',
                    ],
                    [
                        'pic' => 'parts',
                        'heading' => 'The other parts, suggested',
                        'body' => 'From your melody, Stackapella writes an alto, tenor and bass to fit it, in three styles to hear and choose from. Try another chord where a bar doesn\'t sit right, or write the parts yourself.',
                    ],
                    [
                        'pic' => 'songpage',
                        'heading' => 'Share it to be learned',
                        'body' => 'Try it as a learner, then share it by link: friends learn it part by part, like any of our songs. You can also start from a copy of one of ours.',
                        'points' => [
                            'Free: make your own, and keep one on your account to share',
                            'Plus: as many private and unlisted arrangements as you like',
                        ],
                        // Said as coming (a Coming soon label), never as a tick.
                        'soon' => 'Instrument parts, and your own recorded voice as the guide on Plus.',
                    ],
                    [
                        'heading' => 'Start from chords',
                        'body' => 'Start from chords instead of a tune: pick a progression or a 12-bar blues in a key that suits your voice, sing over a simple backing, and the chords become the harmony parts.',
                        'soon' => 'A chorus and a bridge suggested to go with your verse.',
                    ],
                ],
            ],

            'your-tune' => [
                'end' => ['title' => 'Your tune, in four parts.', 'body' => 'Until Hum a tune opens, sing your melody line by line in the creator.'],
                // What works today, as the page's button (its key in Landing::links, or an address).
                'try' => ['Try the creator', 'create'],
                'pic' => 'icon:hum',
                'name' => 'Hum a tune',
                'summary' => 'Hum or draw a tune, hear it harmonised, and invite real singers to sing it.',
                'title' => 'Hum a tune and hear it harmonised',
                'description' => 'Hum a melody or draw it, hear it harmonised in four parts, then share it so real singers can sing it. Coming soon to Stackapella.',
                'headline' => 'Got a tune in your head?',
                'lead' => 'Most people with a melody in their head can\'t arrange it. Hum a tune is being built to take you from a hum to a real a cappella recording.',
                'sections' => [
                    [
                        'heading' => 'Hum it or draw it',
                        'body' => 'You\'ll hum the tune and the app will write down the notes. Or you\'ll drag notes onto a simple grid with your finger or the mouse.',
                    ],
                    [
                        'heading' => 'Hear it harmonised',
                        'body' => 'Stackapella will write an alto, tenor and bass under your tune, and play all four with guide voices straight away.',
                    ],
                    [
                        'heading' => 'Hear it sung by real people',
                        'body' => 'You\'ll share it as an idea, with every part open. Anyone will be able to sing a part, and when someone does, we\'ll email you, at most once a day for each idea, with a setting to turn it off. You\'ll be credited as the writer, and each singer for their part. Sharing an idea, and singing one, will be free.',
                    ],
                    [
                        'heading' => 'Start today in the creator',
                        'body' => 'The creator already turns a tune you sing into four parts: sing your melody line by line, and let it suggest the rest.',
                    ],
                ],
                'soon' => 'Hum a tune comes after the song builder. Until then, the creator can harmonise a tune you sing.',
                // Once it's open (site.features.your-tune live, launch day): these words take over
                // (SitePages::page merges `now`; sections keyed by their heading). Sharing as an idea
                // stays Coming soon until the Ideas shelf opens.
                'now' => [
                    'summary' => 'Hum a tune, and hear it harmonised in four parts.',
                    'description' => 'Hum a melody or sing it on "la", and Stackapella writes the notes and suggests a harmony in four parts, sung by guide voices straight away.',
                    'lead' => 'Most people with a melody in their head can\'t arrange it. Hum it, and Stackapella writes the notes and suggests the other parts.',
                    'end' => ['title' => 'Your tune, in four parts.', 'body' => 'Hum it, and hear it harmonised.'],
                    'sections' => [
                        'Hum it or draw it' => ['heading' => 'Hum it', 'body' => 'Hum the tune, or sing it on "la", and the app writes down the notes. Or write it note by note in the song builder.'],
                        'Hear it harmonised' => ['body' => 'Stackapella writes an alto, tenor and bass under your tune, and plays all four with guide voices straight away.'],
                        'Hear it sung by real people' => ['soon' => 'Sharing it as an idea, for real singers to sing.'],
                        'Start today in the creator' => ['hide' => true],
                    ],
                ],
            ],

            'share' => [
                // A real screen of the app in the head (web/icons/help, both themes), and what it shows.
                'shot' => 'studio--share',
                'shot_alt' => 'The Share sheet in the app: the harmony\'s preview, what to share, Polished, and Create link.',
                'end' => ['title' => 'Make something worth sharing.', 'body' => 'Stack your own voices, then send a link anyone can play.'],
                'pic' => 'share',
                'name' => 'Share',
                'summary' => 'Share a link or a video with the words, or export the mix.',
                'title' => 'Share your harmony as a link, a video or a file',
                'description' => 'Share a finished harmony as a link or a vertical video with the words, or export it as WAV, MP3 or separate parts. Yours to post anywhere, credited.',
                'headline' => 'Share what you sing',
                'lead' => 'When every part is in, send it as a link, a short video with the words, or a file. Polished is on unless you turn it off.',
                'sections' => [
                    [
                        'pic' => 'listener',
                        'heading' => 'A link anyone can play',
                        'body' => 'A share link plays your harmony on any phone or computer. Only people with the link can find it, and its page offers "Learn this harmony" so friends can try it too. Post it to X or Bluesky with the post ready to go.',
                    ],
                    [
                        'pic' => 'video',
                        'heading' => 'A video with the words',
                        'body' => 'Share as a video makes a vertical video for TikTok, Reels and Shorts: each part\'s waveform moving in its colour, with the words lighting up as they\'re sung, so it works with the sound off.',
                    ],
                    [
                        'pic' => 'shot:studio--export',
                        'heading' => 'Files to keep',
                        'body' => 'Export the mix, or tracks to practise with.',
                        'points' => [
                            'The mix as WAV: free',
                            'The mix as MP3: Plus',
                            'Each part on its own: Plus',
                            'Practice tracks, everything but your part or your part louder: free',
                        ],
                    ],
                    [
                        'heading' => 'Help others learn the song',
                        'body' => 'Say yes to featuring a harmony when you share it, and it\'s public: we may choose your part as the voice everyone learns from, credited to you by your profile name. For singers 18 and over. Untick it any time.',
                    ],
                    [
                        'heading' => 'Yours to use',
                        'body' => 'Your recordings are yours. Share them anywhere, including monetised videos, credited "Arranged by Stackapella" when it\'s one of our arrangements. Nothing you sing is public unless you choose.',
                    ],
                ],
            ],

            'coaching' => [
                'end' => ['title' => 'Start with one comfortable note.', 'body' => 'The course is here today, and free to start.'],
                'pic' => 'icon:coach',
                'name' => 'Coaching',
                'summary' => 'Reviews and lessons from approved vocal coaches.',
                'title' => 'Get feedback on your singing from a vocal coach',
                'description' => 'Send a take to an approved vocal coach for a review with comments pinned to each moment, or sign up for ongoing coaching. Coming soon to Stackapella.',
                'headline' => 'A real coach, on your real takes',
                'lead' => 'The app tells you which notes landed. A coach tells you why, and what to do about it.',
                'sections' => [
                    [
                        'heading' => 'No app replaces a coach',
                        'body' => 'No app can replace a real vocal coach. Only a person can hear everything and guide you. That\'s why Stackapella is built to work with coaches: soon you\'ll be able to send a coach your takes and learn with them right here.',
                    ],
                    [
                        'heading' => 'Take reviews',
                        'body' => 'You\'ll send a take with a question. The coach will hear it with your notes and pitch line, and answer with comments pinned to moments in the song, voice notes, a demonstration and a short plan of what to practise.',
                    ],
                    [
                        'heading' => 'More than one review',
                        'body' => 'Coaches will also be able to offer monthly coaching with messages, and exercise packs for your warm-ups. Every coach will be approved by us first.',
                    ],
                    [
                        'heading' => 'Fair for both sides',
                        'body' => 'You\'ll pay in the app when you send a request. If the coach declines, doesn\'t answer or misses the deadline, you\'ll be refunded in full, automatically. Ratings will come only from finished, paid sessions. Coaching will be for singers 18 and over.',
                    ],
                    [
                        'heading' => 'Send to my teacher',
                        'body' => 'Already have a teacher? On Plus, you\'ll be able to invite them by link to review your takes in the same way, with no payment through the app. It will be for singers 18 and over.',
                    ],
                ],
                'soon' => 'Coaching isn\'t open yet. A directory for finding a coach comes later, once there are enough coaches to choose from.',
            ],

            'choirs' => [
                'end' => ['title' => 'Bring your choir in.', 'body' => 'Share a song, give out the parts, and see who\'s ready.'],
                'pic' => 'groups',
                'name' => 'Choir groups',
                'summary' => 'Directors share songs, assign parts and see who has recorded what.',
                'title' => 'Choir groups: share songs and assign parts',
                'description' => 'Directors share songs, give each singer a part, and see who has started and recorded it. Members learn at home with guide voices. On Plus, 18 and over.',
                'headline' => 'Your choir, learning at home',
                'lead' => 'Share a song with your choir, give everyone their part, and see who has it before rehearsal.',
                'sections' => [
                    [
                        'pic' => 'assign',
                        'heading' => 'Share songs, assign parts',
                        'body' => 'Invite members by link. Share any of our songs, or an arrangement you made yourself, and choose who sings what.',
                    ],
                    [
                        'heading' => 'See who\'s ready',
                        'body' => 'For each part you assign, you see whether the singer hasn\'t started, has started, or has recorded it. You never see their recordings or their contact details.',
                    ],
                    [
                        'heading' => 'Free for members',
                        'body' => 'A group comes with the director\'s Plus. Members join free, and learn the group\'s songs, Plus songs included, while they\'re in the group and the director has Plus.',
                    ],
                    [
                        'heading' => 'Who can join',
                        'body' => 'Choir groups are for singers 18 and over for now. Anyone can still learn a part on their own.',
                    ],
                ],
            ],

            'gallery' => [
                'end' => ['title' => 'Make something worth hearing.', 'body' => 'Stack your own voices today, and share it by link.'],
                'pic' => 'icon:grid',
                'name' => 'The gallery',
                'summary' => 'Hear harmonies other singers made, and sing a part they left open.',
                'title' => 'A gallery of harmonies, with parts open to join',
                'description' => 'Hear harmonies singers made on Stackapella, follow the singers you enjoy, and sing a part someone left open for you. Coming soon.',
                'headline' => 'Harmonies to hear, and parts to join',
                'lead' => 'The gallery will celebrate finished harmonies: every part sung by one person, or by friends taking turns.',
                'sections' => [
                    [
                        'heading' => 'Hear, like and follow',
                        'body' => 'You\'ll listen to harmonies singers chose to show, like them, leave a comment, and follow the singers you enjoy.',
                    ],
                    [
                        'heading' => 'Open parts',
                        'body' => 'A singer will be able to leave a part open, like the tenor, for anyone to sing. You\'ll learn it against their other parts, in the same key and tempo, and your version will credit everyone and link back.',
                    ],
                    [
                        'heading' => 'Public only by choice',
                        'body' => 'Nothing will go in the gallery unless its singer chooses, and everyone who sang on it agrees. Its parts may then become guide voices for other singers, credited. Showing anything publicly will be for singers 18 and over; younger singers share by link.',
                    ],
                ],
                'soon' => 'The gallery opens once enough singers have shared harmonies to fill it.',
            ],

            'leagues' => [
                'end' => ['title' => 'A little every day.', 'body' => 'Today\'s workout and the course are here now, free to start.'],
                'pic' => 'icon:flag',
                'name' => 'Leagues',
                'summary' => 'Weekly leagues ranked by practice, not talent, and a table of friends.',
                'title' => 'Singing leagues ranked by practice, not talent',
                'description' => 'Opt-in weekly leagues of singers at your level, ranked by how much you practise rather than accuracy, plus a table of your friends. Coming soon.',
                'headline' => 'A little friendly competition',
                'lead' => 'Leagues will reward showing up. You\'ll climb by practising, so a beginner who practises keeps pace with anyone.',
                'sections' => [
                    [
                        'heading' => 'How a league will work',
                        'body' => 'Each week you\'ll be grouped with singers at a similar level.',
                        'points' => [
                            'You choose to join; it\'s never on by default',
                            'Ranked by XP from practice, never by accuracy',
                            'The top singers move up each week',
                            'Nobody moves down',
                        ],
                    ],
                    [
                        'heading' => 'Friends',
                        'body' => 'A friends table will rank the people you follow by this week\'s practice. It will show names, avatars, levels and XP, never scores or recordings.',
                    ],
                    [
                        'heading' => 'Kept kind',
                        'body' => 'Singers under 18 will see their friends only, not public leagues. There will be no prizes, so it stays about practice.',
                    ],
                ],
                'soon' => 'Leagues open after the gallery, once enough singers practise each week to fill them.',
            ],

            'library' => [
                'try' => ['Try the creator', 'create'],
                'end' => ['title' => 'Arrange a song of your own.', 'body' => 'The creator is here today, and free to start.'],
                'pic' => 'icon:book',
                'name' => 'The library',
                'summary' => 'Arrangements made by singers, to find and learn.',
                'title' => 'A library of harmony arrangements made by singers',
                'description' => 'Search arrangements other singers have made, by difficulty, parts and range, and learn them part by part. Coming soon to Stackapella.',
                'headline' => 'Arrangements from everyone',
                'lead' => 'Our own songs are only the start. The library will hold arrangements singers make in the creator and choose to share.',
                'sections' => [
                    [
                        'heading' => 'Find one to learn',
                        'body' => 'You\'ll search and filter by difficulty, number of parts, key, tempo and voice range, then learn any of them part by part, like our own songs.',
                    ],
                    [
                        'heading' => 'Credit where it\'s due',
                        'body' => 'Every arrangement will credit the person who made it, and so will everything learned from it. Remix one into your own, and it will link back to the original.',
                    ],
                    [
                        'heading' => 'Share yours',
                        'body' => 'Making an arrangement is free. When you save one, you can already choose to list it in the library when it opens. Each one must be your own arrangement of a song that\'s yours or free to use.',
                    ],
                ],
                'soon' => 'The library opens once enough people have offered arrangements to fill it.',
            ],

            'virtual-choir' => [
                'end' => ['title' => 'Add your voice today.', 'body' => 'Learn a part, then stack it with friends.'],
                'pic' => 'icon:users',
                'name' => 'Virtual choir',
                'summary' => 'Add your part to a song\'s choir of many voices.',
                'title' => 'Join a virtual choir with your part',
                'description' => 'Add your recorded part to a song\'s virtual choir, one voice among many, all in time. Leave whenever you like. Coming soon to Stackapella.',
                'headline' => 'One voice among many',
                'lead' => 'Each of our songs will have its own choir. Record your part, add it, and hear yourself inside a crowd of singers.',
                'sections' => [
                    [
                        'heading' => 'How it will work',
                        'body' => 'After you record a part, you\'ll be able to add it to that song\'s choir. Every voice will be in the same key and tempo, so they all line up, and the choir will grow as people join. Hear my voice louder will bring yours forward.',
                    ],
                    [
                        'heading' => 'Kind to beginners',
                        'body' => 'Nobody will hear your voice on its own. Before you join, a quick check on your device will make sure your take is in tune and clean, and if it isn\'t yet, it will tell you which phrase to practise.',
                    ],
                    [
                        'heading' => 'Yours to take back',
                        'body' => 'You\'ll be credited by name, or stay anonymous if you prefer. Leave whenever you like, and the next mix will go on without you.',
                    ],
                ],
                'soon' => 'Each song\'s choir opens once enough singers want to join it.',
            ],

            'song-requests' => [
                'end' => ['title' => 'Sing one of ours today.', 'body' => 'Each song is arranged in parts to learn one at a time.'],
                'pic' => 'icon:vote',
                'name' => 'Song requests',
                'summary' => 'Ask for a song, and vote for the ones you want next.',
                'title' => 'Request a song to learn in harmony',
                'description' => 'Ask for the songs you want to sing in harmony, vote for other people\'s requests, and follow each one until it\'s ready to learn. Coming soon.',
                'headline' => 'Tell us what to arrange next',
                'lead' => 'You\'ll request a song, or vote for one someone already asked for. The most wanted will help decide what we arrange next.',
                'sections' => [
                    [
                        'heading' => 'Ask, or vote',
                        'body' => 'You\'ll request a song with a line about why you want it. If someone already asked, you\'ll vote for theirs instead, so the count means something.',
                    ],
                    [
                        'heading' => 'Follow it along',
                        'body' => 'Every request will show where it stands, and you\'ll be told in the app when yours is ready.',
                        'points' => [
                            'Requested',
                            'Checking it\'s free to use',
                            'Being arranged',
                            'Ready to learn',
                        ],
                    ],
                    [
                        'heading' => 'Songs free to use',
                        'body' => 'We arrange songs that are in the public domain, and our own. A song still under copyright will have to wait, and its request will say so.',
                    ],
                ],
                'soon' => 'Requests open once enough singers practise each week to make the votes count.',
            ],

            'apps' => [
                'end' => ['title' => 'It runs in your browser today.', 'body' => 'Install it from your browser, and it opens like an app.'],
                'try' => ['Install it from your browser', '/help/install'],
                'pic' => 'icon:device',
                'name' => 'Apps',
                'summary' => 'Stackapella for iPhone, Android and desktop.',
                'title' => 'Stackapella apps for iPhone, Android and desktop',
                'description' => 'Stackapella apps for iPhone and Android are being built, then desktop. Today it runs in your browser and installs to your home screen.',
                'headline' => 'Stackapella, as an app',
                'lead' => 'Apps for iPhone and Android are being built now, with apps for Mac, Windows and Linux after them.',
                'sections' => [
                    [
                        'heading' => 'Built for the shortest delay',
                        'body' => 'The apps will aim for the lowest delay each device allows, so hearing yourself and the other parts feels like singing together.',
                    ],
                    [
                        'heading' => 'Use it today',
                        'body' => 'You don\'t need to wait. Stackapella runs in your browser and installs to your home screen like an app. On iPhone, open it in Safari, tap Share, then Add to Home Screen.',
                    ],
                    [
                        'heading' => 'The same rules',
                        'body' => 'Like the web app, the apps will be for people 13 and over.',
                    ],
                ],
                'soon' => 'The apps are being built. Store links will appear here when they\'re ready.',
            ],
        ],
    ],

    // For conductors and teachers (pages/landing and the directors' and coaches' for-pages): the tool
    // under the game. In the game skin it's the Intermission, where the game pauses and the lights
    // come up (Hillel: "break out of the game for a bit, then fall back into it").
    'teams' => [
        'paused' => 'Paused',
        'intermission' => 'Intermission',
        'kick' => 'For conductors and teachers',
        'title' => 'A serious tool for your singers.',
        'lead' => 'Under the game is a practice tool built for rehearsals: every part to learn at home, honest feedback as they sing, and a place to see who has practised.',
        'quote' => 'Learn your part at home, so rehearsal is for singing together.',
        'items' => [
            ['Learning tracks for every part', 'Each part has a guide voice, in a key that fits and slower while it\'s new. Export a practice track with one part louder, or left out, for free.'],
            ['Practice at home, with honest feedback', 'Singers learn their part a phrase at a time, and see how close they are as they sing, note by note.'],
            ['Choir groups', 'With Plus, share songs with your choir, give each singer a part, and see who has started and who has recorded it. You never see their recordings or contact details. For singers 18 and over.'],
            ['Record and stack', 'Record each part over the others, exactly in time, then mix and export: WAV for free, MP3 and each part on its own with Plus.'],
            ['Your own arrangements', 'Write a harmony in the song builder, or start from a copy of one of ours, and share it for your singers to learn.'],
            ['The course as homework', 'Short lessons from a first note to holding a part on your own. Units 1 to 7 are free.'],
        ],
        'coaching_open' => ['Coaching', 'Vocal coaches can apply now. Finding a coach in the app comes once there are enough of them.'],
        'coaching_soon' => ['Coaching', 'Working with a vocal coach in the app is coming soon.'],
        'shot_alt' => 'The studio: four parts stacked, each with solo and mute, one soloed.',
        'groups' => 'See choir groups',
        'directors' => 'For directors',
        'continue' => 'Press start to continue',
    ],

    'for' => [
        'index' => [
            'title' => 'Who Stackapella is for',
            'description' => 'Beginners, choir singers and directors, a cappella singers, songwriters, players and families: how Stackapella helps each one sing harmony.',
            'headline' => 'For anyone who wants to sing harmony',
            'lead' => 'Whether you\'ve never sung a harmony or you lead a choir, the same simple idea works: learn one part, then the next.',
        ],
        'pages' => [
            'beginners' => [
                // A real screen of the app in the head (web/icons/help, both themes), and what it shows.
                'shot' => 'your-voice--map',
                'shot_alt' => 'The course map in the app: the next lesson, today\'s workout, and the week\'s days with your goal.',
                'pic' => 'course',
                'end' => ['title' => 'Start with one comfortable note.', 'body' => 'Lesson 1 takes a minute, and it\'s free.'],
                'name' => 'Beginners',
                'summary' => 'Never sung harmony? Start here.',
                'title' => 'Learn to sing harmony as a complete beginner',
                'description' => 'Never sung harmony? Start with short lessons, a guide voice for every part and kind feedback. No music reading, no account to start. Free in your browser.',
                'headline' => 'Never sung harmony? Start here',
                'lead' => 'You don\'t need to read music, or know which notes to sing. Stackapella shows you, one part at a time.',
                'sections' => [
                    [
                        'heading' => 'Start with one note',
                        'body' => 'The course begins with a note you sing comfortably, and finds your range as you sing. Then it builds, one short lesson at a time: steady notes, moving notes, a tune, then harmony.',
                    ],
                    [
                        'pic' => 'lane',
                        'heading' => 'Kind feedback',
                        'body' => 'Your notes light up as you hit them, and a missed note never turns red. In the course\'s practice steps, the music waits while you find each note. After each try you get a kind word first, then one thing to try.',
                    ],
                    [
                        'heading' => 'Your first harmony, free',
                        'body' => 'Units 1 to 7 of the course are free. They end with you singing all four parts of Amazing Grace yourself, stacked in time.',
                    ],
                    [
                        'heading' => 'What you need',
                        'body' => 'Less than you might think.',
                        'points' => [
                            'The mic in your phone or laptop',
                            'Headphones, once you record over other parts',
                            'Nothing to install, and no account to start',
                        ],
                    ],
                ],
                'features' => ['course', 'learn-a-part', 'real-voices', 'studio', 'share'],
            ],

            'choir-singers' => [
                'pic' => 'modes',
                'end' => ['title' => 'Arrive knowing your part.', 'body' => 'Learn it at home with a guide voice, then sing it with everyone.'],
                'name' => 'Choir singers',
                'summary' => 'Learn your part at home before rehearsal.',
                'title' => 'Learn your choir part at home before rehearsal',
                'description' => 'Learn your part by ear at home, with a guide voice, slow practice and practice tracks, and arrive at rehearsal knowing it.',
                'headline' => 'Know your part before rehearsal',
                'lead' => 'Pick your part, hear it sung, and sing it against the others until it sits. Then walk into rehearsal knowing it.',
                'sections' => [
                    [
                        'heading' => 'Your part, alone or in the mix',
                        'body' => 'Listen to your part, sing along with it, then sing it on your own while the other parts carry on around you. Stuck on a phrase? Loop it, or slow it down.',
                    ],
                    [
                        'pic' => 'share',
                        'heading' => 'Practice tracks',
                        'body' => 'Export a track of everything but your part, to sing along with, or of your part louder, the way choirs use them. Both are free.',
                    ],
                    [
                        'heading' => 'When your director uses Stackapella',
                        'body' => 'In a choir group, your director shares the songs and tells you your part. You join free, and the group\'s songs are open to you while you\'re in it. Your director sees only whether you\'ve started or recorded your part. Choir groups are for singers 18 and over.',
                    ],
                ],
                'features' => ['learn-a-part', 'real-voices', 'choirs', 'course', 'share'],
            ],

            'choir-directors' => [
                'pic' => 'groups',
                'end' => ['title' => 'Choir groups come with Plus.', 'body' => 'Start a group with Plus, and your singers join free.', 'plus' => true],
                'name' => 'Directors and teachers',
                'summary' => 'Share songs with your singers, assign parts and see who\'s ready.',
                'title' => 'For choir directors: assign parts and see who\'s ready',
                'description' => 'Share songs with your choir, assign each singer a part and see who has recorded it before rehearsal. Write your own arrangements too. On Plus.',
                'headline' => 'Rehearsals that start with the notes learned',
                'lead' => 'Your singers learn their parts at home, with a guide voice. You see who\'s ready before anyone walks in.',
                'sections' => [
                    [
                        'heading' => 'A group for your choir',
                        'body' => 'Make a choir group, invite your singers by link, share songs and choose who sings what. A group comes with your Plus, and members join free.',
                    ],
                    [
                        'heading' => 'See who has their part',
                        'body' => 'For each part you assign, you see not started, started or recorded. You never see a singer\'s recordings or contact details.',
                    ],
                    [
                        'pic' => 'create',
                        'heading' => 'Your own arrangements',
                        'body' => 'Write the arrangement your choir sings in the creator: sing the tune, get the other parts suggested, and change any note. Share it with your group like any of our songs.',
                    ],
                    [
                        'notice' => true,
                        'heading' => 'Teachers and younger singers',
                        'body' => 'Choir groups are for singers 18 and over for now, so they suit adult and community choirs. Singers from 13 can still learn any part on their own, and send you a link or a practice track.',
                    ],
                ],
                'features' => ['choirs', 'create', 'learn-a-part', 'real-voices', 'share'],
            ],

            'a-cappella' => [
                'pic' => 'stack',
                'end' => ['title' => 'Stack every part yourself.', 'body' => 'Record one part, then the next over it, exactly in time.'],
                'name' => 'A cappella',
                'summary' => 'Stack every part of an a cappella song yourself.',
                'title' => 'Stack your own a cappella harmonies, exactly in time',
                'description' => 'Sing every part of an a cappella arrangement yourself, line them up exactly, polish the mix, and share it as a link or a video with the words.',
                'headline' => 'Be the whole group',
                'lead' => 'Record the bass, then the tenor, then the rest, each one exactly in time with the last. Then make it sound good and share it.',
                'sections' => [
                    [
                        'heading' => 'Exact timing',
                        'body' => 'A timing check measures your setup\'s delay, so every part lands where you sang it. Trim the edges, set levels and pan, and nudge any take by ear.',
                    ],
                    [
                        'heading' => 'Arrange your own',
                        'body' => 'Write an arrangement in the creator, or start from a copy of one of ours. In the studio, any part you add can be a beatbox or an instrument.',
                    ],
                    [
                        'pic' => 'share',
                        'heading' => 'Made to be shared',
                        'body' => 'Share as a video for TikTok, Reels and Shorts, with each part\'s waveform and the words. Export the mix, or each part on its own with Plus. Post it anywhere, monetised too, credited "Arranged by Stackapella" when it\'s our arrangement.',
                    ],
                ],
                'features' => ['studio', 'share', 'create', 'learn-a-part', 'gallery'],
            ],

            'songwriters' => [
                'pic' => 'create',
                'end' => ['title' => 'Turn your tune into harmony.', 'body' => 'Sing it into the creator today, and hear the other parts suggested.'],
                'name' => 'Songwriters',
                'summary' => 'Got a tune in your head? Turn it into harmony.',
                'title' => 'Turn the tune in your head into a four-part harmony',
                'description' => 'Sing a melody, or sing over chords, and hear it harmonised in four parts, then share it for friends to learn. Humming a tune is coming.',
                'headline' => 'Got a tune in your head?',
                'lead' => 'Sing it to Stackapella, and hear it come back with an alto, tenor and bass under it.',
                'sections' => [
                    [
                        'heading' => 'Today: sing it into the creator',
                        'body' => 'Paste your words, sing the melody a line at a time, and the app writes down the notes. Then it suggests the other parts, and you can change any chord or note.',
                    ],
                    [
                        'pic' => 'share',
                        'heading' => 'Share it to be sung',
                        'body' => 'Share your arrangement by link, and friends learn it part by part. Keeping your own voice as the guide is coming to Plus.',
                    ],
                    [
                        'heading' => 'Coming soon: hum a tune',
                        'body' => 'You\'ll hum or draw a tune, hear it harmonised, and share it as an idea for real singers to sing, credited to you as the writer.',
                    ],
                    [
                        'heading' => 'Today: start from chords',
                        'body' => 'Start from a chord progression or a 12-bar blues in your key, sing over a simple backing, and the chords become the harmony.',
                        'soon' => 'A chorus and a bridge suggested to go with your verse.',
                    ],
                ],
                'features' => ['create', 'your-tune', 'share', 'real-voices', 'library'],
            ],

            'instrumentalists' => [
                'pic' => 'mix',
                'end' => ['title' => 'Play a part, then sing the rest.', 'body' => 'Record your instrument first, then stack your voices over it.'],
                'name' => 'Singers who play',
                'summary' => 'An instrument can be any part.',
                'title' => 'Sing and play: stack voices and instruments together',
                'description' => 'Record a ukulele, guitar, cello or beatbox as its own part, and stack it with your voice, exactly in time. Singing leads, and instruments are welcome.',
                'headline' => 'Bring your instrument',
                'lead' => 'Stackapella leads with singing, but any part can be an instrument. Record it in the studio, name it, and stack it with the voices.',
                'sections' => [
                    [
                        'pic' => 'parts',
                        'heading' => 'Any part, any instrument',
                        'body' => 'Record a ukulele, a guitar, a cello or a beatbox as its own part. Name it, set its level and pan, and trim it like any voice.',
                    ],
                    [
                        'heading' => 'The same exact timing',
                        'body' => 'The timing check covers every part you record, so your playing lines up with your singing.',
                    ],
                    [
                        'heading' => 'Play and sing',
                        'body' => 'Lay down your chords first, then sing every harmony over them. Our own songs are written for voices, and instrument parts in the arrangements you make in the creator are coming.',
                    ],
                ],
                'features' => ['studio', 'create', 'share', 'learn-a-part'],
            ],

            'friends-and-family' => [
                'pic' => 'stack',
                'end' => ['title' => 'Sing a harmony together.', 'body' => 'Send a link, and each person records their part when it suits them.'],
                'name' => 'Friends and family',
                'summary' => 'Sing a harmony together, taking turns.',
                'title' => 'Sing a harmony with friends and family, taking turns',
                'description' => 'Invite friends or family to sing the other parts of your song. Everyone records in their own time, and the parts line up exactly. Accounts from 13.',
                'headline' => 'Sing together, in your own time',
                'lead' => 'Record your part, then invite friends or family to add theirs. Nobody needs to be in the same room, or online at the same time.',
                'sections' => [
                    [
                        'pic' => 'share',
                        'heading' => 'Invite by link',
                        'body' => 'Send a link to your song, and each person records their part when it suits them. Every part lines up exactly, wherever it was sung.',
                    ],
                    [
                        'heading' => 'Songs to start with',
                        'body' => 'Try a round like Are You Sleeping, or Happy Birthday for the next birthday. Both are free, with a guide voice for every part.',
                    ],
                    [
                        'notice' => true,
                        'heading' => 'Who can join',
                        'body' => 'Everyone who sings on a song needs their own account, from 13 years old. Plus makes room for more friends on each song.',
                    ],
                    [
                        'heading' => 'Taking turns, on purpose',
                        'body' => 'Stackapella doesn\'t sing live over the internet, where delays would pull the parts apart. Taking turns keeps every part exactly in time.',
                    ],
                ],
                'features' => ['learn-a-part', 'studio', 'share', 'real-voices'],
            ],

            'coaches' => [
                // While coaching is coming: write to us (Hillel, 2026-10-29: a plain email link, no list kept).
                'try' => ['Write to us to be among the first', 'mailto::email'],
                'pic' => 'icon:coach',
                'name' => 'Vocal coaches',
                'summary' => 'Coaching is coming: review singers\' takes through the app, and get paid for it.',
                'title' => 'For vocal coaches: teach and earn through Stackapella',
                'description' => 'Coaching is coming to Stackapella: approved vocal coaches will sell take reviews, monthly coaching and exercise packs at their own prices. Write to us to be first.',
                'headline' => 'Coach singers, and get paid for it',
                'lead' => 'Stackapella shows singers which notes landed. Soon you\'ll be able to show them why, and how to sing it better.',
                'sections' => [
                    [
                        'heading' => 'Why we built it for coaches',
                        'body' => 'We don\'t think an app is a good replacement for a vocal coach. The app shows singers which notes landed; only a person can hear everything and guide them. So Stackapella is built for singers to work with you directly in it, once coaching opens.',
                    ],
                    [
                        'heading' => 'What you\'ll be able to sell',
                        'body' => 'You\'ll set your own prices and turnaround.',
                        'points' => [
                            'Take reviews',
                            'Monthly coaching, with reviews and messages',
                            'Exercise packs for singers\' warm-ups',
                        ],
                    ],
                    [
                        'heading' => 'A workspace made for voices',
                        'body' => 'You\'ll hear the take with the singer\'s notes and pitch line, and answer with comments pinned to moments, voice notes and a demonstration. The app\'s own scores will be there as a guide only.',
                    ],
                    [
                        'heading' => 'How you\'ll be paid',
                        'body' => 'Singers will pay in the app, and Stripe will pay you out. Stackapella will keep :commission of each sale, and active coaches will get Plus free.',
                    ],
                    [
                        'heading' => 'Be among the first',
                        'body' => 'Coaching opens soon. If you\'d like to be among the first coaches, write to :email and tell us about your experience. Coaches will be 18 and over. When coaching opens, singers will reach you through your own profile link, and a directory comes later.',
                    ],
                ],
                'features' => ['coaching', 'learn-a-part', 'course'],
            ],
        ],
    ],

    // How it works (/how-it-works; Hillel: "make them consistent so it's on its own page", like Pricing
    // and Help): the landing's four steps, opened out.
    'how' => [
        'title' => 'How Stackapella works',
        'description' => 'Learn a harmony part by ear, record it over the others, and share the whole song: how Stackapella works, step by step.',
        'eyebrow' => 'How it works',
        'headline' => 'Learn a part. Sing it with the others. Share the harmony.',
        'lead' => 'Stackapella teaches you a harmony part by ear, then lets you record every part of a song yourself, one voice at a time, in your browser.',
        'shot_alt' => 'The Sing screen: the part\'s notes as shapes on a lane with their words, the note to sing now, and each note ticked as you hit it.',
        'first' => [
            'kick' => 'Your first lesson',
            'title' => 'From your first note to a harmony',
            'steps' => [
                ['Press Start', 'No account and nothing to install. It opens in your browser, on a phone or a computer.'],
                ['Find your comfortable note', 'Sing any easy note. Every exercise after it is fitted to your voice.'],
                ['Learn a part by ear', 'Listen to a guide voice, sing along with it, then sing on your own. Your notes light up as you hit them, and you never need to read music.'],
                ['Stack it and share it', 'Record each part over the ones before, with a quick timing check, then share a link or a short video.'],
            ],
        ],
        'hear' => [
            'kick' => 'What you hear',
            'title' => 'Four voices, and one of them is yours.',
            'lead' => 'Every song comes in four parts. Press Play, then switch voices off or bring one forward to hear how the harmony is built.',
            'points' => [
                'The soprano usually sings the tune; the alto, tenor and bass sing the harmony around it.',
                'You choose the part that suits your voice, and you can move the key to fit you.',
                'Most songs start with a clear practice voice, and real singers sing some of the parts.',
            ],
        ],
        'need' => [
            'kick' => 'What you need',
            'title' => 'Less than you think.',
            'points' => [
                'A phone or a computer with a browser. On an iPhone, Safari 16.4 or newer.',
                'The mic it already has. Headphones matter more than the mic.',
                'Headphones when you record over the other parts, so they stay out of your take. Wired ones keep the timing tightest.',
                'No account and no card to start, and the beginner course is free.',
            ],
            'more' => 'More answers in Help',
        ],
    ],

    'about' => [
        'title' => 'About Stackapella',
        'description' => 'Why Stackapella exists, what it cares about, and who makes it.',
        'headline' => 'About Stackapella',
        'lead' => 'Stackapella is for anyone who wants to sing harmony and isn\'t sure which notes to sing.',
        // Hillel's note, in his words (approved as is, 2026-10-27). :email is the contact address, linked.
        'eyebrow' => 'About Stackapella',
        'note_heading' => 'Why I made Stackapella',
        'note' => [
            'Hi, I\'m Hillel. I love harmony: the moment a few voices lock together and the chord rings. But singing harmony on your own is hard. It\'s hard to know which notes are yours, and harder still to hold them while the tune pulls you away.',
            'So I built Stackapella. Each part has a guide voice to learn from, and your notes light up as you hit them. When you\'re ready, you record your part, then sing the next one over it. Every part lines up exactly in time, so you can stack your own voices into a whole harmony, or share it with friends and your choir.',
            'A few things matter to me. What you sing stays private unless you choose to share it. Feedback is kind: what went well first, then one thing to try. There\'s no third-party analytics and nothing tracks you: the app only counts, anonymously, how much it\'s used each day. And it\'s voices first, with instruments welcome.',
            'The songs are in the public domain, arranged for learning, and some parts are sung by real singers, each credited by name. What you record with them is yours.',
            'If something\'s confusing, or you\'d love it to do something it doesn\'t, write to me at :email. I read every message.',
        ],
        'signature' => ':founder',
        // The note's own promises, on the band (Hillel, 2026-10-29: yes), in its words.
        'promises_kick' => 'What matters to me',
        'promises_title' => 'A few things matter to me.',
        'promises' => [
            'What you sing stays private unless you choose to share it.',
            'Feedback is kind: what went well first, then one thing to try.',
            'There\'s no third-party analytics and nothing tracks you: the app only counts, anonymously, how much it\'s used each day.',
            'The songs are in the public domain, arranged for learning, and some parts are sung by real singers, each credited by name.',
            'What you record with them is yours.',
        ],
        'facts_title' => 'Stackapella',
        'facts' => ['address' => 'Address', 'contact' => 'Write to us', 'press' => 'Press'],
        'company_title' => 'The company',
        'press' => 'Press kit',
        'company' => 'Stackapella, 14799 Soaring Eagle Ct, Fort Myers, FL 33912, USA.',
        // Shown once Stackapella is open source (the open_source flag).
        'open_source_title' => 'Open source',
        'open_source' => 'Stackapella\'s code is open source, under the Attribution Assurance License: the server and the web app. Read it, run it yourself or build on it. The songs and the course are shared under CC BY-SA 4.0; only the singers\' recorded voices and the name stay ours.',
        'open_source_more' => 'What\'s open source',
        'mudeo' => [
            'heading' => 'For mudeo singers',
            'body' => [
                'Stackapella grew out of mudeo. On video, the parts of a harmony never quite lined up, so mudeo closed and Stackapella was built for sound alone, where every part lands exactly in time.',
                'mudeo accounts and videos didn\'t move over, so everyone starts fresh here. If you\'d like a copy of your mudeo videos, write to contact@stackapella.com.',
                'Thank you for every harmony you made there.',
            ],
        ],
    ],

    'press' => [
        'title' => 'Press kit',
        'description' => 'Stackapella in a sentence and a paragraph, the facts, the logo and screenshots, and who to contact.',
        'headline' => 'Press',
        'lead' => 'Writing about Stackapella? Here\'s what it is, the facts, and who to ask.',
        'one_liner' => 'Stackapella is harmony, made by you, for one voice or a whole choir: learn your part, write a song, or sing with others, in your browser.',
        'short' => 'Stackapella is a web app for learning and stacking vocal harmony. A guide voice, a real singer where one has recorded the part, shows you each part, and your notes light up as you hit them. Record your part, then the next over it, exactly in time, and share the finished harmony as a link or a video.',
        'facts' => [
            'What it is' => 'A web app for learning and stacking vocal harmony',
            'Where it runs' => 'In the browser on phones, tablets and computers, and installs to the home screen',
            'Price' => 'Free to start. Plus from :price a month.',
            'Songs' => 'Public-domain songs, arranged for learning by Stackapella',
            'Ages' => 'Accounts from 13. Choir groups from 18.',
            'Made by' => ':founder',
            'Company' => 'Stackapella, Fort Myers, Florida, USA',
            'Typeface' => 'Figtree, from Google Fonts (SIL Open Font License)',
        ],
        // Added to the facts once Stackapella is open source (the open_source flag).
        'open_source_label' => 'Code',
        'open_source_fact' => 'Open source, under the Attribution Assurance License: :repo',
    ],

    // What's new (/whats-new): the frame; the entries are resources/whats-new/en.json.
    'whats_new' => [
        'title' => 'What\'s new in Stackapella',
        'description' => 'What\'s new in Stackapella: every change to the app and the site, newest first, day by day.',
        'headline' => 'What\'s new',
        'lead' => 'What\'s changed, newest first.',
    ],
    'press_words' => [
        'one_liner' => 'In a sentence',
        'short' => 'In a paragraph',
        'facts' => 'The facts',
        'logo' => 'The logo',
        'logo_svg' => 'The mark (SVG)',
        'wordmark_light' => 'The wordmark for light backgrounds (SVG)',
        'wordmark_dark' => 'The wordmark for dark backgrounds (SVG)',
        'stacked_light' => 'Stacked, for light backgrounds (SVG)',
        'stacked_dark' => 'Stacked, for dark backgrounds (SVG)',
        'wordmark_png' => 'The wordmark, 1200 wide (PNG)',
        'mark_png' => 'The mark, 1024 square (PNG)',
        'card' => 'The link picture (PNG, 1200 by 630)',
        'contact' => 'Contact',
        'screenshots' => 'Screenshots',
        'screenshots_lead' => 'Real screens from the app, in its light and dark themes. Use them when you write about Stackapella.',
        'light' => 'Light',
        'dark' => 'Dark',
        // The press kit's screenshots (the user guide's, web/icons/help), each with its caption.
        'shots' => [
            'getting-started--home' => 'The home screen: the course and your songs',
            'learn-a-part--stage' => 'Learning a part: the words light up as you sing them',
            'learn-a-part--notes' => 'The part\'s notes, ticked as you hit them, on a wider screen',
            'studio--overview' => 'The studio: every part stacked, exactly in time',
            'studio--mixer' => 'Mixing the parts, ready to share',
            'your-voice--map' => 'The course map, a unit at a time',
        ],
    ],

    'coming_soon' => 'Coming soon',

    // Open source (docs/open-source-plan.md, 5.2 and 5.3): shown only with the open_source flag on.
    // The features page's "Built in the open", once Stackapella is open source (the open_source flag).
    'features_open' => [
        'title' => 'Built in the open',
        'lead' => 'Stackapella is open source. Here\'s what that means for you, and for anyone curious how it works.',
        'points' => [
            'The server and the web app are open source, under the Attribution Assurance License.',
            'Every part is scheduled on the browser\'s audio clock, so your takes line up exactly in time.',
            'Your pitch is heard on your device, and nothing tracks you: no third-party analytics, only anonymous daily counts. Check the code.',
            'No npm and no build step: a Laravel API and a Vue app served as plain files.',
            'Run it on your own server, with every Plus feature, free.',
        ],
        'more' => 'What\'s open source',
        'selfhost' => 'Run it yourself',
    ],

    'open_source' => [
        'title' => 'Stackapella is open source',
        'description' => 'The server, the web app and the native apps, under the Attribution Assurance License. What\'s open, what isn\'t, and how it works.',
        'eyebrow' => 'Open source',
        'headline' => 'Stackapella is open source',
        'lead' => 'The server, the web app and the native apps, under the Attribution Assurance License: use it, change it, run it, even sell it, as long as the credit stays.',
        'github' => 'View on GitHub',
        'run_it' => 'Run it yourself',
        'open_title' => 'What\'s open, and what isn\'t',
        'open' => [
            ['All of the code', 'The Laravel server and the Vue web app, with their tests and the API contract the apps use.'],
            ['The songs and the course, shared too', 'The course, the song arrangements, the singing guides and help are under CC BY-SA 4.0: use and adapt them, credited "Stackapella (https://stackapella.com)", and share what you make the same way.'],
            ['Not the voices or the name', 'The singers\' recorded voices (the guide voices and the course clips) and the Stackapella name and logo stay ours, under their own terms and the trademark policy.'],
        ],
        'content_licence' => 'Content licence (CC BY-SA 4.0)',
        'trademark' => 'The trademark policy',
        'code_title' => 'How it works, in the code',
        'code_lead' => 'The parts that make harmony line up, if you want to read them.',
        'code' => [
            ['web/engine/engine.js', 'Every part is scheduled on the AudioContext clock, never with timers, so the voices land on the same sample.'],
            ['web/engine/recorder-worklet.js', 'The recorder takes exactly the frames asked for, so a take starts where the others are.'],
            ['web/js/dsp.js', 'The timing check plays chirps and finds them again by cross-correlation, to measure your device\'s delay.'],
            ['web/js/pitchtrack.js', 'Pitch is heard on your device, in a worker, so your singing isn\'t sent anywhere to be scored.'],
        ],
        'quiet_title' => 'Quiet by design',
        'quiet' => [
            'No third-party analytics or tracking, and no licence check. Your install sends nothing to us.',
            'A fresh install calls out to no one on its own: even the check of Cloudflare\'s addresses runs only behind Cloudflare. Its pages load Vue (and lamejs, for an MP3 export) from jsDelivr, pinned to exact versions with integrity hashes, and the Figtree and Noto Music fonts from Google Fonts.',
            'Check any of this yourself: it\'s all in the code.',
        ],
        'npm_title' => 'No npm',
        'npm' => [
            'No package.json and no build step. Libraries load from a CDN, pinned with a subresource integrity hash.',
            'Tailwind runs as its standalone binary, and Vue compiles its templates in the browser.',
            'The cost is a little speed on first load; the gain is a web app anyone can read, change and serve as files.',
        ],
        'licence_title' => 'The licence in plain words',
        'licence' => [
            'The Attribution Assurance License is approved by the Open Source Initiative.',
            'It asks one thing: keep the credit, Stackapella by Hillel Coren with stackapella.com, where people can see it. Our footer carries it, and a self-hosted install keeps it.',
            'It doesn\'t let you use the Stackapella name to promote your own version.',
        ],
        'licence_link' => 'Read the licence',
        'involved_title' => 'Get involved',
        'involved' => 'Report a bug or ask for something on GitHub, and read the contributing guide before a pull request: what you send is under the same licence, and the songs, the course and the guides take no pull requests. Found a security problem? Write to us privately, as the security policy says.',
        'issues' => 'Issues on GitHub',
        'contributing' => 'How to contribute',
        'security' => 'The security policy',
        'notices' => 'Every library, font, sound and sample set we use, credited',
        'terms' => 'Self-hosting terms',
    ],

    'selfhost' => [
        'title' => 'Run Stackapella yourself',
        'description' => 'Every Plus feature for every account, free, on your own server. What you need, how to install it, and what isn\'t included.',
        'eyebrow' => 'Self-host',
        'headline' => 'Run Stackapella yourself',
        'lead' => 'Every Plus feature for every account, free, on your own server.',
        'need_title' => 'What you need',
        'need' => [
            'PHP 8.3 or 8.4 with gd, intl, pdo_mysql, bcmath, pcntl and zip, and Composer.',
            'MySQL 8.4. Only MySQL: SQLite and MariaDB aren\'t supported.',
            'ffmpeg, which encodes takes, shared harmonies and share videos on the server.',
            'HTTPS. Browsers give a page the microphone, audio worklets, the service worker, passkeys and push notifications only on a secure page. localhost counts, for trying it out.',
            'S3-compatible object storage with CORS and presigned uploads (DigitalOcean Spaces, AWS S3, Cloudflare R2 or MinIO), for media, private files and backups. Uploads to the local disk are for development only.',
            'Two background processes: the scheduler, which also works through the default queue, and a worker for the long queue.',
            'Mail, through any mailer Laravel supports.',
            'Our beta runs on 1 vCPU and 1 GB for the web, 1 GB for the long worker and 0.5 GB for the scheduler.',
        ],
        'install_title' => 'Install it',
        'install' => [
            'Clone the repo and run composer install --no-dev in server.',
            'Copy .env.example to .env, and set the database, the storage buckets, mail, APP_URL and SELF_HOSTED=true.',
            'Run php artisan key:generate, and set your own SQIDS_ALPHABET: never the default, and never changed once links are out.',
            'Run php artisan migrate --force, then php artisan app:templates:sync to put the songs in the catalogue.',
            'Point the web root at server/public, which serves the app from web/ beside it, and start the scheduler and the long worker.',
            'Run php artisan app:doctor: every readiness check, with its fix.',
        ],
        'unlocked_title' => 'What SELF_HOSTED=true changes',
        'unlocked' => [
            'Everything is unlocked: every account has every Plus feature, with no plans and no licence key. The limits are yours to set in server/config/usage.php.',
            'Your own legal pages: your terms, privacy policy and the rest, as Markdown files in server/resources/legal/local/en/, or another folder with SELF_HOSTED_LEGAL_PATH. Until you write one, its page says it isn\'t written yet. Stackapella\'s own are never shown.',
            'Your own addresses: SITE_CONTACT_EMAIL, SITE_FOUNDER, SITE_FOUNDER_URL, SITE_X_URL and SITE_BLUESKY_URL (none hides a link), SECURITY_CONTACT for security.txt, and the mail settings for what you send from.',
            'The credit the licence asks for, Stackapella by Hillel Coren with stackapella.com, stays in every page\'s footer.',
            'The admin, with each network feature (the gallery, leagues, a virtual choir and more) a switch you turn on.',
        ],
        'missing_title' => 'What isn\'t included',
        'missing' => [
            'The official songs and the course aren\'t bundled, but they\'re CC BY-SA 4.0: fetch them from their own repo and use or adapt them on any install, credited "Stackapella (https://stackapella.com)". The creator makes songs too.',
            'The real singers\' guide voices. Your guides are the synthesized voice.',
            'Real instruments, until you upload CC0 sample sets of your own (php artisan app:instruments:upload).',
            'The native apps, which talk to stackapella.com. On your server, people use the web app, which installs from the browser.',
            'Paid Plus, the app stores and coaching, which are Stackapella\'s business.',
            'This site. You may run the Stackapella web app under the Attribution Assurance License, but our marketing site (its pages, words, images and the Stackapella brand) isn\'t part of what\'s licensed for you to serve. On your install it\'s switched off (IS_NEXUS off): your address opens the app, and Help, the legal pages and shared harmonies stay.',
            'If your install may not use the songs, the course and the guides, delete those folders: it runs without them, with no songs until you add your own, no guides and no what\'s new.',
        ],
        'connects_title' => 'What a fresh install connects to',
        'connects' => [
            'Nothing reported to us, no third-party analytics and no licence check.',
            'Your server contacts your storage and mail provider, and each optional service only once its keys are set. Practice reminders go through the browsers\' own push services, only for people who turn them on.',
            'The weekly check of Cloudflare\'s published addresses runs only behind Cloudflare: BEHIND_CLOUDFLARE, off by default on a self-hosted install.',
            'Your users\' browsers load Vue (and lamejs, when exporting an MP3) from jsDelivr, each pinned to an exact version with an integrity hash, and the Figtree and Noto Music fonts from Google Fonts.',
        ],
        'optional_title' => 'Bring your own, if you like',
        'optional' => 'Google and Apple sign-in, Turnstile, Sentry, web push (php artisan app:reminders:keys), Event Schedule and Invoice Ninja, and posting to X and Bluesky. Each stays out of the way until its keys are in .env.',
        'yours_title' => 'Yours to run',
        'yours' => [
            'HTTPS and its renewals.',
            'Backups, and a restore you\'ve tested.',
            'Updates.',
            'Mail that arrives.',
            'Moderation, if you open sharing.',
            'Your own terms and privacy policy. Voices are personal data, and your singers may be 13 or older.',
        ],
        'choirs_title' => 'For choirs and schools',
        'choirs' => 'One install can serve a whole choir, school or federation: the director makes a group, shares songs and assigns parts, and the singers practise theirs.',
        'should_title' => 'Should you self-host?',
        'should' => 'Most singers shouldn\'t: stackapella.com is free to start. Self-host when the recordings must stay on your own servers, or when you\'re building on Stackapella.',
        'github' => 'View on GitHub',
        'terms' => 'Self-hosting terms',
        'open_source' => 'What\'s open source',
        'readme' => 'The README\'s install steps',
        'notices' => 'Third-party notices',
    ],

    // How an app link looks when it's shared (Slack, WhatsApp, iMessage, X...): the tags the server
    // puts in the app's head (App\Support\Web\AppShell), and the words on a song's card. Only what
    // the page itself shows a signed-out visitor: never who sent an invite.
    'meta' => [
        'app' => [
            'default' => [
                'title' => 'Stackapella',
                'description' => 'Harmony, made by you. For one voice, or a whole choir. Learn a part with a guide voice, then stack your own voices, exactly in time.',
                'image_alt' => 'Stackapella: four coloured bars, one for each voice, and Harmony, made by you.',
            ],
            'join' => [
                'title' => 'Sing in :song',
                'title_part' => 'Sing the :part in :song',
                'description' => 'You\'re invited to sing in this harmony on Stackapella. Learn your part with a guide voice, then record it over the others.',
                'image_alt' => ':song, with Join in, on Stackapella.',
            ],
            'invite' => [
                'title' => 'You\'re invited to Stackapella',
                'description' => 'Try Stackapella before it opens to everyone: learn a part with a guide voice, then stack your own voices. No account needed to start.',
            ],
            'early' => [
                'title' => 'Early access to Stackapella',
                'description' => 'You\'re in before Stackapella opens to everyone: learn a part with a guide voice, then stack your own voices.',
            ],
            'choir' => [
                'title' => 'Join your choir on Stackapella',
                'description' => 'Your choir director shares the songs and gives out the parts. Learn yours at home with a guide voice.',
            ],
            'coming_soon' => [
                'title' => 'Stackapella',
                'description' => 'Stackapella opens soon. Harmony, made by you: learn a part with a guide voice, then stack your own voices.',
            ],
            // On a song's card (SongCards), under its title.
            'card' => [
                'join' => 'Join in',
                'listen' => 'Listen',
            ],
        ],
    ],
    // The courses coming to Plus (docs/plus-courses-plan.md; Hillel, 2026-10-29: "All 27 now"): /courses and
    // /courses/<slug>, each Coming soon. `needs` names the listening the app can't do yet, `health` the
    // plan's care for loud or high singing. Nothing promised beyond the plan.
    // The comparison pages (/compare, /compare/<slug>; docs/compare-pages-plan.md), unlisted behind the
    // compare_pages switch until the lawyer (H14) and Hillel say go. for_lawyer: comparative advertising
    // and the use of other products' names, as the plan lists. Every claim about another product is
    // from its own listing or site, seen 2026-10-30 (docs/compare-sources.md); our own cells are shared.
    'compare' => [
        'index' => [
            'title' => 'Stackapella compared with other harmony and singing apps',
            'description' => 'Honest, dated side-by-side notes on Stackapella and other apps for learning and singing harmony, each with its sources.',
            'eyebrow' => 'Compare',
            'headline' => 'How Stackapella compares',
            'lead' => 'Side-by-side notes on other apps for learning and singing harmony, from each one\'s own listing or website. Each page says where the other fits better, too.',
        ],
        'common' => [
            'asof' => 'As of October 2026',
            'seen' => 'Seen 30 October 2026, on each product\'s own listing or website.',
            'us' => 'Stackapella',
            'rows' => [
                'runs' => 'Where it runs',
                'price' => 'Price',
                'learn' => 'Learn a part by ear, with live pitch feedback',
                'stack' => 'Record your own parts, stacked in time',
                'suggest' => 'Harmony parts suggested for your own tune',
                'course' => 'A course from first note to harmony',
                'choir' => 'Choir tools',
                'import' => 'Bring in sheet music (MusicXML or MIDI)',
                'languages' => 'Languages',
                'open' => 'Open source',
            ],
            'cells' => [
                'runs' => 'In any modern browser, on phones and computers. It installs like an app; there\'s no app-store app yet',
                'price' => 'Free to start. Plus is :price_month a month or :price_year a year',
                'learn' => 'Yes: hear each part from a guide voice, then sing it while your notes light up as you hit them',
                'stack' => 'Yes: record each part over the ones before, lined up in time',
                'suggest' => 'Yes: sing a tune, and alto, tenor and bass are suggested to fit it',
                'course' => 'Yes: a free course from your first note to four-part harmony, and Plus courses on skills and styles',
                'choir' => 'Choir groups and practice tracks',
                'import' => 'Not yet: sing a tune, write it in the note editor, or open a Stackapella file',
                'languages' => 'English',
                'open' => 'Yes, under the Attribution Assurance License',
            ],
            'titles' => [
                'for' => 'Who each is for',
                'for_them' => ':name is for',
                'for_us' => 'Stackapella is for',
                'table' => 'Side by side',
                'fits' => 'Where each fits better',
                'fits_them' => 'Where :name fits better',
                'fits_us' => 'Where Stackapella fits better',
                'moving' => 'Moving over',
                'faq' => 'Questions',
                'try' => 'Try Stackapella',
                'sources' => 'Sources',
            ],
            'not_stated' => '"Not stated" means we couldn\'t find it in the product\'s own listing or website, not that it\'s missing.',
            'try_body' => 'Start with your comfortable note. No account needed, and the course\'s first seven units are free.',
            'names' => 'Product names belong to their owners. Stackapella isn\'t connected with any of these products or their makers.',
            'source' => 'Source :n',
        ],
        'pages' => [
            'howli' => [
                'name' => 'Howli',
                'title' => 'Stackapella and Howli compared, as of October 2026',
                'description' => 'How Stackapella and Howli compare for learning and singing harmony: where each runs, prices, feedback, recording, courses and importing music, with sources.',
                'summary' => 'Both teach harmony parts with live pitch feedback.',
                'headline' => 'Stackapella and Howli',
                'lead' => 'Both help you learn harmony parts with live pitch feedback, and record them. Howli is an iPhone and Android app with a lifetime price and 45 languages. Stackapella runs in your browser, with a free course and harmony suggested for your own tunes.',
                'them_for' => [
                    'Singers who want an app on iPhone or Android.',
                    'Anyone who\'d rather buy once: it has a lifetime price.',
                    'Bringing in your own arrangement as MusicXML or MIDI.',
                ],
                'us_for' => [
                    'Starting from your very first note, with a free course to four-part harmony.',
                    'Getting alto, tenor and bass suggested for a tune you sing.',
                    'Singing in a browser on any phone or computer, and practising with a choir.',
                ],
                'columns' => ['Howli'],
                'cells' => [
                    'runs' => [
                        [
                            'iPhone and Android apps, and a free song editor on the web',
                            [1, 2, 3],
                        ],
                    ],
                    'price' => [
                        [
                            'Free up to a daily limit. Unlimited: $3.99 a month, $14.99 a year, or $24.99 once (App Store)',
                            [1],
                        ],
                    ],
                    'learn' => [
                        [
                            'Yes: hear each part, then sing it with live pitch feedback',
                            [1, 2],
                        ],
                    ],
                    'stack' => [
                        [
                            'Yes: record each part, two to six layers',
                            [1],
                        ],
                    ],
                    'suggest' => [
                        [
                            'Not stated: you write the parts or import them',
                            [1],
                        ],
                    ],
                    'course' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'choir' => [
                        [
                            'Not stated. Duets with a friend, and song codes to share an arrangement',
                            [1, 2],
                        ],
                    ],
                    'import' => [
                        [
                            'Yes: MusicXML and MIDI, in its web editor',
                            [1],
                        ],
                    ],
                    'languages' => [
                        [
                            '45',
                            [1],
                        ],
                    ],
                    'open' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                ],
                'fits_them' => [
                    'You want an app from the App Store or Google Play.',
                    'You\'d like to pay once, or sing in one of its 45 languages.',
                    'You already have an arrangement as MusicXML or MIDI.',
                ],
                'fits_us' => [
                    'You\'re new to singing and want a course that starts at the first note.',
                    'You want harmony parts suggested for a tune of your own.',
                    'You sing with a choir and want its groups and practice tracks.',
                ],
                'faq' => [
                    [
                        'Does Stackapella work on Android?',
                        'Yes, in the browser on Android phones, and it installs like an app. There\'s no app-store app yet.',
                    ],
                    [
                        'Can I bring MusicXML or MIDI into Stackapella?',
                        'Not yet. You can sing or write a tune in the note editor, or open a Stackapella file.',
                    ],
                    [
                        'Is Stackapella free?',
                        'Yes, to start: the course\'s first seven units, from your first note to all four parts of Amazing Grace, and your own songs. Plus is :price_month a month or :price_year a year.',
                    ],
                ],
                'sources' => [
                    [
                        'Howli on the App Store',
                        'https://apps.apple.com/us/app/howli-learn-to-sing-harmonies/id6762453643',
                    ],
                    ['Howli on Google Play', 'https://play.google.com/store/apps/details?id=com.howli.howli'],
                    ['get-howli.com', 'https://get-howli.com/'],
                ],
            ],
            'harmony-helper' => [
                'name' => 'Harmony Helper',
                'title' => 'Stackapella and Harmony Helper compared, as of October 2026',
                'description' => 'How Stackapella and Harmony Helper compare for learning harmony parts: where each runs, prices, pitch feedback, songbooks, courses and recording, with sources.',
                'summary' => 'Both show your pitch while you sing a harmony part.',
                'headline' => 'Stackapella and Harmony Helper',
                'lead' => 'Both show your pitch while you sing a harmony part. Harmony Helper is an iPhone, iPad and Android app from RWS Global, built around songbooks that directors share with their singers. Stackapella runs in your browser, with a free course and your own recorded parts stacked into a song.',
                'them_for' => [
                    'Singers whose director shares songs through a Harmony Helper songbook.',
                    'RWS Global partners and their performers.',
                    'Songs with up to 12 parts.',
                ],
                'us_for' => [
                    'Learning harmony from your very first note, with a free course.',
                    'Recording every part yourself and hearing them stacked.',
                    'Singing or writing your own tune and getting parts suggested.',
                ],
                'columns' => ['Harmony Helper'],
                'cells' => [
                    'runs' => [
                        [
                            'iPhone, iPad and Android apps, and a web portal for songbooks',
                            [1, 2, 3],
                        ],
                    ],
                    'price' => [
                        [
                            'A free app and free songbook songs. RWS says it\'s available exclusively to RWS Global partners',
                            [1, 3],
                        ],
                    ],
                    'learn' => [
                        [
                            'Yes: real-time pitch and timing feedback, with part volume controls',
                            [1, 3],
                        ],
                    ],
                    'stack' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'suggest' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'course' => [
                        [
                            'A guided five-step method for learning and holding harmonies',
                            [1, 3],
                        ],
                    ],
                    'choir' => [
                        [
                            'Yes: directors create songbooks and share them with performers or students',
                            [3],
                        ],
                    ],
                    'import' => [
                        [
                            'Not stated. Its sheet-music scanner ended in December 2024',
                            [1],
                        ],
                    ],
                    'languages' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'open' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                ],
                'fits_them' => [
                    'Your director already shares songs through its songbooks.',
                    'You\'re with an RWS Global production or partner.',
                    'You need up to 12 parts.',
                ],
                'fits_us' => [
                    'You\'re new to singing and want a course from the first note.',
                    'You want to record every part of a song yourself.',
                    'You want to make your own song and get parts suggested.',
                ],
                'moving' => 'Coming from Harmony Helper? Stackapella can\'t open its songbooks. Start with the free course, or a public-domain song from the library, or sing your own tune and get its parts suggested.',
                'faq' => [
                    [
                        'Can Stackapella open Harmony Helper songbooks?',
                        'No. Its library has public-domain songs to start with, and you can sing, hum or write your own.',
                    ],
                    [
                        'Does Stackapella work on iPad and Android?',
                        'Yes, in the browser, and it installs like an app. There\'s no app-store app yet.',
                    ],
                    [
                        'Is Stackapella free?',
                        'Yes, to start: the course\'s first seven units, from your first note to all four parts of Amazing Grace, and your own songs. Plus is :price_month a month or :price_year a year.',
                    ],
                ],
                'sources' => [
                    [
                        'Harmony Helper on the App Store',
                        'https://apps.apple.com/us/app/harmony-helper/id1283704743',
                    ],
                    [
                        'Harmony Helper on Google Play',
                        'https://play.google.com/store/apps/details?id=com.harmonyhelperapp.practice',
                    ],
                    ['RWS Global, Harmony Helper', 'https://www.rwsglobal.com/harmony-helper'],
                ],
            ],
            'sing-harmonies' => [
                'name' => 'Sing Harmonies',
                'title' => 'Stackapella and Sing Harmonies compared, as of October 2026',
                'description' => 'How Stackapella and Sing Harmonies compare for learning harmony by ear: where each runs, prices, the singers you hear, pitch feedback and recording, with sources.',
                'summary' => 'Both teach harmony parts by ear, one singer at a time.',
                'headline' => 'Stackapella and Sing Harmonies',
                'lead' => 'Sing Harmonies lets you solo, soften or silence each of four singers in its arrangements, on iPhone and iPad. Its maker has said the app is being phased out. Stackapella runs in your browser, listens as you sing, and lets you record every part yourself.',
                'them_for' => [
                    'Learning its arrangements of pop and folk songs by ear.',
                    'Hearing four singers, each at full, soft or silent.',
                    'Paying once for the app and each song.',
                ],
                'us_for' => [
                    'Feedback on your pitch as you sing your part.',
                    'Recording every part yourself and hearing them together.',
                    'Public-domain songs in four parts, and your own songs.',
                ],
                'columns' => ['Sing Harmonies'],
                'cells' => [
                    'runs' => [
                        [
                            'iPhone and iPad. No longer on Google Play',
                            [1, 2],
                        ],
                    ],
                    'price' => [
                        [
                            '$1.99 with three songs; more songs $0.99 each',
                            [1],
                        ],
                    ],
                    'learn' => [
                        [
                            'By ear: solo or mute any of the four singers. Live pitch feedback: not stated',
                            [1],
                        ],
                    ],
                    'stack' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'suggest' => [
                        [
                            'Not stated: its songs come from its own catalogue',
                            [1],
                        ],
                    ],
                    'course' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'choir' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'import' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'languages' => [
                        [
                            'English',
                            [1],
                        ],
                    ],
                    'open' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                ],
                'fits_them' => [
                    'You already own its songs and know the app.',
                    'You want to hear four real singers in its arrangements.',
                ],
                'fits_us' => [
                    'You want feedback on your pitch as you sing.',
                    'You want to record every part yourself.',
                    'You want new songs, including your own.',
                ],
                'moving' => 'Coming from Sing Harmonies? Its maker\'s own notes say no new songs since August 2024, the Android app was removed in November 2024, and in January 2026 the app still worked on the newest iOS. Stackapella\'s library has public-domain songs in four parts, and you can sing or write your own.',
                'faq' => [
                    [
                        'Can I bring my Sing Harmonies songs over?',
                        'No, its arrangements stay in its app. Stackapella\'s library has public-domain songs arranged in four parts, like Amazing Grace, and you can make your own.',
                    ],
                    [
                        'Does Stackapella work on Android?',
                        'Yes, in the browser on Android phones, and it installs like an app.',
                    ],
                    [
                        'Is Stackapella free?',
                        'Yes, to start: the course\'s first seven units, from your first note to all four parts of Amazing Grace, and your own songs. Plus is :price_month a month or :price_year a year.',
                    ],
                ],
                'sources' => [
                    [
                        'Sing Harmonies on the App Store',
                        'https://apps.apple.com/us/app/sing-harmonies/id378913608',
                    ],
                    ['singharmonies.com, with its maker\'s notes', 'https://www.singharmonies.com/'],
                ],
            ],
            'acapella' => [
                'name' => 'Acapella',
                'title' => 'Stackapella and Acapella compared, as of October 2026',
                'description' => 'How Stackapella and Acapella from PicPlayPost compare: where each runs, prices, recording your own parts, video, pitch feedback and learning, with sources.',
                'summary' => 'Both record your own voice in layers.',
                'headline' => 'Stackapella and Acapella',
                'lead' => 'Acapella, from PicPlayPost, is an iPhone and iPad app for recording yourself in multi-frame music videos up to 10 minutes long, and collaborating with friends. Stackapella is for learning harmony: it teaches each part by ear, listens as you sing, and stacks your parts in time.',
                'them_for' => [
                    'Making split-screen music videos of yourself.',
                    'Making music videos with friends.',
                    'Recording songs up to 10 minutes long.',
                ],
                'us_for' => [
                    'Learning a part by ear, with feedback on your pitch.',
                    'A free course from your first note to four-part harmony.',
                    'Parts suggested for your own tune, and share videos with the words.',
                ],
                'columns' => ['Acapella'],
                'cells' => [
                    'runs' => [
                        [
                            'iPhone and iPad. Android: not stated by its maker',
                            [1, 2],
                        ],
                    ],
                    'price' => [
                        [
                            'Free with in-app purchases: Singing App Pro $9.99 a month, Multitrack Editor Pro $47.99 a year, packs from $0.99 to $7.99',
                            [1],
                        ],
                    ],
                    'learn' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'stack' => [
                        [
                            'Yes: multitrack, multi-frame videos, up to 10 minutes',
                            [1, 2],
                        ],
                    ],
                    'suggest' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'course' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'choir' => [
                        [
                            'Not stated. Make music with friends',
                            [1, 2],
                        ],
                    ],
                    'import' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'languages' => [
                        [
                            '11',
                            [1],
                        ],
                    ],
                    'open' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                ],
                'fits_them' => [
                    'You want a video of yourself in a grid of frames.',
                    'You\'re making music videos with friends.',
                ],
                'fits_us' => [
                    'You want to learn harmony parts, with feedback as you sing.',
                    'You want parts suggested for a tune of your own.',
                    'You sing with a choir.',
                ],
                'faq' => [
                    [
                        'Does Stackapella make videos?',
                        'Yes: a share video of your song, with the words shown as they\'re sung. It\'s your audio with the words, not a grid of faces.',
                    ],
                    [
                        'Does Stackapella work on Android?',
                        'Yes, in the browser on Android phones, and it installs like an app.',
                    ],
                    [
                        'Is Stackapella free?',
                        'Yes, to start: the course\'s first seven units, from your first note to all four parts of Amazing Grace, and your own songs. Plus is :price_month a month or :price_year a year.',
                    ],
                ],
                'sources' => [
                    ['Acapella on the App Store', 'https://apps.apple.com/us/app/-/id924635678'],
                    ['Mixcord, Acapella', 'https://www.mixcord.co/pages/acapella-singing-app'],
                ],
            ],
            'yousician' => [
                'name' => 'Yousician',
                'title' => 'Stackapella and Yousician singing compared, as of October 2026',
                'description' => 'How Stackapella and Yousician\'s singing lessons compare: where each runs, prices, pitch feedback, courses, harmony and recording, with sources.',
                'summary' => 'Both have singing lessons that listen to your pitch.',
                'headline' => 'Stackapella and Yousician',
                'lead' => 'Yousician teaches singing in a guided course that listens to your pitch, alongside guitar and bass. Stackapella is about harmony: learning a part while the others play, and recording every part yourself.',
                'them_for' => [
                    'A guided singing course, from first-time singers to pros.',
                    'Learning guitar or bass in the same app.',
                    'Apps for iOS, Android, Mac and PC.',
                ],
                'us_for' => [
                    'Harmony first: learning one part while the others play.',
                    'Recording every part yourself, stacked in time.',
                    'Parts suggested for your own tune, and choir groups.',
                ],
                'columns' => ['Yousician'],
                'cells' => [
                    'runs' => [
                        [
                            'iOS, Android, Mac and PC',
                            [2],
                        ],
                    ],
                    'price' => [
                        [
                            'One Instrument: $19.99 a month or $119.99 a year. Premium Plus: $29.99 a month, or $159.99 or $179.99 a year (App Store)',
                            [1],
                        ],
                    ],
                    'learn' => [
                        [
                            'Live pitch feedback on your singing: yes. "Harmony" is listed as a lesson topic',
                            [1, 2],
                        ],
                    ],
                    'stack' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'suggest' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'course' => [
                        [
                            'Yes: a guided learning path',
                            [1],
                        ],
                    ],
                    'choir' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'import' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'languages' => [
                        [
                            '10',
                            [1],
                        ],
                    ],
                    'open' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                ],
                'fits_them' => [
                    'You want a broad singing course, or to learn guitar or bass too.',
                    'You want an app on Mac or PC.',
                ],
                'fits_us' => [
                    'You want to learn harmony parts and sing them with others.',
                    'You want to record every part of a song yourself.',
                    'You want parts suggested for your own tune.',
                ],
                'faq' => [
                    [
                        'Can I learn solo singing in Stackapella?',
                        'Its free course starts with your first note, steady notes and a whole tune before any harmony, and Plus courses cover skills like vibrato, breath and pitch. Its focus is harmony.',
                    ],
                    [
                        'Does Stackapella work on a computer?',
                        'Yes, in any modern browser, on a computer or a phone.',
                    ],
                    [
                        'Is Stackapella free?',
                        'Yes, to start: the course\'s first seven units, from your first note to all four parts of Amazing Grace, and your own songs. Plus is :price_month a month or :price_year a year.',
                    ],
                ],
                'sources' => [
                    [
                        'Yousician on the App Store',
                        'https://apps.apple.com/us/app/yousician-learn-guitar-bass/id959883039',
                    ],
                    ['yousician.com/voice', 'https://yousician.com/voice'],
                ],
            ],
            'smule' => [
                'name' => 'Smule',
                'title' => 'Stackapella and Smule compared, as of October 2026',
                'description' => 'How Stackapella and Smule compare: where each runs, prices, songs, duets, pitch guides, learning harmony and recording your own parts, with sources.',
                'summary' => 'Both let you sing in layers, for different ends.',
                'headline' => 'Stackapella and Smule',
                'lead' => 'Smule is for singing songs with others: duets and group performances from a catalogue of over 15 million songs, with pitch guides. Stackapella is for learning harmony and recording every part yourself, from public-domain songs and your own.',
                'them_for' => [
                    'Singing popular songs from a very large catalogue.',
                    'Duets and group performances with other singers.',
                    'Live karaoke sessions.',
                ],
                'us_for' => [
                    'Learning a harmony part by ear, with feedback on your pitch.',
                    'Recording every part of a song yourself.',
                    'A free course from your first note to four-part harmony.',
                ],
                'columns' => ['Smule'],
                'cells' => [
                    'runs' => [
                        [
                            'iPhone, iPad, Apple TV and Android. Web: not stated',
                            [1, 2],
                        ],
                    ],
                    'price' => [
                        [
                            'Join duets free. VIP: $5.99 to $12.99, or $7.99 a week (App Store)',
                            [1, 2],
                        ],
                    ],
                    'learn' => [
                        [
                            'Pitch guides while you sing. Learning a harmony part: not stated',
                            [1, 2],
                        ],
                    ],
                    'stack' => [
                        [
                            'Layered group songs',
                            [2],
                        ],
                    ],
                    'suggest' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'course' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'choir' => [
                        [
                            'Not stated. Duets, group performances and live sessions',
                            [2],
                        ],
                    ],
                    'import' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'languages' => [
                        [
                            '14',
                            [1],
                        ],
                    ],
                    'open' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                ],
                'fits_them' => [
                    'You want to sing popular songs with other people.',
                    'You want duets, groups and live sessions.',
                ],
                'fits_us' => [
                    'You want to learn to hold a harmony part.',
                    'You want to record every part yourself.',
                    'You want a course that starts at your first note.',
                ],
                'faq' => [
                    [
                        'Does Stackapella have popular songs?',
                        'Its library is public-domain songs, like Amazing Grace, and you can make your own. It doesn\'t license popular songs.',
                    ],
                    [
                        'Can I sing with friends in Stackapella?',
                        'Yes: share a song by link so friends can learn it part by part, and choirs can use groups.',
                    ],
                    [
                        'Is Stackapella free?',
                        'Yes, to start: the course\'s first seven units, from your first note to all four parts of Amazing Grace, and your own songs. Plus is :price_month a month or :price_year a year.',
                    ],
                ],
                'sources' => [
                    ['Smule on the App Store', 'https://apps.apple.com/us/app/smule-sing-duet/id509993510'],
                    [
                        'Smule on Google Play',
                        'https://play.google.com/store/apps/details?id=com.smule.singandroid',
                    ],
                ],
            ],
            'choirmate' => [
                'name' => 'ChoirMate',
                'title' => 'Stackapella and ChoirMate compared, as of October 2026',
                'description' => 'How Stackapella and ChoirMate compare for choirs: where each runs, prices, practising your part, pitch feedback, choir tools and sheet music, with sources.',
                'summary' => 'Both help choir singers practise their parts.',
                'headline' => 'Stackapella and ChoirMate',
                'lead' => 'ChoirMate runs a choir: members, set lists, calendar and attendance, messages and files, with a Track Mixer to practise your part. Stackapella helps each singer learn their part: it listens as you sing, and has choir groups and practice tracks.',
                'them_for' => [
                    'Choir directors who want rehearsals, attendance and messages in one place.',
                    'Set lists, sheet music and audio files for the whole choir.',
                    'Practising in the car with CarPlay, or on an Apple Watch.',
                ],
                'us_for' => [
                    'Each singer getting feedback on their pitch while they learn.',
                    'A course that teaches singers to hold a harmony part.',
                    'Recording every part yourself, stacked in time.',
                ],
                'columns' => ['ChoirMate'],
                'cells' => [
                    'runs' => [
                        [
                            'iPhone, iPad, Apple Watch and Android, and a web version',
                            [1, 2, 5],
                        ],
                    ],
                    'price' => [
                        [
                            'Free for up to 50 people. Choir plans $26.67 or $53.33 a month. Premium for one singer $4.99 a month (App Store)',
                            [3, 1],
                        ],
                    ],
                    'learn' => [
                        [
                            'By ear: its Track Mixer solos or mutes parts. Live pitch feedback: not stated',
                            [4],
                        ],
                    ],
                    'stack' => [
                        [
                            'Not stated. Members can make recordings',
                            [1, 6],
                        ],
                    ],
                    'suggest' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'course' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'choir' => [
                        [
                            'Yes: members, set lists, calendar and attendance, polls, messages and files',
                            [1, 3],
                        ],
                    ],
                    'import' => [
                        [
                            'Sheet music as PDF. MIDI must be converted to audio first',
                            [5],
                        ],
                    ],
                    'languages' => [
                        [
                            '15 (App Store)',
                            [1],
                        ],
                    ],
                    'open' => [
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                ],
                'fits_them' => [
                    'You run a choir and want its rehearsals, people and files in one place.',
                    'You want set lists, CarPlay and Apple Watch.',
                ],
                'fits_us' => [
                    'You want each singer to get feedback on their pitch.',
                    'You want singers to learn harmony from the first note.',
                    'You want to record every part of a song yourself.',
                ],
                'faq' => [
                    [
                        'Can I use both?',
                        'Yes. Many choirs keep their rosters and files in one place and practise parts in another.',
                    ],
                    [
                        'Does Stackapella have choir tools?',
                        'Choir groups and practice tracks, so a director can share songs and each singer can learn their part. It doesn\'t do attendance, calendars or messages.',
                    ],
                    [
                        'Is Stackapella free?',
                        'Yes, to start: the course\'s first seven units, from your first note to all four parts of Amazing Grace, and your own songs. Plus is :price_month a month or :price_year a year.',
                    ],
                ],
                'sources' => [
                    ['ChoirMate on the App Store', 'https://apps.apple.com/us/app/choirmate/id1495545798'],
                    [
                        'ChoirMate on Google Play',
                        'https://play.google.com/store/apps/details?id=com.choirmate.app',
                    ],
                    ['ChoirMate plans', 'https://www.choirmate.com/subscription-plans'],
                    ['ChoirMate, Track Mixer', 'https://www.choirmate.com/blog/track-mixer'],
                    ['ChoirMate FAQ', 'https://www.choirmate.com/faq'],
                    ['choirmate.com', 'https://www.choirmate.com/'],
                ],
            ],
            'cyberbass-and-choralia' => [
                'name' => 'CyberBass and Choralia',
                'title' => 'Stackapella, CyberBass and Choralia compared, as of October 2026',
                'description' => 'How Stackapella compares with CyberBass and Choralia, the free choir practice-track sites: prices, practising your part, pitch feedback, courses and recording, with sources.',
                'summary' => 'All three help choir singers learn their part by ear.',
                'headline' => 'Stackapella and CyberBass and Choralia',
                'lead' => 'CyberBass and Choralia are free websites of practice tracks for choral works, with your own part brought forward. Stackapella teaches the parts of shorter songs by ear and listens as you sing.',
                'them_for' => [
                    'Choir singers learning a major choral work, like a requiem.',
                    'Free practice tracks, with no account.',
                    'Your part louder, or on its own with a click (Choralia).',
                ],
                'us_for' => [
                    'Feedback on your pitch as you sing your part.',
                    'A free course from your first note to four-part harmony.',
                    'Recording every part yourself, and your own songs.',
                ],
                'columns' => ['CyberBass', 'Choralia'],
                'cells' => [
                    'runs' => [
                        [
                            'Web',
                            [1],
                        ],
                        [
                            'Web: listen, or download MP3s',
                            [5],
                        ],
                    ],
                    'price' => [
                        [
                            'Free online. MP3 sets and CDs for sale',
                            [2, 3],
                        ],
                        [
                            'Free. Its licence asks for a donation, often 5 to 50 euros a work',
                            [5, 6],
                        ],
                    ],
                    'learn' => [
                        [
                            'By ear: your part with the others quieter, with a speed control and an A-B loop. Live pitch feedback: not stated',
                            [1, 4],
                        ],
                        [
                            'By ear: your part with a click, or louder in the mix. Live pitch feedback: not stated',
                            [5],
                        ],
                    ],
                    'stack' => [
                        [
                            'Not stated',
                            [],
                        ],
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'suggest' => [
                        [
                            'Not stated',
                            [],
                        ],
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'course' => [
                        [
                            'Not stated',
                            [],
                        ],
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'choir' => [
                        [
                            'Not stated. Choirs are encouraged to link to it',
                            [2],
                        ],
                        [
                            'Not stated. Choirs may share the files under its licence',
                            [6],
                        ],
                    ],
                    'import' => [
                        [
                            'Not stated',
                            [],
                        ],
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                    'languages' => [
                        [
                            'Not stated',
                            [],
                        ],
                        [
                            'Its site is in 4 languages',
                            [5],
                        ],
                    ],
                    'open' => [
                        [
                            'Not stated',
                            [],
                        ],
                        [
                            'Not stated',
                            [],
                        ],
                    ],
                ],
                'fits_them' => [
                    'You\'re learning a large choral work your choir is singing.',
                    'You want free tracks with no account.',
                ],
                'fits_us' => [
                    'You want feedback on your pitch as you learn.',
                    'You want a course that teaches harmony from the first note.',
                    'You want to record every part yourself.',
                ],
                'faq' => [
                    [
                        'Does Stackapella have major choral works?',
                        'Not as a catalogue: its library is shorter public-domain songs, and a director can add the choir\'s own songs.',
                    ],
                    [
                        'Do I need an account?',
                        'No, to start the free course. A free account keeps your songs and progress.',
                    ],
                    [
                        'Is Stackapella free?',
                        'Yes, to start: the course\'s first seven units, from your first note to all four parts of Amazing Grace, and your own songs. Plus is :price_month a month or :price_year a year.',
                    ],
                ],
                'sources' => [
                    ['CyberBass FAQ', 'https://cyberbass.org/CommonContent/cbFAQs.htm'],
                    ['CyberBass, supporting it', 'https://cyberbass.org/CommonContent/cbPledge.htm'],
                    ['CyberBass CDs', 'https://cyberbass.org/CommonContent/cbPurchaseCD.htm'],
                    ['CyberBass player', 'https://cyberbass.org/CommonContent/cbPlayer.htm'],
                    ['choralia.net', 'https://choralia.net/'],
                    ['Choralia FAQ and contacts', 'https://choralia.net/contacts.html'],
                ],
            ],
        ],
    ],
    'courses' => [
        'index' => [
            // The free course, listed first, to its own page (/features/course).
            'free_kick' => 'Free',
            'free_name' => 'Free: from your first note to four-part harmony',
            'free_summary' => '8 units and 32 lessons, from finding your comfortable note to singing all four parts of Amazing Grace. Units 1 to 7 are free.',
            'title' => 'Singing courses coming to Stackapella Plus',
            'description' => '27 deeper courses for your voice, coming to Stackapella Plus: counterpoint, vibrato, range, sight-singing, scat, barbershop, whistling, beatboxing and more.',
            'headline' => 'Courses coming to Plus',
            'lead' => 'Deeper courses for your voice, built on the same short lessons as the free course. When each one opens, its first lesson is free.',
        ],
        // Singing skills, Harmony and Styles (Hillel), as the app's catalogue has them
        // (web/course/plus/courses.json: `group` and `order` win where a course is listed). The first
        // in each are the launch courses; Vibrato is featured.
        'groups' => [
            'skills' => ['name' => 'Singing skills', 'icon' => 'mic', 'courses' => ['vibrato', 'perfect-your-pitch', 'breath-and-stamina', 'ear-training', 'range-and-registers', 'sight-singing', 'humming-and-lip-trills', 'belting', 'diction', 'other-languages']],
            'harmony' => ['name' => 'Harmony', 'icon' => 'spark', 'courses' => ['counterpoint', 'improvising-harmony', 'barbershop', 'a-cappella-bass', 'gospel-runs', 'scat', 'beatboxing', 'vocal-percussion']],
            'styles' => ['name' => 'Styles', 'icon' => 'hum', 'courses' => ['folk-and-early-music', 'yodeling', 'pop-and-country', 'musical-theatre', 'whistling', 'live-looping', 'overtone-singing', 'character-voices', 'accents']],
        ],
        'pages' => [
            'counterpoint' => [
                'group' => 'harmony',
                'name' => 'Counterpoint',
                'summary' => 'Drones, rounds, descants, and lines that fit together.',
                'title' => 'Counterpoint course',
                'description' => 'Drones, rounds, descants, and lines that fit together. A harmony course on Stackapella Plus, in short lessons, with the first lesson free.',
                'headline' => 'Sing lines that fit together',
                'lead' => 'Counterpoint is two or more tunes sung at once, each a melody of its own, fitting together. The course goes from a drone to rounds, descants and note-against-note lines.',
                'learn' => ['Holding your line against a drone', 'Rounds and partner songs', 'Descants above a tune', 'Note against note: which intervals ring, and how lines move'],
                'measures' => 'The app hears your notes on pitch while other voices sing, when you\'re pulled onto theirs, and how your own line moves. Blend and balance are yours to check.',
                'scoring' => ['game' => 'Up to three stars and XP for your notes on pitch, and Own line counts your notes in a row on pitch while the other voices sound.', 'serious' => 'The same take shows the plain measure, such as "14 of 16 notes on pitch, pulled once", with no stars or combos.'],
                'safety' => ['Don\'t sing louder to hear yourself: listen more instead.', 'Wired headphones, or one ear lifted, help you hear your own voice.', 'Stop if anything tightens, tickles or hurts. Don\'t practise when hoarse.'],
                'songs' => [['Row, Row, Row Your Boat', 'Traditional, public domain'], ['Are You Sleeping', 'Traditional, 18th century, public domain'], ['Ode to Joy', 'Public domain melody (1824)'], ['O Come, All Ye Faithful', 'Hymn tune (1740s), public domain'], ['The canon, the descant and the slow lines', 'Written for the course']],
                'shots' => ['Counterpoint\'s first lesson, Be a round: singing Row, Row, Row on the lane while the other voices\' notes come in two bars apart.', 'Your choice, in unit 5: at each note of the slow line, every note that fits lights up, and you choose which one to sing.'],
                // The designed page (site/courses/counterpoint): its own sections and words.
                'app' => 'counterpoint',
                'eyebrow' => 'A Plus course · 22 lessons',
                'hero_title' => 'Hold your own line.',
                'hero_lead' => 'Counterpoint is two or more tunes sung at once, each a melody of its own, fitting together. You start by leading a round, and finish by writing your own line against a melody.',
                'hero_facts' => ['22 lessons, 2 to 4 minutes each', 'About 70 minutes in all', 'No reading music'],
                'see_lessons' => 'See the lessons',
                'titles' => [
                    'outcomes' => 'By the last book, you can',
                    'move' => 'Three ways two lines can move',
                    'units' => 'Five books, from a round to your own line',
                    'try' => 'Be a round',
                    'reader' => 'Before you begin',
                    'inside' => 'What the app listens for',
                    'facts' => 'Three notes in the margin',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'join' => 'Start the round.',
                    'related' => 'Good with this course',
                ],
                'outcomes' => [
                    'Hold your line while drones, rounds and canons pull at your ear.',
                    'Sing a descant over a tune, and two songs at once.',
                    'Hear which notes are smooth together and which rub.',
                    'Choose your own notes against a slow melody, and write a counterpoint.',
                ],
                'move' => [
                    'lead' => 'Two voices can move the same way, opposite ways, or one can hold while the other moves. Opposite ways keeps them most independent.',
                    'kinds' => [['Same way', 'Both lines rise or fall together.'], ['Opposite ways', 'One rises while the other falls.'], ['One holds', 'One line stays put while the other moves.']],
                    'smooth' => 'In this style, unisons, thirds, fifths, sixths and octaves sound smooth; seconds, fourths and sevenths rub, and a rub wants to move.',
                    // The table beside the figures: the course's own lists (lesson 4, Smooth and rubbing).
                    'table_title' => 'Smooth and rubbing, in this style',
                    'smooth_label' => 'Smooth',
                    'smooth_list' => ['unison', 'third', 'fifth', 'sixth', 'octave'],
                    'rub_label' => 'Rub',
                    'rub_list' => ['second', 'fourth', 'seventh'],
                    'rub_note' => 'A rub wants to move. In the last book you hold one into the next note, where it rubs, then let it fall a step.',
                ],
                'units_say' => [
                    'Lead a round, follow which voice moved, hold a drone and a repeating pattern.',
                    'Come in second, sing a round in minor, a canon a bar behind, and the middle seat.',
                    'Call and response, a slow line under a tune, a descant above it, and two songs at once.',
                    'Smooth and rubbing notes, the ways lines move, and the first two species.',
                    'Choose your notes, hold one into a rub and let it fall, then write a counterpoint.',
                ],
                'try' => [
                    'lead' => 'You need your phone\'s voice recorder and a quiet room. Headphones help.',
                    'steps' => [
                        'Record yourself singing Row, Row, Row Your Boat twice through.',
                        'Play it back. When it reaches "Gently down the stream", start singing from the top.',
                        'Keep your own line going while your recording sings different words against you.',
                        'If it pulls you onto its notes, listen more and sing no louder: that\'s the skill.',
                    ],
                    'after' => 'Lesson 1 does this with three voices joining you two bars apart, then plays a round where all four voices are you.',
                    'figure' => 'Three voices, one tune, each two bars after the last.',
                ],
                'facts' => [
                    ['Row, Row, Row Your Boat was in print by 1852, though its early tune differs from today\'s.', 'Wikipedia, Row, Row, Row Your Boat', 'https://en.wikipedia.org/wiki/Row,_Row,_Row_Your_Boat'],
                    ['Pachelbel\'s Canon is a true canon: three violins two bars apart, over a bass that repeats.', 'Wikipedia, Pachelbel\'s Canon', 'https://en.wikipedia.org/wiki/Pachelbel%27s_Canon'],
                    ['Fux\'s fourth species is all suspensions: notes tied over, rubbing on the strong beat, then falling a step.', 'Open Music Theory, Fourth species', 'https://viva.pressbooks.pub/openmusictheory/chapter/fourth-species-counterpoint/'],
                ],
                'who' => [
                    'Choir singers who get pulled onto the tune or the loudest part.',
                    'Anyone who enjoys rounds and wants to hold the hardest seat.',
                    'Songwriters and arrangers who want to write a second line that stands on its own.',
                ],
                'need' => [
                    'Headphones, wired if you have them, so you hear your own voice against the others.',
                    'A few minutes a day.',
                    'No reading music: the notes are shapes on a lane, and the rules come one at a time.',
                ],
                'faq' => [
                    ['How long does it take?', '22 lessons of 2 to 4 minutes, about 70 minutes in all. Each book ends in a challenge.'],
                    ['Is it safe for my voice?', 'Yes. You never sing louder to be heard: you listen more instead. Stop if anything tightens or hurts. It isn\'t a voice teacher.'],
                    ['Do I need to read music?', 'No. You learn each line by ear from a guide, and the rules come one at a time, as you meet them.'],
                    ['Is this music theory?', 'It\'s singing first. You hear and sing why notes rub and how lines move, then use the same rules to choose your own notes.'],
                    ['What does it cost?', 'Lesson 1 is free, with no account needed. The rest is part of Plus; saving your progress takes a free account.'],
                ],
                'related' => ['improvising-harmony', 'barbershop', 'ear-training'],
                // The treatise's chapter heads, one a section.
                'kicks' => ['argument' => 'Chapter I · The argument', 'motion' => 'Chapter II · Of motion', 'books' => 'Chapter III · The lessons', 'exercise' => 'Chapter IV · An exercise', 'margins' => 'In the margins', 'reader' => 'To the reader', 'questions' => 'Questions and answers', 'finis' => 'Finis'],
                'book_n' => 'Book :n',
                'try_first' => 'Start here.',
                'finis' => 'Lesson 1 takes a few minutes: you start the round, and three voices join you two bars apart.',
            ],
            'vibrato' => [
                'group' => 'skills',
                'name' => 'Vibrato',
                'summary' => 'A straight tone first, then finding the wave and letting it happen.',
                'title' => 'Vibrato course',
                'description' => 'A straight tone first, then finding the wave and letting it happen. A singing skills course on Stackapella Plus, in short lessons, with the first lesson free.',
                'headline' => 'Find your vibrato',
                'lead' => 'Vibrato is a gentle, regular wave in your pitch. It comes from an easy throat and steady breath, not from pushing. The course starts with a straight tone, then finds the wave and lets it happen.',
                'learn' => ['A steady straight tone, the way choirs tune', 'Finding the wave: its speed and its width', 'Letting it happen, a moment into a long note', 'Choosing it: when a song wants vibrato, and when it doesn\'t'],
                'measures' => 'The app hears how steady your note\'s centre is, how fast and wide any wave is, how even it is, and when it starts. Your jaw and belly are yours to check.',
                'scoring' => ['game' => 'Each lesson earns up to three stars and XP, and on the steered drills a combo counts your even waves in a row. Wider never earns more.', 'serious' => 'The same lesson shows the plain measure instead, such as "Straight for 7 of 8 seconds", with no stars or rewards.'],
                'safety' => ['Keep your jaw, tongue and belly still: a wave is never shaken in. Sing at a medium volume, in the middle of your voice.', 'Stop if anything tightens, tickles or hurts. Don\'t practise when hoarse or ill.', 'If your voice shakes when you speak too, that isn\'t vibrato. A doctor can check it.'],
                'songs' => [['Amazing Grace', 'Public domain hymn (words 1779, tune 1829)'], ['Shenandoah', 'Traditional, public domain'], ['The Water Is Wide', 'Traditional, public domain']],
                'shots' => ['Vibrato\'s first lesson, Swing your voice: a wavy line, and three steps: hum softly, swing up and down, then settle on one easy note.', 'A calm, straight line: a held "oo" drawn flat along its target, and Hear yourself saying "Straight for 4 of 4 seconds."'],
                // The designed page (site/courses/vibrato): its own sections and words.
                'app' => 'vibrato',
                'eyebrow' => 'A Plus course · 17 lessons',
                'hero_title' => 'Let the wave come.',
                'hero_lead' => 'Vibrato is a gentle, regular wave in your pitch. It isn\'t pushed: it grows from an easy note, steady breath and a free throat. You start straight, learn what a wave feels like, let yours happen, then choose when to use it.',
                'hero_facts' => ['17 lessons, about 3 minutes each', 'Under an hour in all', 'No reading music'],
                'see_lessons' => 'See the lessons',
                'titles' => [
                    'outcomes' => 'By the end, you can',
                    'kinds' => 'Straight, a wave, a wobble',
                    'gentle' => 'Never force it',
                    'units' => 'From a straight note to your own wave',
                    'try' => 'Swing your voice',
                    'bank' => 'Before you begin',
                    'inside' => 'What the app listens for',
                    'facts' => 'About the wave',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'join' => 'Let it happen',
                    'related' => 'Good with this course',
                ],
                'outcomes' => [
                    'Hold a calm, straight note, the way choirs tune their chords.',
                    'Hear a straight tone, an even wave and a wobble apart.',
                    'Steer a wave, slow and quick, wide and small.',
                    'Let a natural vibrato come, and choose when a song wants it.',
                ],
                'kinds' => [
                    ['Straight', 'A calm, steady line. It\'s how chords ring, and it\'s light, never a held throat.'],
                    ['A wave', 'An even, gentle wave. Classical singers\' vibrato usually runs about 5.5 to 7 a second.'],
                    ['A wobble', 'Slow, wide and uneven. It sounds unsteady, and the course never calls it vibrato.'],
                ],
                'kinds_body' => 'The app draws your voice as you sing, so you can see a wave you can\'t yet hear in yourself.',
                'gentle' => [
                    'lead' => 'Vibrato grows by itself from an easy note, sung softly. Never force a wave, or shake it from your jaw or throat.',
                    'points' => ['An easy volume, in the middle of your voice.', 'Jaw, tongue and belly still.', 'Steered wave drills about five minutes at a time, then a rest.', 'If your voice shakes when you speak too, that isn\'t vibrato. A doctor can check it.'],
                ],
                'units_say' => [
                    'Swing your voice, an easy long note, a calm straight line, and hearing the difference.',
                    'Steer a wave: slow to quicker, smaller, echoing the guide, then on a tune.',
                    'Let the wave settle on its own, on easy vowels, then in Shenandoah.',
                    'Choose it: start straight and let it bloom, on and off, and The Water Is Wide.',
                ],
                'try' => [
                    'lead' => 'Softly, in the middle of your voice. Stop if anything tightens or hurts.',
                    'steps' => [
                        'Hum softly on "mm", at a comfortable note.',
                        'Swing your hum slowly up a little and back down, like a gentle swing, a few times.',
                        'Then settle on one easy note and hold it, calm and straight. Keep your jaw and belly still.',
                        'If a small wave comes by itself near the end, let it. If it doesn\'t, that\'s fine too.',
                    ],
                    'after' => 'Lesson 1 draws your swing as a wave on the screen, then listens to your easy note and tells you if a natural vibrato showed up.',
                ],
                'facts' => [
                    ['Classical singers\' vibrato usually runs about 5.5 to 7 a second.', 'Prame, JASA (1994)', 'https://doi.org/10.1121/1.410141'],
                    ['On a held note, listeners hear the pitch at the middle of the wave.', 'Shonle and Horan, JASA (1980)', 'https://doi.org/10.1121/1.383733'],
                    ['Vibrato comes with regular pulses in the muscles that stretch your vocal folds.', 'Hsiao, Solomon, Luschei and Titze, J. Voice (1994)', 'https://iro.uiowa.edu/esploro/outputs/journalArticle/Modulation-of-fundamental-frequency-by-laryngeal/9984719752102771'],
                ],
                'who' => [
                    'Singers whose long notes sound flat and lifeless, or shaky.',
                    'Choir singers who need a straight tone, and soloists who want a wave: you learn both.',
                    'Anyone who has wondered whether they have a vibrato, and what to do about it.',
                ],
                'need' => [
                    'A quiet spot where you can sing softly.',
                    'A few minutes at a time: the wave drills are short on purpose.',
                    'No reading music: you see your voice as a line and the guide as a ribbon.',
                ],
                'faq' => [
                    ['How long does it take?', '17 lessons of about 3 minutes, under an hour in all. A natural vibrato can take longer to settle than the course does, so come back to the later lessons.'],
                    ['Is it safe for my voice?', 'Yes, sung softly. Never force a wave or shake it from your jaw, keep the steered drills short, and stop if anything tightens. If your voice shakes when you speak too, a doctor can check it.'],
                    ['Do I need to read music?', 'No. You hear each exercise from a guide and see your voice drawn as a line.'],
                    ['What if no vibrato comes?', 'That\'s fine. A calm, straight tone always counts, and many voices find their wave later, from the same easy note.'],
                    ['What does it cost?', 'Lesson 1 is free, with no account needed. The rest is part of Plus; saving your progress takes a free account.'],
                ],
                'related' => ['breath-and-stamina', 'perfect-your-pitch', 'pop-and-country'],
                // The lake's labels, one a section, and the close.
                'kicks' => ['still' => 'Still water', 'surfaces' => 'Three surfaces', 'calm' => 'Calm water', 'stones' => 'Stepping stones', 'wade' => 'Wade in', 'ripples' => 'Ripples', 'bank' => 'On the bank', 'questions' => 'Questions', 'close' => 'Let it come'],
                'try_first' => 'Start here.',
                'close' => 'Lesson 1 takes about three minutes: swing your voice, settle on one easy note, and watch your voice drawn as you sing.',
            ],
            'range-and-registers' => [
                'group' => 'skills',
                'name' => 'Range and registers',
                'summary' => 'Chest, head voice, falsetto and the mix, with smooth breaks.',
                'title' => 'Range and registers course',
                'description' => 'Chest, head voice, falsetto and the mix, with smooth breaks. A singing skills course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Your whole range, with smooth breaks',
                'lead' => 'Chest voice, head voice, falsetto and the mix between them: where each sits in your voice, and how to cross between them without a crack.',
                'learn' => ['Finding your range, low to high', 'Chest voice and head voice', 'Falsetto, and the mix', 'Smooth breaks, and safe high and low notes'],
                'needs' => 'telling one register from another by its sound',
                'health' => true,
            ],
            'belting' => [
                'group' => 'skills',
                'name' => 'Belting, safely',
                'summary' => 'A strong, bright sound without strain.',
                'title' => 'Belting, safely course',
                'description' => 'A strong, bright sound without strain. A singing skills course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Belting, safely',
                'lead' => 'A strong, bright sound for big moments, built without strain.',
                'learn' => ['What belting is, and what it isn\'t', 'Support and a bright, forward sound', 'Keeping it free, never forced'],
                'needs' => 'hearing how loud and how bright you sing',
                'health' => true,
            ],
            'breath-and-stamina' => [
                'group' => 'skills',
                'name' => 'Breath and stamina',
                'summary' => 'Long phrases, support and quiet starts.',
                'title' => 'Breath and stamina course',
                'description' => 'Long phrases, support and quiet starts. A singing skills course on Stackapella Plus, in short lessons, with the first lesson free.',
                'headline' => 'Breath for long phrases',
                'lead' => 'Breath that lasts to the end of the line: long phrases, steady support, and notes that start softly and in tune.',
                'learn' => ['Breathing low and easy', 'Long phrases, steady to the end', 'Quiet starts, in tune from the first moment'],
                'measures' => 'The app hears your steady seconds, whether a note stays steady to its end, and when you stop and come back after a breath. Ribs and shoulders are yours to check.',
                'scoring' => ['game' => 'Up to three stars and XP for how your hold ended, how steady it was and reaching your own goal. Holding longer than that earns nothing more.', 'serious' => 'The same hold shows the plain measure, such as "Steady to the end: 9.4 steady seconds", with no stars or rewards.'],
                'safety' => ['Never hold your breath before you sing, and stop while it\'s still easy.', 'With asthma, a heart or lung condition, or in pregnancy, keep holds short and easy.', 'A cough, wheeze, tight chest or feeling lightheaded means stop.'],
                'songs' => [['Swing Low, Sweet Chariot', 'Spiritual, public domain'], ['Ah! vous dirai-je, maman', 'French tune (1761), public domain'], ['Simple Gifts', 'Public domain (1848)']],
                'shots' => ['Breath and stamina\'s first lesson, The long note: a breathing flower, and why a long note is about letting the air out slowly.', 'A first hold of "oo": a bar showing 2.9 steady seconds, and Hear yourself saying it stayed steady and stopped while it was easy.'],
                // The designed page (site/courses/breath-and-stamina): its own sections and words.
                'app' => 'breath',
                'eyebrow' => 'A Plus course · 15 lessons',
                'hero_title' => 'Steady to the end.',
                'hero_lead' => 'A long note isn\'t about big lungs. It\'s letting the air out slowly and stopping while it\'s still easy. You learn to breathe low and wide, end a note on purpose, start one on the breath, and catch quick breaths without going flat.',
                'hero_facts' => ['15 lessons, about 3 minutes each', 'Goals capped at 15 seconds', 'No reading music'],
                'see_lessons' => 'See the lessons',
                'titles' => [
                    'outcomes' => 'By the end, you can',
                    'ending' => 'A long note is about its ending',
                    'easy' => 'Easy, never gasping',
                    'units' => 'Four legs, one breath at a time',
                    'try' => 'The long note',
                    'crew' => 'Before you sail',
                    'inside' => 'What the app listens for',
                    'facts' => 'About breathing',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'join' => 'Take a breath.',
                    'related' => 'Good with this course',
                ],
                'outcomes' => [
                    'Breathe low and wide, and keep your ribs from closing too fast.',
                    'Hold a long note steady to the end, and stop it on purpose.',
                    'Start a note on the breath, with no hold and no hard catch.',
                    'Plan your breaths, and catch quick ones without going flat.',
                ],
                'ending' => [
                    'lead' => 'As the air runs low, a held note slides flat: pitch rises and falls with the air pressure under your vocal folds. So the skill isn\'t holding longer. It\'s ending on purpose.',
                    'out' => ['Running out', 'Held until the air is gone: the end sags and cracks.'],
                    'purpose' => ['Ending on purpose', 'Stopped while it\'s still steady: a clean finish, and air left for the next line.'],
                    'body' => 'The course measures your steady seconds and how your note ended. Holding longer than your own goal earns nothing more.',
                ],
                'easy' => [
                    'lead' => 'Breathe low and wide, never gasp, and stop while it\'s still easy. Never squeeze out the last drop.',
                    'points' => ['Never hold your breath before you sing, and never lock your stomach.', 'At least three easy breaths between holds, and at most three long holds in a row.', 'With asthma, a lung or heart condition, or in pregnancy, keep holds short and easy.', 'A cough, wheeze, tight chest or feeling lightheaded means stop. Sit for long holds if you\'ve ever felt lightheaded.'],
                ],
                'units_say' => [
                    'A long note that ends on purpose, wide ribs, a long line and an easy hold.',
                    'An even stream of air: a buzz, a trill up the hill, a planned ending, and a chord of your own.',
                    'Notes that start on the breath, short bouncy ones, and Ah, vous dirai-je.',
                    'Quick breaths, planned breaths, breathing in turns, and Simple Gifts\' first verse.',
                ],
                'try' => [
                    'lead' => 'Sit or stand tall and easy. Stop if you feel lightheaded.',
                    'steps' => [
                        'Hum a few easy slides, low to high and back, to warm up.',
                        'Breathe in low and wide, without lifting your shoulders.',
                        'Sing a comfortable note on "oo", soft and steady, and count the seconds in your head.',
                        'Stop while it\'s still steady, before the air runs out. Next time, aim for one more steady second, still ending on purpose.',
                    ],
                    'after' => 'Lesson 1 holds your note over a drone, draws your steady seconds as a bar, and tells you how your note ended.',
                ],
                'facts' => [
                    ['Appoggio comes from the Italian appoggiare, to lean: your breathing muscles lean against each other, so the ribs stay wide while you sing.', 'Rider University, The Breathing Mechanism for Singing', 'https://explore.rider.edu/sites/default/files/files/vthc-a-review-of-the-breathing-mechanism-for-singing-part2.pdf'],
                    ['Voice clinics use lip trills and straw singing: partly closing the mouth sends some air pressure back toward the vocal folds, helping them vibrate easily.', 'Titze, J. Speech Lang. Hear. Res. (2006)', 'https://pubs.asha.org/doi/abs/10.1044/1092-4388(2006/035)'],
                    ['Pitch rises and falls with the air pressure under your vocal folds, so as the air runs low, a held note slides flat.', 'Titze, JASA (1989)', 'https://iro.uiowa.edu/esploro/outputs/journalArticle/On-the-relation-between-subglottal-pressure/9984719749002771'],
                ],
                'who' => [
                    'Singers who run out of air at the end of a line, or go flat on long notes.',
                    'Choir singers who want to breathe without breaking the phrase.',
                    'Anyone who gasps or lifts their shoulders to breathe.',
                ],
                'need' => [
                    'A quiet spot, and a chair if you\'d like to sit for long holds.',
                    'A few minutes a day: the lessons keep holds short and rests between them.',
                    'No reading music: you see your steady seconds as a bar.',
                ],
                'faq' => [
                    ['How long does it take?', '15 lessons of about 3 minutes. Breath grows over days, so a lesson a day suits it.'],
                    ['Is it safe for my voice?', 'Yes, the course\'s way: breathe low and easy, stop while it\'s still easy, and never hold your breath first. With asthma, a lung or heart condition, or in pregnancy, keep holds short, and stop for a cough, wheeze or lightheadedness.'],
                    ['Do I need to read music?', 'No. You hear each exercise from a guide and see your note drawn as you sing.'],
                    ['Will it make my lungs bigger?', 'No. It\'s about letting the air out slowly and evenly, and ending on purpose.'],
                    ['What does it cost?', 'Lesson 1 is free, with no account needed. The rest is part of Plus; saving your progress takes a free account.'],
                ],
                'related' => ['vibrato', 'perfect-your-pitch', 'folk-and-early-music'],
                // The voyage's labels, one a section, and the close.
                'kicks' => ['bearings' => 'Bearings', 'coming' => 'Coming in', 'weather' => 'Fair weather', 'voyage' => 'The voyage', 'castoff' => 'Cast off', 'log' => 'Ship\'s log', 'crew' => 'Crew list', 'questions' => 'Questions', 'close' => 'Set sail'],
                'leg_n' => 'Leg :n',
                'entry_n' => 'Entry :n',
                'try_first' => 'Start here.',
                'close' => 'Lesson 1 takes about three minutes: one long note over a drone, as long as it\'s easy, then again with your first hold to beat.',
            ],
            'diction' => [
                'group' => 'skills',
                'name' => 'Diction for singers',
                'summary' => 'Pure vowels, and consonants on time.',
                'title' => 'Diction for singers course',
                'description' => 'Pure vowels, and consonants on time. A singing skills course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Words people can hear',
                'lead' => 'Pure vowels that blend, and consonants that land on time, so a choir sings one word, not twenty. Latin and Italian for choir, too.',
                'learn' => ['Pure vowels that blend', 'Consonants on the beat', 'Latin and Italian sounds for choir'],
                'needs' => 'recognising vowels',
            ],
            'sight-singing' => [
                'group' => 'skills',
                'name' => 'Sight-singing',
                'summary' => 'Read a stave and sing it, with solfège.',
                'title' => 'Sight-singing course',
                'description' => 'Read a stave and sing it, with solfège. A singing skills course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Read a stave and sing it',
                'lead' => 'Look at a line of music and sing it: the stave, intervals, and do re mi, a little at a time.',
                'learn' => ['Reading the stave', 'Solfège: do re mi', 'Steps, skips and leaps', 'Singing a new line at first sight'],
            ],
            'ear-training' => [
                'group' => 'skills',
                'name' => 'Ear training',
                'summary' => 'Intervals, chords, major and minor, by ear.',
                'title' => 'Ear training course',
                'description' => 'Intervals, chords, major and minor, by ear. A singing skills course on Stackapella Plus, in short lessons, with the first lesson free.',
                'headline' => 'Hear it, name it, sing it',
                'lead' => 'Hear an interval or a chord and know what it is: the ear that makes harmony easier.',
                'learn' => ['Intervals by ear', 'Major and minor', 'Chords, and which note you sing in them'],
                'measures' => 'The app hears where each sung note lands and whether it\'s the leap you were asked for. It doesn\'t check your syllables, and it never times your answers.',
                'scoring' => ['game' => 'Listening rounds teach and never give stars: up to three stars and XP come from the leaps you sing, each one a dart on the Bullseye board.', 'serious' => 'The same lesson shows the plain measure, such as "You found 5 of 6 leaps", with no stars or rewards.'],
                'safety' => ['Short, easy notes at a medium volume, in the middle of your voice.', 'Stop if anything tightens or hurts. Don\'t practise when hoarse or ill.'],
                'songs' => [['Oh When the Saints', 'Spiritual, public domain'], ['Amazing Grace', 'Public domain hymn (words 1779, tune 1829)'], ['Twinkle, Twinkle', 'French tune (1761), public domain'], ['Ode to Joy', 'Public domain melody (1824)'], ['Frère Jacques', 'Traditional, public domain'], ['Greensleeves', 'Anonymous, 16th century, public domain'], ['My Bonnie Lies over the Ocean', 'Traditional, public domain'], ['Swing Low, Sweet Chariot', 'Spiritual, public domain'], ['This Old Man', 'Traditional, public domain'], ['O Come, All Ye Faithful', '18th-century tune, public domain']],
                'shots' => ['Ear training\'s first lesson, Song starts: three leaps from songs you know, Oh When the Saints, Amazing Grace and Twinkle, Twinkle.', 'Echoing the song-start leaps on the Bullseye board: a dart in the centre, each landing in cents, and Hear it right to hear the leap.'],
                // The designed page (site/courses/ear-training): its own sections and words.
                'app' => 'ear',
                'eyebrow' => 'A Plus course · 17 lessons',
                'hero_title' => 'Hear it, then sing it.',
                'hero_lead' => 'Hear how far apart two notes are, whether a chord is major or minor, and where home is, then sing it back. You start from leaps you already carry in songs you know, and every lesson ends in singing.',
                'hero_facts' => ['17 lessons, 3 to 4 minutes each', 'Every lesson ends in singing', 'No reading music'],
                'see_lessons' => 'See the lessons',
                'titles' => [
                    'outcomes' => 'By the end, you can',
                    'leaps' => 'Leaps you already carry',
                    'colour' => 'Bright or dark',
                    'units' => 'Four trips, from song starts to the bass line',
                    'try' => 'Song starts',
                    'kit' => 'Before you set out',
                    'inside' => 'What the app listens for',
                    'facts' => 'About listening',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'join' => 'Open your ears.',
                    'related' => 'Good with this course',
                ],
                'outcomes' => [
                    'Hear and sing leaps of three, four and five, from songs you already know.',
                    'Tell half steps from whole steps, big threes from small, and up from down.',
                    'Hear whether a chord is major or minor, and sing the third that decides it.',
                    'Feel where home is, and sing a chord\'s root, the way a bass does.',
                ],
                'leaps' => [
                    'lead' => 'You don\'t start from theory. You start from the openings of songs you know, each one a leap, and keep each leap with its song.',
                    'kinds' => [
                        ['A leap of three', 'Oh When the Saints', 'From "Oh" up to "when": do to mi.'],
                        ['A leap of four', 'Amazing Grace', 'From "A-" up to "-ma-": so below to do.'],
                        ['A leap of five', 'Twinkle, Twinkle', 'From the first "-kle" up to the next "twin-": do to so.'],
                    ],
                    'body' => 'Leaps are named in plain words first, with do re mi beside them and the textbook name, like "major third", small.',
                ],
                'colour' => [
                    'lead' => 'A chord\'s middle note, its third, decides whether it sounds major or minor: bright or dark. Lower it a half step and the whole chord changes colour.',
                    'major' => ['Major', 'do, mi, so'],
                    'minor' => ['Minor', 'do, me, so ("may")'],
                ],
                'units_say' => [
                    'Leaps from songs you know: up or down, step or leap, three or five.',
                    'Four or five, half and whole steps, big and small threes, six and eight, and leaps down.',
                    'Bright or dark: hear a chord\'s third and sing it, in chords and in tunes.',
                    'Feel home after a phrase, sing a chord\'s root, and then a bass line.',
                ],
                'try' => [
                    'lead' => 'Three songs you probably know, and the leap that opens each one.',
                    'steps' => [
                        'Sing "Oh when the saints" and stop on "when". From "Oh" up to "when" is a leap of three.',
                        'Sing "Amazing grace" and stop on "-ma-". From "A-" up to "-ma-" is a leap of four.',
                        'Sing "Twinkle, twinkle". From the first "-kle" up to the second "twin-" is a leap of five.',
                        'Now hum any easy note, and try each leap up from it, thinking of its song.',
                    ],
                    'after' => 'Lesson 1 plays each song\'s opening, then each leap on its own, and every leap you sing back lands as a dart on the board. At the end, your leap plays over the guide\'s note: that\'s harmony.',
                ],
                'facts' => [
                    ['Twinkle\'s tune was first printed in France in 1761. The alphabet song uses the same tune.', 'Wikipedia, Ah! vous dirai-je, maman', 'https://en.wikipedia.org/wiki/Ah!_vous_dirai-je,_maman'],
                    ['Mahler turned Frère Jacques into a slow minor march in the third movement of his First Symphony.', 'Wikipedia, Symphony No. 1 (Mahler)', 'https://en.wikipedia.org/wiki/Symphony_No._1_(Mahler)'],
                    ['Phones barely carry the lowest notes of a deep voice, yet you still hear its pitch: your brain fills in the missing fundamental.', 'Wikipedia, Missing fundamental', 'https://en.wikipedia.org/wiki/Missing_fundamental'],
                ],
                'who' => [
                    'Singers who can follow a guide but can\'t yet hear where a harmony goes.',
                    'Anyone who has wondered what "a third" or "minor" actually sounds like.',
                    'Choir singers who want to find their note from the chord, not the piano.',
                ],
                'need' => [
                    'A quiet spot and a few minutes a day.',
                    'Headphones help you hear the guide clearly.',
                    'No reading music and no theory: leaps are named in plain words.',
                ],
                'faq' => [
                    ['How long does it take?', '17 lessons of 3 to 4 minutes. Ears grow with repetition, so a lesson a day suits it well.'],
                    ['Do I need to know the interval names?', 'No. Leaps are named in plain words first, like "a leap of three", with do re mi beside them and the textbook name small.'],
                    ['Is it only listening?', 'No. Listening rounds teach and never give stars. Every lesson ends in singing, and your stars come from the leaps you sing.'],
                    ['Is it safe for my voice?', 'Yes. The notes are short and easy, at a medium volume in the middle of your voice. Stop if anything tightens or hurts.'],
                    ['What does it cost?', 'Lesson 1 is free, with no account needed. The rest is part of Plus; saving your progress takes a free account.'],
                ],
                'related' => ['perfect-your-pitch', 'improvising-harmony', 'barbershop'],
                // The field guide's labels, one a section, and the close.
                'kicks' => ['life' => 'Your life list', 'guide' => 'The field guide', 'colour' => 'Day and dusk', 'trips' => 'Field trips', 'field' => 'In the field', 'notes' => 'Field notes', 'kit' => 'Your field kit', 'questions' => 'Questions', 'close' => 'Go listening'],
                'plate_n' => 'Plate :n',
                'trip_n' => 'Trip :n',
                'try_first' => 'Start here.',
                'close' => 'Lesson 1 takes a few minutes: three song openings, and each leap you sing back lands on the board.',
            ],
            'perfect-your-pitch' => [
                'group' => 'skills',
                'name' => 'Perfect your pitch',
                'summary' => 'Land on a note cleanly, hold it steady, and tune to a drone.',
                'title' => 'Perfect your pitch course',
                'description' => 'Land on a note cleanly, hold it steady, and tune to a drone. A singing skills course on Stackapella Plus, in short lessons, with the first lesson free.',
                'headline' => 'Hit the note',
                'lead' => 'Land on the note, hold it steady, and tune to a drone until it rings.',
                'learn' => ['Landing cleanly on a note', 'Holding it steady', 'Tuning to a drone'],
                'measures' => 'The app hears where each note lands, whether you slid in, how close you hold to a drone, and whether a long note sags. A tight neck or jaw is yours to check.',
                'scoring' => ['game' => 'Every note you sing back lands as a dart on the Bullseye board, and the lesson earns up to three stars and XP for how close they landed.', 'serious' => 'The same board, labelled in cents, with the plain measure instead of stars, such as "4 of 5 landed within 25 cents".'],
                'safety' => ['Pitch comes from breath and listening, never from squeezing: tightening pushes you sharp.', 'Keep headphones at a moderate volume, with one ear off.', 'Stop if anything tightens, tickles or hurts. Don\'t practise when hoarse or ill.'],
                'songs' => [['The Westminster chimes', 'Public domain (1793)'], ['Red River Valley', 'Traditional, public domain'], ['Amazing Grace', 'Public domain hymn (words 1779, tune 1829)'], ['Ode to Joy', 'Public domain melody (1824), sung on "loo"']],
                'shots' => ['Perfect your pitch\'s first lesson, Bullseye: land right on a note, don\'t slide into it, in three steps: listen, think it, land on "dah".', 'The Bullseye board after two echoes: both darts in the centre, between Sharp and Flat, and "2 of 2 landed within 25 cents."'],
                // The designed page (site/courses/perfect-your-pitch): its own sections and words.
                'app' => 'pitch',
                'eyebrow' => 'A Plus course · 17 lessons',
                'hero_title' => 'Land right on it.',
                'hero_lead' => 'Most of singing in tune is landing on a note cleanly, holding it steady, and tuning to what\'s around you. You hear a note, land on it like a dart, lock to a drone until it rings, and carry your own note in your head.',
                'hero_facts' => ['17 lessons, about 3 minutes each', 'Under an hour in all', 'No reading music'],
                'see_lessons' => 'See the lessons',
                'titles' => [
                    'outcomes' => 'By the end, you can',
                    'relative' => 'Every note, a step from your anchor',
                    'ring' => 'Tuning by the beats',
                    'units' => 'Four ends, from the bullseye to your own note',
                    'try' => 'A clean landing',
                    'kit' => 'Before you shoot',
                    'inside' => 'What the app listens for',
                    'facts' => 'About pitch',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'join' => 'Take aim.',
                    'related' => 'Good with this course',
                ],
                'outcomes' => [
                    'Land on a note cleanly, without scooping up into it.',
                    'Lock to a drone until it rings, and hear the beats slow as you get close.',
                    'Hold your note while the chords change and a close part pulls at your ear.',
                    'Find your own note with nothing given, and start a song from it.',
                ],
                'relative' => [
                    'lead' => 'This course trains relative pitch: hearing each note against another, and landing on it. Absolute pitch, naming a note with nothing to compare, is rare, and the course doesn\'t promise it.',
                    'body' => 'Relative pitch is what harmony needs. Your own comfortable note becomes an anchor you carry, and every note of the scale is a step from it.',
                ],
                'ring' => [
                    'lead' => 'Two notes a little apart beat: a slow wah-wah. The beats slow down as you get closer, and stop when you\'re there.',
                    'body' => 'The beat rate is exactly the gap between the two notes: two notes 3 hertz apart beat three times a second.',
                    'apart' => 'A little apart: beats',
                    'there' => 'Right there: still',
                ],
                'units_say' => [
                    'Land like a dart, clean instead of scooped, then think a note before you leap to it.',
                    'Lock to a drone: hear the ring, the octave and fifth, coming back, and no sag.',
                    'Hold your note while chords change and parts pull, then stack yourself.',
                    'Your note from memory, kept in mind, every note from your anchor, and a song you start yourself.',
                ],
                'try' => [
                    'lead' => 'You need a held note: a keyboard app, a tuning app\'s drone, or a recording of one note.',
                    'steps' => [
                        'Play a comfortable note and listen. Then stop the sound.',
                        'Hear it in your head for a moment before you sing.',
                        'Sing "dah", starting right on the note: no slide up into it.',
                        'If you started low and slid up, that\'s a scoop: think the note, breathe, and start right on it.',
                    ],
                    'after' => 'In lesson 1 the guide sings each note, and each echo lands as a dart on the board as you finish it. After two rounds, you hear how much closer you moved.',
                ],
                'facts' => [
                    ['In Indian classical music a tanpura plays only a drone, the home note and its fifth, under the whole performance, and the singer chooses that home note.', 'Wikipedia, Tanpura', 'https://en.wikipedia.org/wiki/Tanpura'],
                    ['In a 2006 study, conservatory students who grew up speaking Mandarin, a tone language, were far more likely to have absolute pitch than American students.', 'Deutsch et al., JASA (2006)', 'https://doi.org/10.1121/1.2151799'],
                    ['The Westminster chimes were written in 1793 for Great St Mary\'s Church, Cambridge, and are said to grow from a phrase of Handel\'s Messiah.', 'Wikipedia, Westminster Quarters', 'https://en.wikipedia.org/wiki/Westminster_Quarters'],
                ],
                'who' => [
                    'Singers who\'ve been told they\'re a little sharp or flat, or who scoop into notes.',
                    'Choir singers who drift when the chords change or a neighbour sings close by.',
                    'Anyone who wants to start a song in tune without a note to help.',
                ],
                'need' => [
                    'Headphones for the drone lessons, one ear off, at a moderate volume.',
                    'A quiet spot and a few minutes a day.',
                    'No reading music: your notes land as darts, and the drone is a sound to tune to.',
                ],
                'faq' => [
                    ['How long does it take?', '17 lessons of about 3 minutes, under an hour in all. Pitch settles with practice, so short, regular sessions help most.'],
                    ['Will this give me perfect pitch?', 'No. It trains relative pitch: landing on a note, holding it, and tuning to a drone. That\'s what singing in tune needs. Absolute pitch is rare, and the course doesn\'t promise it.'],
                    ['Is it safe for my voice?', 'Yes. Pitch comes from breath and listening, never from squeezing. Keep headphones at a moderate volume, and stop if anything tightens or hurts.'],
                    ['Do I need to read music?', 'No. You hear each note, sing it back, and see where it landed.'],
                    ['What does it cost?', 'Lesson 1 is free, with no account needed. The rest is part of Plus; saving your progress takes a free account.'],
                ],
                'related' => ['ear-training', 'vibrato', 'barbershop'],
                // The range's labels, one a section, and the close.
                'kicks' => ['aim' => 'Aim', 'anchor' => 'The anchor point', 'target' => 'On target', 'ends' => 'Four ends', 'first' => 'First arrows', 'range' => 'From the range', 'kit' => 'Your kit', 'questions' => 'Questions', 'close' => 'Take aim'],
                'end_n' => 'End :n',
                'try_first' => 'Start here.',
                'close' => 'Lesson 1 takes about three minutes: the guide sings a note, you echo it, and each echo lands on the board.',
            ],
            'scat' => [
                'group' => 'harmony',
                'name' => 'Scat singing',
                'summary' => 'Jazz syllables, swing, and short improvised lines.',
                'title' => 'Scat singing course',
                'description' => 'Jazz syllables, swing, and short improvised lines. A harmony course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Scat singing',
                'lead' => 'Jazz syllables, a swing feel, and short lines of your own that land on the chord.',
                'learn' => ['Scat syllables', 'Swing', 'Short lines that land on chord tones'],
            ],
            'barbershop' => [
                'group' => 'harmony',
                'name' => 'Barbershop and tags',
                'summary' => 'Ringing chords tuned closer than a piano can, the four barbershop parts, and original tags.',
                'title' => 'Barbershop and tags course',
                'description' => 'Ringing chords tuned closer than a piano can, the four barbershop parts, and original tags. A harmony course on Stackapella Plus, in short lessons, with the first lesson free.',
                'headline' => 'Barbershop and tags',
                'lead' => 'Four voices tuned so closely the chord rings, each barbershop part in turn, and short original endings, called tags, with every part yours to sing.',
                'learn' => ['Tuning a chord until it rings', 'Singing each of the four barbershop parts', 'Tags and the post'],
                'measures' => 'The app hears how close each held chord note sits to where it rings, in cents and which way, and your part\'s notes on pitch. Vowels and tone are yours to judge.',
                'scoring' => ['game' => 'Up to three stars and XP for how close your held notes come to ringing, or for a part half on pitch and half ringing. The piano\'s third never earns ring stars.', 'serious' => 'The same take says it plainly, like "6 cents high: let it fall", with no stars or XP.'],
                'safety' => ['Straight, not squeezed: sing medium-soft, and if your throat tightens, let a little wave back in.', 'The tenor stays light and soft; the bass never presses down, chin level.', 'Wear headphones: through a speaker the mic hears the chord too. Stop if anything hurts.'],
                'songs' => [['Down by the Old Mill Stream', 'Song from 1910, its chorus, public domain; the barbershop parts written for the course'], ['The swipe, the post and the tag', 'Written for the course']],
                'shots' => ['A chord that rings, the first lesson: hear four straight voices ring, then a hole in the chord where the top note, mi, is yours.', 'Holding mi until it rings: 100% on pitch, a steady A3 for five seconds, and Hear it together with the chord.'],
                // The designed page (site/courses/barbershop): its own sections and words.
                'app' => 'barbershop',
                'eyebrow' => 'A Plus course · 16 lessons',
                'hero_title' => 'Make a chord ring.',
                'hero_lead' => 'Four voices, tuned so close the chord hums. Learn to hear the ring and lock your note into it, sing every seat in a quartet, and finish with tags: the short, ringing endings barbershoppers sing just for the chords.',
                'hero_facts' => ['16 lessons, a few minutes each', 'Headphones on', 'No reading music'],
                'see_lessons' => 'See the lessons',
                'titles' => [
                    'outcomes' => 'By the last tag, you can',
                    'ring' => 'Ringing, not beating',
                    'seats' => 'Four seats, four jobs',
                    'units' => 'From one chord to a tag of your own',
                    'try' => 'Find the ring',
                    'inside' => 'What the app hears',
                    'facts' => 'Three things barbershoppers know',
                    'before' => 'Before you sing',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'join' => 'Pull up a chair',
                    'related' => 'Also on the bill',
                ],
                'outcomes' => [
                    'Hear a chord beat, then tune your note until it rings.',
                    'Place the third and the seventh where barbershop does: lower than the piano.',
                    'Sing every seat in the quartet: lead, tenor, baritone and bass.',
                    'Sing a swipe, hold a post, and stack a tag in two voices, both yours.',
                ],
                'ring' => [
                    'lead' => 'When two notes are a little out of tune with each other, the sound wobbles: you hear beats. Tune one of them and the wobble slows, then stops. That\'s the ring.',
                    'body' => 'Barbershop tunes its chords closer than a piano can. The third sits 14 cents below the piano\'s, and the seventh about 31 cents below (a cent is a hundredth of a semitone). The course hears how far you are from the ring, and which way.',
                    'beating' => 'Beating: a little out',
                    'ringing' => 'Ringing: locked',
                    // The needle's three marks, and the two numbers set large.
                    'meter' => ['Low', 'Ring', 'High'],
                    'numbers' => [['14', 'cents', 'The third, below where the piano plays it'], ['31', 'cents', 'The seventh, about this far below the piano\'s']],
                ],
                'seats' => [
                    ['Tenor', 'Floats above the lead, light and soft: the quietest voice in the quartet.'],
                    ['Lead', 'Sings the tune, the second voice from the top. The other three tune to the lead.'],
                    ['Baritone', 'Sings the note the chord still needs, crossing the lead: above it one moment, under it the next.'],
                    ['Bass', 'The floor: roots and fifths. Never pushed down for depth, chin level.'],
                ],
                'seats_note' => 'In unit 3 you sing each seat in the chorus of Down by the Old Mill Stream, with the other three as guides.',
                'units_say' => [
                    'Hear a chord lock, sing on a straight tone, and find the voice that\'s out.',
                    'Build a chord from its floor up to the barbershop seventh, tuned low.',
                    'Lead, bass, tenor and baritone, each in turn, then a seat with no practice first.',
                    'A swipe, a post, and a tag where both voices are you.',
                ],
                'try' => [
                    'lead' => 'Two minutes, one held note to tune to: a keyboard app, a drone from a tuning app, or a friend humming.',
                    'steps' => [
                        'Play or hum a low note and keep it going. Call it do.',
                        'Sing mi, the third note of do re mi, on a straight "oo" with no wave in your voice.',
                        'Listen for a slow wobble in the sound. Slide your note down, very slightly, until the wobble slows and stops.',
                        'That stillness is the ring. Hold it for a breath, then rest.',
                    ],
                    'after' => 'Lesson 1 does this with three tuned voices around you, and tells you after each hold how close you came, in cents.',
                ],
                'facts' => [
                    ['Barbershop harmony has deep roots in Black American quartet singing of the late 1800s.', 'Abbott, American Music (1992)', 'https://www.jstor.org/stable/3051597'],
                    ['When four voices lock, the overtones can seem to add a voice nobody sings. Barbershoppers call it the fifth voice.', 'Barbershop Harmony Society, the language of barbershop', 'https://www.barbershop.org/intro-to-barbershop-learning-the-language-of-barbershop'],
                    ['Early Black quartets called finding a close chord by ear "cracking up a chord". Their slides from chord to chord were called swipes.', 'Abbott, American Music (1992)', 'https://www.jstor.org/stable/3051597'],
                ],
                'who' => [
                    'Singers who\'ve sung a harmony part before, in the free course or anywhere else, and want their chords to ring.',
                    'Choir and a cappella singers who\'d like to try every part of a quartet, not just their own.',
                    'Anyone curious about barbershop who has never had three other singers to try it with.',
                ],
                'need' => [
                    'Headphones. Ringing needs them: through a speaker, the mic hears the chord too.',
                    'A quiet spot and a few minutes a day.',
                    'No reading music: you learn every part by ear, from a guide voice.',
                ],
                'faq' => [
                    ['How long does it take?', '16 short lessons. At one or two a day, you\'re singing tags in about two weeks.'],
                    ['Do I need three other singers?', 'No. The app sings the other three, tuned to ring. In the last lesson, two of the voices are you.'],
                    ['Is it safe for my voice?', 'Yes, sung the course\'s way: straight, not squeezed, medium-soft, in short holds. Stop if anything tightens or hurts. It isn\'t a voice teacher.'],
                    ['Do I need to read music?', 'No. You learn each part by ear from a guide voice, and see its notes as shapes.'],
                    ['What does it cost?', 'Lesson 1 is free, with no account needed. The rest is part of Plus; saving your progress takes a free account.'],
                ],
                'related' => ['improvising-harmony', 'counterpoint', 'ear-training'],
                // The show bill's running labels, one a section.
                'kicks' => ['bill' => 'Tonight\'s bill', 'ring' => 'The ring', 'chairs' => 'The four chairs', 'set' => 'The set list', 'warm' => 'Warm-up', 'shop' => 'From the shop', 'before' => 'House rules', 'fine' => 'The fine print', 'encore' => 'Encore'],
                'act_n' => 'No. :n',
                'set_n' => 'Set :n',
                'try_first' => 'Start here.',
                'encore' => ['title' => 'Pull up a chair.', 'body' => 'Lesson 1 takes a few minutes: three voices tuned and waiting, and a gap in the chord that\'s yours to fill.'],
            ],
            'gospel-runs' => [
                'group' => 'harmony',
                'name' => 'Gospel and soul runs',
                'summary' => 'Riffs, runs and blue notes.',
                'title' => 'Gospel and soul runs course',
                'description' => 'Riffs, runs and blue notes. A harmony course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Gospel and soul runs',
                'lead' => 'Riffs, runs and blue notes, slow at first, then up to speed.',
                'learn' => ['Blue notes', 'Short riffs', 'Runs, slow then up to speed'],
            ],
            'yodeling' => [
                'group' => 'styles',
                'name' => 'Yodeling',
                'summary' => 'Quick flips between chest and head voice.',
                'title' => 'Yodeling course',
                'description' => 'Quick flips between chest and head voice. A styles course on Stackapella Plus, in short lessons, with the first lesson free.',
                'headline' => 'Yodeling',
                'lead' => 'The quick flip between chest voice and head voice, clean and on pitch.',
                'learn' => ['Chest voice and head voice', 'The flip, slow then quick', 'A yodel on a tune'],
                'measures' => 'It finds where your voice flips, then hears how cleanly you jump across it, up and down, and each note\'s pitch. Whether the top sounded lighter is yours to hear.',
                'scoring' => ['game' => 'Up to 3 stars, half for clean flips and half for the notes on pitch, plus XP; in lesson 1 a nearly clean flip counts.', 'serious' => 'The same take says it plainly, like "6 of 8 flips clean, up and down", with no stars or XP.'],
                'safety' => ['The flip is a light flick, never a hard catch or a crack.', 'Land every downward flip lightly, at speaking volume.', 'About five minutes of flips at a time, then a rest.'],
                'songs' => [['Home on the Range', 'Traditional, 1870s, public domain'], ['Red River Valley', 'Traditional, 1890s, public domain'], ['The yodel refrains', 'Written for the course']],
                'shots' => ['Find the flip: your siren drawn over the ribbon, the jumps marked, and a note that your voice flipped around E4.', 'Flip or slide, a listening round: two clips to play, and the question of which one flipped.'],
                // The designed page (site/courses/yodeling): its own sections and words.
                'app' => 'yodel',
                'eyebrow' => 'A Plus course · 16 lessons',
                'hero_title' => 'Find the flip.',
                'hero_lead' => 'Herders called across valleys with a voice that flips, and the flip is already in yours. Find it, make it on purpose, keep it clean and light, then take it up the trail into Home on the Range and Red River Valley.',
                'hero_facts' => ['16 lessons, five minutes or less each', 'About 30 minutes in all', 'No reading music'],
                'see_lessons' => 'See the trail',
                'art' => ['head' => 'Head voice', 'chest' => 'Chest voice'],
                'titles' => [
                    'outcomes' => 'By the end of the trail, you can',
                    'voices' => 'Two voices, and the flip between them',
                    'light' => 'Light, never loud',
                    'units' => 'Four stages, from a first flip to a fast roll',
                    'try' => 'Find your flip',
                    'pack' => 'Packed and ready',
                    'inside' => 'What the app listens for',
                    'facts' => 'From the valleys',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'join' => 'Call across the valley',
                    'related' => 'Good with this course',
                ],
                'outcomes' => [
                    'Find where your voice flips, and flip on purpose.',
                    'Hear a clean flip from a slide.',
                    'Switch vowels at the flip, and land the way down lightly.',
                    'Yodel in rhythm, and roll several flips on one breath.',
                ],
                'voices' => [
                    'lead' => 'Your voice has two sounds. Chest voice is low and speaking-like; head voice is high, light and hooty. A yodel jumps between them, and the jump is the flip.',
                    'flip' => 'A flip jumps straight from one note to the other.',
                    'slide' => 'A slide passes through every note in between.',
                    'flip_label' => 'Flip',
                    'slide_label' => 'Slide',
                    'body' => 'The app hears the jump in pitch, not which voice made it: you learn to hear that part yourself.',
                ],
                'light' => [
                    'lead' => 'A good flip is a light flick, never a hard catch or a crack. The valley carries the sound, so you never need to push.',
                    'points' => ['Medium volume, all the way up.', 'Land every downward flip lightly, at speaking volume.', 'About five minutes of flips at a time, then a rest.', 'Voices that are still breaking should wait before yodeling.'],
                ],
                'units_say' => [
                    'Find your flip, hear your two voices, and tell a flip from a slide.',
                    'An open vowel below and a narrow one above, a light way down, and bigger leaps.',
                    'Yodels that fit the chord, a cowboy lope, and Home on the Range.',
                    'Several flips on one breath, at three speeds, then Red River Valley.',
                ],
                'try' => [
                    'lead' => 'Somewhere quiet, at a medium volume. Stop if anything tightens or tickles.',
                    'steps' => [
                        'Say "ho" in your speaking voice. That\'s your chest voice.',
                        'Now hoot like an owl, high and light: "hoo". That\'s your head voice.',
                        'On a quiet "oo", slide slowly from low to high like a siren, then back down. Do it three times.',
                        'Somewhere in the middle your voice jumps. That\'s your flip. Go only as high as feels easy, and keep the top light, never louder.',
                    ],
                    'after' => 'Lesson 1 draws your siren as you sing, marks where your voice flipped, then has you make the flip on purpose.',
                ],
                'facts' => [
                    ['Yodeling isn\'t only Alpine: the Mbuti of the Congo weave yodels into their songs, and Hawaiian falsetto singers make a yodel of the break.', 'Wikipedia, Yodeling', 'https://en.wikipedia.org/wiki/Yodeling'],
                    ['"Yodel" comes from the German jodeln, to call out the syllable "jo".', 'Wikipedia, Yodeling', 'https://en.wikipedia.org/wiki/Yodeling'],
                    ['Switzerland\'s first national yodeling festival was held in Basel in 1924.', 'Historisches Lexikon der Schweiz, Jodel', 'https://hls-dhs-dss.ch/de/articles/011891'],
                ],
                'who' => [
                    'Singers whose voice cracks on high notes and who\'d like to make that flip on purpose.',
                    'Fans of folk, country and cowboy songs who want the yodel tag.',
                    'Anyone curious, with a voice that has finished breaking.',
                ],
                'need' => [
                    'A quiet spot where you can sing at a medium volume.',
                    'A few minutes at a time: the lessons are short on purpose.',
                    'No reading music: you learn every yodel by ear, from a guide.',
                ],
                'faq' => [
                    ['How long does it take?', '16 lessons of five minutes or less, about 30 minutes in all. One or two a day is plenty: flips tire a voice.'],
                    ['Is it safe for my voice?', 'Yes, sung lightly: medium volume, a gentle landing on the way down, and a rest after five minutes. Voices still breaking should wait. It isn\'t a voice teacher.'],
                    ['Do I need to read music?', 'No. You hear each yodel from a guide and see its notes as shapes.'],
                    ['What if I can\'t find my flip?', 'It\'s already in your voice. Lesson 1 finds it with a slow, quiet siren. Pushing makes it harder to find.'],
                    ['What does it cost?', 'Lesson 1 is free, with no account needed. The rest is part of Plus; saving your progress takes a free account.'],
                ],
                'related' => ['vibrato', 'pop-and-country', 'folk-and-early-music'],
                // The trail's signs, one a section, and the close's echo.
                'kicks' => ['trailhead' => 'Trailhead', 'voices' => 'Two voices', 'rules' => 'Mountain rules', 'trail' => 'The trail', 'first' => 'First steps', 'post' => 'Postcards', 'pack' => 'Your pack', 'fine' => 'Before you set off', 'summit' => 'The summit'],
                'try_first' => 'Start here.',
                'echo' => 'valley',
                'summit' => 'Lesson 1 takes five minutes: a slow siren, your flip found and marked, then made on purpose.',
            ],
            'folk-and-early-music' => [
                'group' => 'styles',
                'name' => 'Folk and early music',
                'summary' => 'A plain, steady tone, modes and drones.',
                'title' => 'Folk and early music course',
                'description' => 'A plain, steady tone, modes and drones. A styles course on Stackapella Plus, in short lessons, with the first lesson free.',
                'headline' => 'Folk and early music',
                'lead' => 'A plain, steady tone, the old modes, and singing over a drone.',
                'learn' => ['A plain, steady tone', 'Modes: the old scales', 'Singing over a drone'],
                'measures' => 'It hears each note\'s pitch, a steady hold with the drone, and how far you drift from your key singing alone. Words and tone are yours to judge.',
                'scoring' => ['game' => 'Up to 3 stars for the lesson\'s measure: the notes on pitch, a steady hold with the drone, or your key held alone, plus XP.', 'serious' => 'The same take says it plainly, like "You held your key to the end, and 88% of your intervals were in tune", with no stars.'],
                'safety' => ['A plain tone is a relaxed sigh, never a held or squeezed throat.', 'Shanties invite shouting: sing strong, not loud, and keep the top notes light.', 'Ornaments come from the breath, light and small, with the jaw still.'],
                'songs' => [['Drunken Sailor', 'Sea shanty, traditional, public domain'], ['Barbara Allen', 'Traditional ballad, its tune from an 1891 printing, public domain'], ['Old Joe Clark', 'Appalachian, traditional, public domain (the chorus)'], ['Ut queant laxis', 'Latin hymn, 8th century, public domain (its six syllables)'], ['Wondrous Love', 'American shape-note hymn, about 1840, public domain']],
                'shots' => ['Join the crew: the shanty\'s first line at your own pace, each note waiting on the lane until you find it.', 'Ring with the drone: two clips to compare, and an answer explaining the quick wobble of a note a little high.'],
                // The designed page (site/courses/folk-and-early-music): its own sections and words.
                'app' => 'folk',
                'eyebrow' => 'A Plus course · 16 lessons',
                'hero_title' => 'Tell it plainly.',
                'hero_lead' => 'A plain, storytelling voice over a drone, the old scales that make a tune sound old, the small ornaments folk singers add, and a ballad sung on your own, with nothing underneath, without sliding off key.',
                'hero_facts' => ['16 lessons, about 3 minutes each', 'About 40 minutes in all', 'Every tune public domain'],
                'see_lessons' => 'See the lessons',
                'titles' => [
                    'outcomes' => 'By the last verse, you can',
                    'modes' => 'The old scales',
                    'ornaments' => 'Small ornaments',
                    'units' => 'Four verses, from a shanty to a ballad alone',
                    'try' => 'Sing a shanty',
                    'singer' => 'Before you sing',
                    'inside' => 'What the app listens for',
                    'facts' => 'From the old books',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'join' => 'Join the crew.',
                    'related' => 'Good with this course',
                ],
                'outcomes' => [
                    'Sing a plain, steady tone that rings with a drone.',
                    'Hear and sing the old scales: Dorian\'s bright note and Mixolydian\'s soft note.',
                    'Add a flick and a turn where they fit, light and small.',
                    'Sing a ballad on your own and keep your key with nothing underneath.',
                ],
                'modes' => [
                    'lead' => 'A tune sounds old when one note of its scale is different from the major and minor we\'re used to. You hear the difference by ear: no theory needed.',
                    'bright' => ['The bright note', 'Dorian: a minor tune with one note higher, its sixth. Drunken Sailor is in it.'],
                    'soft' => ['The soft note', 'Mixolydian: a major tune with one note lower, its seventh. Old Joe Clark is in it.'],
                ],
                'ornaments' => [
                    'lead' => 'Folk singers decorate a tune with small, quick touches, light and from the breath, with the plain voice between them.',
                    'flick' => ['A flick', 'A quick touch of the next note up before the main one. Real flicks are under a tenth of a second, so you practise them slower.'],
                    'turn' => ['A turn', 'Above, the note, below, the note: on "youth" in Barbara Allen.'],
                ],
                'units_say' => [
                    'A shanty over a drone, the ring of a true note, and Barbara Allen told plainly.',
                    'The bright note and the soft note, telling the modes apart, and Drunken Sailor end to end.',
                    'A flick from above, a turn, hearing where they go, and a decorated ballad.',
                    'Where do re mi come from, lining out, a ballad on your own, and an encore.',
                ],
                'try' => [
                    'lead' => 'A sea shanty from the 1830s. Strong, not loud.',
                    'steps' => [
                        'Sing the first line of Drunken Sailor: "What shall we do with a drunken sailor?"',
                        'Sing it plainly, like telling a story: no wobble on the long notes.',
                        'Keep the top notes light. A shanty invites shouting: sing strong, not loud.',
                        'If you can, sing it over one held low note from a tuning app, and listen for the ring.',
                    ],
                    'after' => 'Lesson 1 sings the verse over a drone, and you echo it line by line. Then your verse plays with the guide singing along over the drone: a crew of two.',
                ],
                'facts' => [
                    ['On 2 January 1666, Samuel Pepys wrote in his diary of hearing the actress Elizabeth Knipp sing Barbara Allen.', 'Pepys\'s diary, 2 January 1665/66', 'https://www.pepysdiary.com/diary/1666/01/02/'],
                    ['In Byzantine chant, some singers hold one low note under the melody, a sung drone called the ison.', 'Wikipedia, Ison (music)', 'https://en.wikipedia.org/wiki/Ison_(music)'],
                    ['A Highland bagpipe\'s chanter plays the soft seventh too: its scale is close to the Mixolydian.', 'Wikipedia, Great Highland bagpipe', 'https://en.wikipedia.org/wiki/Great_Highland_bagpipe'],
                ],
                'who' => [
                    'Singers drawn to ballads, shanties, early music and chant.',
                    'Anyone whose voice wobbles on long notes, or drifts off key when singing alone.',
                    'Choir singers curious why old tunes sound the way they do.',
                ],
                'need' => [
                    'A quiet spot, and a voice in the middle of its range.',
                    'Headphones help you hear the drone and yourself.',
                    'No reading music: every tune is taught by ear, in your own key.',
                ],
                'faq' => [
                    ['How long does it take?', '16 lessons of about 3 minutes, about 40 minutes in all.'],
                    ['What are modes?', 'The old scales that make a tune sound old. Dorian is minor with one note higher; Mixolydian is major with one note lower. You learn them by ear, over a drone.'],
                    ['Is it safe for my voice?', 'Yes. A plain tone is a relaxed sigh, never a squeezed throat: strong, not loud, in the middle of your voice. Stop if anything tightens, tickles or hurts.'],
                    ['Do I need to read music?', 'No. Every tune is taught by ear, in your own key, and you see your notes land as you sing.'],
                    ['What does it cost?', 'Lesson 1 is free, with no account needed. The rest is part of Plus; saving your progress takes a free account.'],
                ],
                'related' => ['counterpoint', 'yodeling', 'breath-and-stamina'],
                // The broadside's labels, one a section, and the close.
                'kicks' => ['ballad' => 'The ballad', 'modes' => 'The old modes', 'grace' => 'Grace notes', 'verses' => 'Four verses', 'strike' => 'Strike up', 'wall' => 'On the tavern wall', 'singer' => 'For the singer', 'questions' => 'Questions', 'close' => 'All hands'],
                'verse_n' => 'Verse :n',
                'try_first' => 'Start here.',
                'close' => 'Lesson 1 takes a few minutes: a shanty over a drone, one line at a time, then your verse with the guide singing along.',
            ],
            'musical-theatre' => [
                'group' => 'styles',
                'name' => 'Musical theatre',
                'summary' => 'Acting a song, with dynamics and legato.',
                'title' => 'Musical theatre course',
                'description' => 'Acting a song, with dynamics and legato. A styles course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Musical theatre',
                'lead' => 'Acting a song: loud and soft, smooth lines, and telling the story with your voice.',
                'learn' => ['Dynamics: loud and soft', 'Legato: smooth lines', 'Telling the story'],
                'needs' => 'hearing how loud you sing',
            ],
            'pop-and-country' => [
                'group' => 'styles',
                'name' => 'Pop and country styling',
                'summary' => 'Slides, cries and twang.',
                'title' => 'Pop and country styling course',
                'description' => 'Slides, cries and twang. A styles course on Stackapella Plus, in short lessons, with the first lesson free.',
                'headline' => 'Pop and country styling',
                'lead' => 'The touches that make a pop or country line sound like one: slides, cries and twang.',
                'learn' => ['Slides into notes', 'Cries and breaks', 'Twang'],
                'measures' => 'It hears your scoops, fall-offs, slides and a cry\'s shape, and each note\'s pitch. Tone colour and whether a move sounds good are yours to judge.',
                'scoring' => ['game' => 'Up to 3 stars, half for the moves asked for and half for the notes on pitch, plus XP; styling your own way, more moves never earn more.', 'serious' => 'The same take says it plainly, like "We heard 2 of the 3 moves asked for", with no stars or XP.'],
                'safety' => ['Keep every move small and light, at medium volume in the middle of your voice.', 'Twang is narrow and bright, never pressed: stop if your throat feels squeezed.', 'Breathy means a little air, not a whisper; keep water nearby.'],
                'songs' => [['Down in the Valley', 'Traditional American, public domain'], ['Bury Me Not on the Lone Prairie', 'Traditional cowboy song, words 1910, tune 1839, public domain'], ['Aura Lee', 'Song from 1861, its original words, public domain'], ['Beautiful Dreamer', 'Song from 1864, its first line, public domain']],
                'shots' => ['Plain to styled, the first lesson: Down in the Valley with a scoop into "val-", sung small and light.', 'Three colours: hear the breathy model, record yourself, then compare, with a note that tone isn\'t scored yet.'],
                // The designed page (site/courses/pop-and-country): its own sections and words.
                'app' => 'pop',
                'eyebrow' => 'A Plus course · 16 lessons',
                'hero_title' => 'Same notes, your sound.',
                'hero_lead' => 'A plain tune and a styled one are the same notes. The difference is a few small moves: a scoop up into a note, a fall-off at its end, a slide, a little cry, and a tone colour to suit the song. And the taste to use them sparingly.',
                'hero_facts' => ['16 lessons, 2 to 4 minutes each', 'About 40 minutes in all', 'Over real chords'],
                'see_lessons' => 'See the lessons',
                'titles' => [
                    'outcomes' => 'By the end, you can',
                    'moves' => 'Four small moves',
                    'light' => 'Small and light',
                    'units' => 'From plain to your record',
                    'try' => 'Try this now: plain to styled',
                    'inside' => 'What the app listens for',
                    'facts' => 'From the record shelf',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'join' => 'Make it yours',
                    'related' => 'Good with this course',
                ],
                'outcomes' => [
                    'Scoop up into a note, and let a note\'s end fall off, on purpose.',
                    'Slide between notes, and add a little cry, light and quick.',
                    'Try three tone colours, breathy, bright and twang, and choose one for a song.',
                    'Style a plain line your own way, using the moves sparingly.',
                ],
                'moves' => [
                    'lead' => 'The app draws your voice as a line, so you can see each move as you make it. Use them sparingly: on the long notes and the line ends.',
                    'kinds' => [
                        ['The scoop', 'Start a little below a note and slide up into it, on purpose.'],
                        ['The fall-off', 'Let a note\'s end drop away, with the breath still steady.'],
                        ['The slide', 'Glide smoothly from one note to the next, on one breath.'],
                        ['The little cry', 'A quick flick up into your lighter voice and straight back down.'],
                    ],
                ],
                'light' => [
                    'lead' => 'Every move is small and light, at a medium volume in the middle of your voice. Stop if anything tightens, tickles or hurts.',
                    'points' => [
                        'Breathy means a little air, not a whisper. Keep water nearby.',
                        'Bright comes from the vowel and a slight smile, not from more volume or higher notes. This isn\'t belting.',
                        'Twang is narrow and bright, never pressed: start from a light "nyeh", and stop if your throat feels squeezed.',
                        'The cry is a light flick, never a hard catch, and a fall-off never collapses into a creak.',
                    ],
                ],
                'units_say' => [
                    'Plain to styled, the scoop, the fall-off, and Down in the Valley styled.',
                    'The slide, the little cry, hearing which move is which, and Lone Prairie.',
                    'Breathy, bright and twang, a crooner\'s Aura Lee, a country edge, and your colour.',
                    'Where the moves go, answering back, Beautiful Dreamer your way, and your record.',
                ],
                'try' => [
                    'lead' => 'One line of Down in the Valley, sung twice. Small and slow.',
                    'steps' => [
                        'Sing "Down in the valley" plainly, every note straight.',
                        'Sing it again with a scoop into "val-": start a little below the note and slide up into it.',
                        'Keep the scoop small and slow. It should sound meant, not missed.',
                        'Record both takes on your phone and listen to them back to back: same notes, different song.',
                    ],
                    'after' => 'Lesson 1 hears your scoop into "val-" and each note\'s pitch, and plays your plain and styled takes back.',
                ],
                'facts' => [
                    ['Hank Williams was famous for a quick flick into the lighter voice: his Lovesick Blues of 1949 yodels on "cry" and on every syllable of "lonesome".', 'Wikipedia, Lovesick Blues', 'https://en.wikipedia.org/wiki/Lovesick_Blues'],
                    ['Aura Lee\'s 1861 tune came back in 1956 as the melody of Love Me Tender.', 'Wikipedia, Aura Lea', 'https://en.wikipedia.org/wiki/Aura_Lea'],
                    ['In the late 1950s Neal Matthews Jr. of the Jordanaires began writing vocal parts as numbers. Nashville\'s studios still chart songs that way.', 'Wikipedia, Nashville Number System', 'https://en.wikipedia.org/wiki/Nashville_Number_System'],
                ],
                'who' => [
                    'Singers whose lines sound correct but plain.',
                    'Anyone who loves pop, country or crooners and wants to sing in that style.',
                    'Harmony singers who want their part to match a styled lead.',
                ],
                'need' => [
                    'A quiet spot, and water nearby.',
                    'Headphones help you hear the chords and yourself.',
                    'No reading music: you see your voice drawn as a line.',
                ],
                'faq' => [
                    ['How long does it take?', '16 lessons of 2 to 4 minutes each, about 40 minutes in all.'],
                    ['Is this belting?', 'No. Bright comes from the vowel and a slight smile, not from more volume or higher notes, and every move stays small and light.'],
                    ['Will I sound like a particular singer?', 'No recording is imitated and no living singer is named as a model: styles are described, and you style a line your own way.'],
                    ['Does it score my tone?', 'Not yet. It hears your scoops, fall-offs, slides and the shape of a cry, and each note\'s pitch. Your tone colour is yours to judge, by comparing your takes.'],
                    ['What does it cost?', 'The first lesson is free, with no account needed. The rest of the course is part of Plus. Saving your progress takes a free account.'],
                ],
                'related' => ['vibrato', 'yodeling', 'folk-and-early-music'],
            ],
            'a-cappella-bass' => [
                'group' => 'harmony',
                'name' => 'A cappella bass',
                'summary' => 'Low lines and holding a groove.',
                'title' => 'A cappella bass course',
                'description' => 'Low lines and holding a groove. A harmony course coming to Stackapella Plus, in short lessons.',
                'headline' => 'A cappella bass',
                'lead' => 'Low lines that hold the whole group up, with "dm dm" syllables and a steady groove.',
                'learn' => ['Low lines, in tune', 'Bass syllables', 'Holding the groove'],
            ],
            'other-languages' => [
                'group' => 'skills',
                'name' => 'Singing in another language',
                'summary' => 'Songs in Hebrew, Spanish, French and Latin, sounds first.',
                'title' => 'Singing in another language course',
                'description' => 'Songs in Hebrew, Spanish, French and Latin, sounds first. A singing skills course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Sing in another language',
                'lead' => 'Songs in Hebrew, Spanish, French and Latin, learning the sounds first, then the words on the tune.',
                'learn' => ['The sounds of each language', 'Words on the tune', 'A song in each language'],
                'needs' => 'hearing how you say the words',
            ],
            'whistling' => [
                'group' => 'styles',
                'name' => 'Whistling',
                'summary' => 'In tune, long notes and melodies.',
                'title' => 'Whistling course',
                'description' => 'In tune, long notes and melodies. A styles course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Whistle in tune',
                'lead' => 'A whistle is one clear note: in tune, long notes, then whole melodies.',
                'learn' => ['Whistling in tune', 'Long, steady notes', 'Melodies'],
            ],
            'beatboxing' => [
                'group' => 'harmony',
                'name' => 'Beatboxing',
                'summary' => 'Kick, snare and hi-hat, then simple grooves.',
                'title' => 'Beatboxing course',
                'description' => 'Kick, snare and hi-hat, then simple grooves. A harmony course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Beatboxing',
                'lead' => 'The kick, the snare and the hi-hat, then simple grooves, then over a song.',
                'learn' => ['Kick, snare and hi-hat', 'Simple grooves', 'Beatboxing under a song'],
                'needs' => 'recognising percussive sounds',
            ],
            'vocal-percussion' => [
                'group' => 'harmony',
                'name' => 'Vocal percussion for a cappella',
                'summary' => 'The drum part under a group.',
                'title' => 'Vocal percussion for a cappella course',
                'description' => 'The drum part under a group. A harmony course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Vocal percussion for a cappella',
                'lead' => 'The drum part under an a cappella group: steady, simple, and in time with everyone.',
                'learn' => ['The basic sounds', 'Keeping time for a group', 'Fitting under a song'],
                'needs' => 'recognising percussive sounds',
            ],
            'humming-and-lip-trills' => [
                'group' => 'skills',
                'name' => 'Humming and lip trills',
                'summary' => 'Warm-ups that build your tone.',
                'title' => 'Humming and lip trills course',
                'description' => 'Warm-ups that build your tone. A singing skills course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Humming and lip trills',
                'lead' => 'Warm-ups that build an easy, ringing tone: humming, and lip trills up and down your range.',
                'learn' => ['Humming warm-ups', 'Lip trills', 'Slides up and down'],
            ],
            'overtone-singing' => [
                'group' => 'styles',
                'name' => 'Overtone singing',
                'summary' => 'Two notes at once.',
                'title' => 'Overtone singing course',
                'description' => 'Two notes at once. A styles course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Overtone singing',
                'lead' => 'One voice, two notes at once: a low drone with a whistle-like tune above it, as in Tuvan throat singing.',
                'learn' => ['The drone', 'Finding the overtones', 'A tune in overtones'],
                'needs' => 'tracking overtones',
            ],
            'live-looping' => [
                'group' => 'styles',
                'name' => 'Live looping',
                'summary' => 'Build a song by stacking parts as you go.',
                'title' => 'Live looping course',
                'description' => 'Build a song by stacking parts as you go. A styles course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Live looping',
                'lead' => 'Build a song as you go: sing a part, loop it, and stack the next one on top.',
                'learn' => ['A loop that lines up', 'Stacking parts as you go', 'A whole song from loops'],
            ],
            'improvising-harmony' => [
                'group' => 'harmony',
                'name' => 'Improvising a harmony',
                'summary' => 'Hear a tune and invent a part on the spot.',
                'title' => 'Improvising a harmony course',
                'description' => 'Hear a tune and invent a part on the spot. A harmony course on Stackapella Plus, in short lessons, with the first lesson free.',
                'headline' => 'Improvise a harmony',
                'lead' => 'Hear a tune, then invent a part above or below it on the spot.',
                'learn' => ['Chord tones', 'A part above, a part below', 'Making it up as the tune goes'],
                'measures' => 'It checks each note of your harmony against the notes that fit, with the lights on, only at the chord changes, or off, and says when the tune pulled you onto its note. Whether the chord rings is for your ears.',
                'scoring' => ['game' => 'Up to three stars and XP for your harmony\'s notes, and some lessons also ask that you never copied the tune.', 'serious' => 'The same take shows its measure plainly, like "76% on pitch", with no stars or XP.'],
                'safety' => ['Stop if anything tightens or hurts, and don\'t sing when you\'re hoarse.', 'Above a loud tune, go lighter rather than strain to match it.', 'The tune plays under your voice; wired or one-ear headphones help you hear yourself.'],
                'songs' => [['Michael, Row the Boat Ashore', 'Traditional, public domain'], ['Home on the Range', 'Traditional, 1870s, public domain (the chorus)'], ['Swing Low, Sweet Chariot', 'Spiritual, published 1872, public domain (the chorus)'], ['Red River Valley', 'Traditional, 1870s, public domain'], ['The harmony lines', 'Written for the course']],
                'shots' => ['The first lesson\'s card: sing the tune\'s next chord tone up, mostly a third and a fourth over so, best with headphones.', 'The part above Michael, Row the Boat Ashore on the note lane, every harmony note lit with its word.'],
                // The designed page (site/courses/improvising-harmony): its own sections and words.
                'app' => 'improvising-harmony',
                'eyebrow' => 'A Plus course · 16 lessons',
                'hero_title' => 'Make up a part.',
                'hero_lead' => 'Hear a tune and sing a second part to it on the spot, above or below, without copying it. Every harmony note starts lit, then the lights fade, until you\'re making it up by ear.',
                'hero_facts' => ['16 lessons, 2 to 4 minutes each', 'About 55 minutes in all', 'Best with headphones'],
                'see_lessons' => 'See the lessons',
                'titles' => [
                    'outcomes' => 'By the end, you can',
                    'rule' => 'The next chord tone up',
                    'lights' => 'From lights to no lights',
                    'units' => 'From a part above to a trio of you',
                    'try' => 'Try this now: a part above',
                    'inside' => 'What the app listens for',
                    'facts' => 'About harmony singing',
                    'who' => 'Who it\'s for',
                    'need' => 'What you need',
                    'safety' => 'Singing it safely',
                    'faq' => 'Questions',
                    'join' => 'Find your part',
                    'related' => 'Good with this course',
                ],
                'outcomes' => [
                    'Sing a part above a tune: the next chord tone up.',
                    'Sing a part below, and rescue yourself when it rubs: hold, then step.',
                    'Choose any note of the chord, and move against the tune.',
                    'Harmonise with no lights at all, even a high tenor above the lead.',
                ],
                'rule' => [
                    'lead' => 'The simplest harmony is the tune\'s next chord tone up. Over most notes that\'s a third. Over so it\'s a fourth, because the third above so, ti, rubs against the chord.',
                    'third' => ['Over most notes', 'A third up: over mi, sing so.'],
                    'fourth' => ['Over so', 'A fourth up, to do: ti would rub.'],
                    'body' => 'It\'s the real folk and bluegrass tenor rule, and it leads straight to any chord tone. Lost? Hold your note until it rubs, then step.',
                ],
                'lights' => [
                    ['Every note lit', 'Each harmony note lights up on the lane, so you learn the shape.'],
                    ['Lights fading', 'The lights fade over a line, and you carry on by ear.'],
                    ['You choose', 'Every note of the chord lights up, and you pick one.'],
                    ['Lights off', 'Only the chords are left. The part is yours.'],
                ],
                'units_say' => [
                    'A third above, why so takes a fourth, harmony or the tune, and Home on the Range.',
                    'A part below, hold then step off, answering in harmony, and Steal Away.',
                    'The chord\'s three notes, close then against, and your own harmony to Swing Low.',
                    'Lights at the changes, lights off, a tenor above in Simple Gifts, and a trio of you.',
                ],
                'try' => [
                    'lead' => 'You need the tune playing while you sing: a recording of yourself works.',
                    'steps' => [
                        'Record yourself singing the first line of Michael, Row the Boat Ashore.',
                        'Play it back, and on the long notes, "row" and "shore", hum a little higher than the tune until it sounds sweet with it: about a third up.',
                        'If you find yourself on the tune\'s own note, hold yours and listen: harmony sounds like two notes at once.',
                        'Lost? Hold your note until it rubs, then step.',
                    ],
                    'after' => 'Lesson 1 lights every harmony note on the lane above the tune, then plays your part with the tune: Hear it together.',
                ],
                'facts' => [
                    ['Medieval English singers called two voices moving mostly in thirds "gymel", from the Latin for twin.', 'Britannica, Gymel', 'https://www.britannica.com/art/gymel'],
                    ['In shape-note singing from The Sacred Harp (1844), the tune is in the tenor, with parts sung above and below it.', 'New Georgia Encyclopedia, Sacred Harp', 'https://www.georgiaencyclopedia.org/articles/arts-culture/sacred-harp/'],
                    ['Bluegrass is named after Bill Monroe\'s Blue Grass Boys, and Monroe often sang the high tenor above the lead.', 'Wikipedia, Bill Monroe', 'https://en.wikipedia.org/wiki/Bill_Monroe'],
                ],
                'who' => [
                    'Singers who can learn a written part but freeze when asked to make one up.',
                    'Anyone who sings along with friends, at church or round a campfire, and wants to add a harmony.',
                    'Choir singers who want to hear where their part fits in the chord.',
                ],
                'need' => [
                    'Headphones, wired or one ear off: on a speaker, the tune rests while you sing.',
                    'A quiet spot and a few minutes a day.',
                    'No reading music: the notes that fit light up on the lane.',
                ],
                'faq' => [
                    ['How long does it take?', '16 lessons of 2 to 4 minutes each, about 55 minutes in all.'],
                    ['What if I keep singing the tune?', 'That\'s the most common pull, and the app says when the tune pulled you onto its note. Headphones help. Lost? Hold your note until it rubs, then step.'],
                    ['Do I need to know chords?', 'No. The notes that fit light up, and the course teaches the three notes of a chord as you go.'],
                    ['Is it safe for my voice?', 'Yes. Above a loud tune, go lighter rather than strain to match it. Stop if anything tightens or hurts, and don\'t sing when you\'re hoarse. It isn\'t a substitute for a teacher.'],
                    ['What does it cost?', 'The first lesson is free, with no account needed. The rest of the course is part of Plus. Saving your progress takes a free account.'],
                ],
                'related' => ['ear-training', 'counterpoint', 'barbershop'],
            ],
            'character-voices' => [
                'group' => 'styles',
                'name' => 'Character voices',
                'summary' => 'Voices for stories and games.',
                'title' => 'Character voices course',
                'description' => 'Voices for stories and games. A styles course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Character voices',
                'lead' => 'Voices for stories and games: a pirate, a robot, a giant, a cartoon squeak.',
                'learn' => ['Changing your voice\'s colour', 'High and low characters', 'Keeping it safe for your voice'],
                'needs' => 'hearing the colour of your tone',
            ],
            'accents' => [
                'group' => 'styles',
                'name' => 'Accents for performance',
                'summary' => 'The sounds of an accent, for a role or a song.',
                'title' => 'Accents for performance course',
                'description' => 'The sounds of an accent, for a role or a song. A styles course coming to Stackapella Plus, in short lessons.',
                'headline' => 'Accents for performance',
                'lead' => 'The sounds of an accent, for a role or a song, one vowel at a time.',
                'learn' => ['Listening for an accent\'s sounds', 'Vowels first', 'An accent on a song'],
                'needs' => 'recognising vowels',
            ],
        ],
    ],
];
