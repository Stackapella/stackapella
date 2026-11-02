// A QR code for a link (the warm-up builder's Share: Hillel, 2026-11-01, "yes" to a small QR library
// from a CDN). qrcode-generator, loaded only when Share opens, pinned to a version with its SRI hash
// (index.html names it, so the CSP allows it). It only works out which squares are dark; the app draws
// them as one SVG path, so nothing the library makes is put into the page as markup.

const QR = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js';
const QR_SRI = 'sha384-8FWZA6BGMXhsfO+BLtrJK0We6gg5o1JyO8xQm6peWDEUs17ACA5ziE/NIAkl9z2k';

let loading = null;

/** qrcode-generator, once (a classic script that defines `qrcode` globally). */
function loadQr() {
    loading ??= new Promise((resolve, reject) => {
        if (typeof window.qrcode === 'function') {
            resolve(window.qrcode);
            return;
        }
        const script = Object.assign(document.createElement('script'), { src: QR, integrity: QR_SRI, crossOrigin: 'anonymous', async: true });
        script.onload = () => (typeof window.qrcode === 'function' ? resolve(window.qrcode) : reject(new Error('The QR library did not load')));
        script.onerror = () => {
            loading = null; // offline, say: tried again next time
            reject(Object.assign(new Error('The QR library did not load'), { code: 'offline' }));
        };
        document.head.append(script);
    });
    return loading;
}

/** One SVG path for the dark squares of a `size` by `size` grid; `dark(row, col)` says which. Pure. */
export function qrPath(size, dark) {
    let d = '';
    for (let row = 0; row < size; row++) {
        for (let col = 0; col < size; col++) if (dark(row, col)) d += `M${col} ${row}h1v1h-1z`;
    }
    return d;
}

/** `text` as a QR code: { size, path } for an SVG with viewBox 0 0 size size (medium error correction). */
export async function qrFor(text) {
    const qrcode = await loadQr();
    const code = qrcode(0, 'M');
    code.addData(text);
    code.make();
    const size = code.getModuleCount();
    return { size, path: qrPath(size, (row, col) => code.isDark(row, col)) };
}
