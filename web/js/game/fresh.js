// Game mode before a first song (the first-session review, 2026-11-13, proposal 5; Hillel: yes): until the singer has
// sung (first-song.js's sungYet, the one test for both skins), a score of 000000 and STAGE 01 are words for a game
// they haven't played. This mirrors it onto <html data-game-fresh>, and the game sheet hides Home's HI and score while
// it's set. Loaded with game mode's other pieces (modules.js), so first-song.js's stores never load the game.
import { watch } from 'vue';
import { sungYet } from '../first-song.js';
import { learning } from '../stores/learning.js';
import { loadProjects, projects } from '../stores/projects.js';

// Fresh only once both are read, so a singer with a score never sees it go and come back as the page opens.
if (!projects.loaded) loadProjects().catch((error) => console.error('Songs not read for game mode', error));
watch(() => learning.loaded && projects.loaded && !sungYet(), (fresh) => document.documentElement.toggleAttribute('data-game-fresh', fresh), { immediate: true });
