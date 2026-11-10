// /tools/embed (server/resources/views/site/tools-embed.blade.php): pick a tool and its look, see it live, copy one
// line. The page's data (data-embed-maker-data) gives the embed's base address, the tools that may be embedded (and
// which listen), the themes and the words; this fills the preview and the code from the picks. The code is plain HTML:
// an iframe with the tool's own address, the look in its link (?theme=, ?skin=game), allow="microphone" only for a tool
// that listens, and lazy loading.

const root = document.querySelector('[data-embed-maker]');
const data = JSON.parse(root?.querySelector('[data-embed-maker-data]')?.textContent ?? 'null');

const HEIGHT = 620; // the whole tool at about 320 px wide (its compact layout), with the credit under it

if (root && data) {
    const $ = (sel) => root.querySelector(sel);
    const pickTool = $('[data-embed-tool]');
    const pickTheme = $('[data-embed-theme]');
    const pickGame = $('[data-embed-game]');
    const preview = $('[data-embed-preview]');
    const code = $('[data-embed-code]');
    const copy = $('[data-embed-copy]');
    const copyWords = copy.textContent;
    const tools = Object.fromEntries(data.tools.map((t) => [t.slug, t]));

    const escape = (text) => text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

    function update() {
        const tool = tools[pickTool.value] ?? data.tools[0];
        const params = new URLSearchParams();
        if (pickTheme.value) params.set('theme', pickTheme.value);
        if (pickGame.checked) params.set('skin', 'game');
        const query = params.toString();
        const path = `${tool.slug}${query ? `?${query}` : ''}`;
        const src = `${data.base}${path}`;
        const here = `${data.preview ?? data.base}${path}`;
        const title = data.title.replace(':tool', tool.name);
        if (preview.getAttribute('src') !== here) preview.setAttribute('src', here);
        preview.title = title;
        const allow = tool.listens ? ' allow="microphone"' : '';
        code.value = `<iframe src="${escape(src)}" title="${escape(title)}" width="100%" height="${HEIGHT}" style="border:0;max-width:480px"${allow} loading="lazy"></iframe>`;
        $('[data-embed-mic]').hidden = !tool.listens;
    }

    for (const input of [pickTool, pickTheme, pickGame]) input.addEventListener('change', update);
    copy.addEventListener('click', async () => {
        code.select();
        try {
            await navigator.clipboard.writeText(code.value);
        } catch {
            document.execCommand?.('copy'); // an older browser: the selected text
        }
        copy.textContent = data.copied;
        setTimeout(() => { copy.textContent = copyWords; }, 2000); // the button's words, not audio
    });
    update();
}
