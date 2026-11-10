// The frame a /tools page puts around its tool (marketing's pages; the design expert's call, 2026-11-03):
// a 48 px brand row on top, the mark and name home, the tool's key control ([data-tool-key]) and the Theme button
// at the right, so the demo pages
// shoot the tools as they'll be seen. The Theme button is the site's own (js/public/site.js).
const row = document.createElement('header');
row.className = 'mx-auto flex h-12 max-w-xl items-center justify-between gap-2 px-4';
row.innerHTML = `<a href="/" class="flex min-h-11 items-center gap-2 text-lg font-extrabold text-cream"><img src="/icons/icon.svg" width="28" height="28" alt="" class="rounded-[17%]"><span data-frame-name>Stackapella</span></a>
    <span class="flex items-center gap-2"><span class="text-[0.8125rem] font-medium text-muted empty:hidden" data-frame-tool></span><span class="empty:hidden" data-tool-key></span><button type="button" class="s-iconbtn" data-theme-toggle aria-label="Light or dark"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M21 14.5A8.5 8.5 0 0 1 9.5 3a8.5 8.5 0 1 0 11.5 11.5z"/></svg></button></span>`;
// Below 360 px the row shows just the mark, so the key control has room (the real row does the same).
const narrow = document.createElement('style');
// The tool's name never wraps, and gives way to a key control when the tool has one (the quieter of the two: the
// page's H1 names the tool just below).
narrow.textContent = '@media (max-width: 359px) { [data-frame-name] { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); } }'
    + ' [data-frame-tool] { white-space: nowrap; } header:has([data-tool-key]:not(:empty)) [data-frame-tool] { display: none; }';
document.head.append(narrow);
row.querySelector('[data-frame-tool]').textContent = document.body.dataset.toolName ?? '';
document.body.prepend(row);
await import('/js/public/site.js');
