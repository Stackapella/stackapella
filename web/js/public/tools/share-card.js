// A free tool's result, shared (the range test's Share my range, pitch matching's round, an ear trainer's ten): a card
// made on the device (js/result-card.js), handed to the device's share sheet with a line and the tool's link
// (?ref=share-<tool>, so a friend lands on the tool and tries it). Where files can't be shared (most desktops), the card
// downloads and the line and link are copied. Nothing is uploaded. The page counts a share (page.js onShared).

/** The tool's own address with its share tag, never the embed's or a variant's: the page a friend should land on. */
export const shareUrl = (slug, tag) => `${location.origin}/tools/${slug}?ref=${tag}`;

/**
 * Shares `draw`'s card (a function given result-card.js, returning the PNG blob). Returns 'shared' (the sheet took it),
 * 'saved' (downloaded and the line copied: say so with the page's words), or null (cancelled, or it failed).
 */
export async function shareCard({ draw, fileName, text, url }) {
    try {
        const blob = await draw(await import('../../result-card.js'));
        const file = new File([blob], fileName, { type: 'image/png' });
        if (navigator.canShare?.({ files: [file] })) {
            await navigator.share({ files: [file], text, url });
            return 'shared';
        }
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = file.name;
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000); // the download's address, freed after it starts (not audio)
        await navigator.clipboard?.writeText(`${text} ${url}`).catch((error) => console.info('The line was not copied', error?.name ?? error));
        return 'saved';
    } catch (error) {
        if (error?.name !== 'AbortError') console.warn('Sharing failed', error);
        return null;
    }
}

/** The files a tool that shares keeps offline (its WARM), so the card draws with no connection. */
export const SHARE_FILES = ['/js/public/tools/share-card.js', '/js/result-card.js', '/js/glass.js'];

/** A part's ink (1 to 4) as the page's theme has it, for a card's colour: readable on the theme's ground. */
export const partInk = (part) => getComputedStyle(document.documentElement).getPropertyValue(`--color-part-ink-${part}`).trim() || ['#ff9139', '#ffea6b', '#45eca4', '#968dfd'][part - 1];

/** The card's look: the arcade's in game mode, as the theme menu reads the skin. */
export const arcade = () => document.documentElement.dataset.skin !== 'serious';
