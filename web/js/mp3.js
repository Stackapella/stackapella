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
export async function encodeMp3(channels, sampleRate, { comment = null, aiMark = null } = {}) {
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
    // The real voices' credits, as an ID3 comment players show (as the WAV's ICMT); a file holding an AI voice is marked
    // (EU AI Act Article 50): its text first in the comment, and TXXX AI_GENERATED / AI_GENERATOR / DIGITAL_SOURCE_TYPE.
    const text = [aiMark?.text, comment].filter(Boolean).join('\n');
    const machine = aiMark?.text ? { AI_GENERATED: 'true', AI_GENERATOR: aiMark.generator ?? 'Stackapella', ...(aiMark.digitalSourceType ? { DIGITAL_SOURCE_TYPE: aiMark.digitalSourceType } : {}) } : {};
    if (text) parts.unshift(id3Tag(text, machine));
    return new Blob(parts, { type: 'audio/mpeg' });
}

const utf16 = (text) => {
    const out = [0xff, 0xfe]; // UTF-16LE, with its BOM
    for (const unit of String(text)) for (let i = 0; i < unit.length; i++) out.push(unit.charCodeAt(i) & 0xff, unit.charCodeAt(i) >> 8);
    return out;
};
const frame = (id, body) => [...[...id].map((c) => c.charCodeAt(0)), (body.length >>> 24) & 0xff, (body.length >>> 16) & 0xff, (body.length >>> 8) & 0xff, body.length & 0xff, 0, 0, ...body];

/**
 * An ID3v2.3 tag: a COMM frame (`text`, UTF-16 with a BOM, language "eng") and a TXXX frame for each of `extra`
 * ({ description: value }, both UTF-16). Pure.
 */
export function id3Tag(text, extra = {}) {
    // COMM: encoding 1 (UTF-16), "eng", an empty description (BOM + 00 00), then the text.
    const frames = [...frame('COMM', [1, 0x65, 0x6e, 0x67, 0xff, 0xfe, 0, 0, ...utf16(text)])];
    // TXXX: encoding 1, the description (zero-ended, two bytes), then the value.
    for (const [description, value] of Object.entries(extra)) frames.push(...frame('TXXX', [1, ...utf16(description), 0, 0, ...utf16(value)]));
    // The tag's size is "synchsafe": 7 bits a byte.
    const size = frames.length;
    const header = [0x49, 0x44, 0x33, 3, 0, 0, (size >>> 21) & 0x7f, (size >>> 14) & 0x7f, (size >>> 7) & 0x7f, size & 0x7f];
    return new Uint8Array([...header, ...frames]);
}

/** An ID3v2.3 tag with one COMM frame (the credits alone). */
export const id3Comment = (text) => id3Tag(text);
