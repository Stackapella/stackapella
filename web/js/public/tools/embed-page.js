// The embed view's one script (server/resources/views/embed/tool.blade.php): it reads the tool's data from the page
// (the module, its mount, its words from lang, the credit's words and the app's address) and hands it to the frontend's
// embedFrame, which mounts the tool compact, takes the look from ?theme= and ?skin=, and adds the credit. No inline
// script, so the page keeps the site's script policy.
import { embedFrame } from './embed.js';

const stage = document.querySelector('[data-embed-stage]');
const data = document.querySelector('[data-embed-data]');

try {
    const boot = JSON.parse(data?.textContent ?? 'null');
    if (stage && boot) await embedFrame(stage, boot);
} catch (error) {
    console.error('The embedded tool could not start', error);
}
