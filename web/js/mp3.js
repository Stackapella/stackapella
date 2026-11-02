// MP3 export (Plus; docs/pricing-plan.md, "MP3 export is checked in the browser only"): the mix
// encoded on the device by lamejs, loaded from the CDN only when an MP3 is made, pinned to a version
// with its SRI hash (index.html names it, so the CSP allows it).

const LAME = 'https://cdn.jsdelivr.net/npm/lamejs@1.2.1/lame.min.js';
const LAME_SRI = 'sha384-xuasJXVcyv3hZq0eYpelEkBC8l4yufatZXDsKuyCU2rqfhDCb+ftuE/mSfZAteiK';
const BLOCK = 1152; // an MP3 frame's samples
const KBPS = 192;

let loading = null;

/** lamejs, once (a classic script that defines `lamejs` globally). */
function loadLame() {
    loading ??= new Promise((resolve, reject) => {
        if (window.lamejs) {
            resolve(window.lamejs);
            return;
        }
        const script = Object.assign(document.createElement('script'), { src: LAME, integrity: LAME_SRI, crossOrigin: 'anonymous', async: true });
        script.onload = () => (window.lamejs ? resolve(window.lamejs) : reject(new Error('lamejs did not load')));
        script.onerror = () => {
            loading = null; // offline, say: tried again next time
            reject(Object.assign(new Error('lamejs did not load'), { code: 'offline' }));
        };
        document.head.append(script);
    });
    return loading;
}

const toInt16 = (samples) => {
    const out = new Int16Array(samples.length);
    for (let i = 0; i < samples.length; i++) {
        const v = Math.max(-1, Math.min(1, samples[i]));
        out[i] = v < 0 ? v * 0x8000 : v * 0x7fff;
    }
    return out;
};

/** The mix (`channels`: one or two Float32Arrays) as an MP3 Blob, at 192 kbps. */
export async function encodeMp3(channels, sampleRate, { comment = null } = {}) {
    const lame = await loadLame();
    const encoder = new lame.Mp3Encoder(channels.length, sampleRate, KBPS);
    const [left, right] = channels.map(toInt16);
    const parts = [];
    for (let i = 0; i < left.length; i += BLOCK) {
        const chunk = right ? encoder.encodeBuffer(left.subarray(i, i + BLOCK), right.subarray(i, i + BLOCK)) : encoder.encodeBuffer(left.subarray(i, i + BLOCK));
        if (chunk.length) parts.push(new Uint8Array(chunk));
    }
    const end = encoder.flush();
    if (end.length) parts.push(new Uint8Array(end));
    // The real voices' credits, as an ID3 comment players show (as the WAV's ICMT).
    if (comment) parts.unshift(id3Comment(comment));
    return new Blob(parts, { type: 'audio/mpeg' });
}

/** An ID3v2.3 tag with one COMM frame (`text`, UTF-16 with a BOM, language "eng"). Pure. */
export function id3Comment(text) {
    const utf16 = [0xff, 0xfe]; // UTF-16LE, with its BOM
    for (const unit of String(text)) for (let i = 0; i < unit.length; i++) utf16.push(unit.charCodeAt(i) & 0xff, unit.charCodeAt(i) >> 8);
    // Encoding 1 (UTF-16), "eng", an empty description (BOM + 00 00), then the text.
    const body = [1, 0x65, 0x6e, 0x67, 0xff, 0xfe, 0, 0, ...utf16];
    const frame = [0x43, 0x4f, 0x4d, 0x4d, (body.length >>> 24) & 0xff, (body.length >>> 16) & 0xff, (body.length >>> 8) & 0xff, body.length & 0xff, 0, 0, ...body];
    // The tag's size is "synchsafe": 7 bits a byte.
    const size = frame.length;
    const header = [0x49, 0x44, 0x33, 3, 0, 0, (size >>> 21) & 0x7f, (size >>> 14) & 0x7f, (size >>> 7) & 0x7f, size & 0x7f];
    return new Uint8Array([...header, ...frame]);
}
