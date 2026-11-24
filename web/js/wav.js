// WAV files: 16-bit PCM for export, any number of channels.

/**
 * Interleaves `channels` (Float32Arrays of equal length) into a 16-bit PCM WAV Blob. A `comment`
 * (the real voices' credits) goes in a LIST/INFO chunk as ICMT, which players show as the comment.
 * `aiMark` ({ text, generator, digitalSourceType }, auth.config's, EU AI Act Article 50) marks a file holding an AI voice,
 * machine-readably, as the server and the voices worker do (ai-modal's table, 2026-11-24): ICMT starts with its text,
 * ISFT is "Stackapella", an iXML chunk holds NOTE (the text) and USER ("ai_generated=true;digital_source_type=…;
 * generator=…"), and a bext chunk carries the text as its description.
 */
export function encodeWav(channels, sampleRate, { comment = null, aiMark = null } = {}) {
    const frames = channels[0].length;
    const count = channels.length;
    const dataBytes = frames * count * 2;
    const icmt = [aiMark?.text, comment].filter(Boolean).join('\n');
    const info = concat(icmt ? infoChunk(icmt, aiMark?.text ? 'Stackapella' : null) : new Uint8Array(0), aiMark?.text ? concat(ixmlChunk(aiMark), bextChunk(aiMark.text)) : new Uint8Array(0));
    const view = new DataView(new ArrayBuffer(44 + dataBytes + info.length));
    const text = (at, value) => {
        for (let i = 0; i < value.length; i++) {
            view.setUint8(at + i, value.charCodeAt(i));
        }
    };
    text(0, 'RIFF');
    view.setUint32(4, 36 + dataBytes + info.length, true);
    text(8, 'WAVE');
    text(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true); // PCM
    view.setUint16(22, count, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * count * 2, true);
    view.setUint16(32, count * 2, true);
    view.setUint16(34, 16, true);
    text(36, 'data');
    view.setUint32(40, dataBytes, true);
    let at = 44;
    for (let i = 0; i < frames; i++) {
        for (let c = 0; c < count; c++) {
            const v = Math.max(-1, Math.min(1, channels[c][i]));
            view.setInt16(at, v < 0 ? v * 0x8000 : v * 0x7fff, true);
            at += 2;
        }
    }
    new Uint8Array(view.buffer, 44 + dataBytes).set(info);
    return new Blob([view.buffer], { type: 'audio/wav' });
}

const concat = (...parts) => {
    const out = new Uint8Array(parts.reduce((n, p) => n + p.length, 0));
    let at = 0;
    for (const p of parts) {
        out.set(p, at);
        at += p.length;
    }
    return out;
};

/** One RIFF sub-chunk: its id, its size and its bytes, padded to an even length as RIFF requires. */
function subChunk(id, bytes) {
    const out = new Uint8Array(8 + bytes.length + (bytes.length % 2));
    const view = new DataView(out.buffer);
    [...id].forEach((c, i) => view.setUint8(i, c.charCodeAt(0)));
    view.setUint32(4, bytes.length, true);
    out.set(bytes, 8);
    return out;
}

/** LIST/INFO with ICMT (comment) and, for a marked file, ISFT (the software), UTF-8, each zero-ended. */
function infoChunk(comment, software = null) {
    const z = (value) => new TextEncoder().encode(`${value}\0`);
    const fields = concat(subChunk('ICMT', z(comment)), software ? subChunk('ISFT', z(software)) : new Uint8Array(0));
    return subChunk('LIST', concat(new TextEncoder().encode('INFO'), fields));
}

const xml = (value) => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/** An iXML chunk with NOTE (the mark's text) and USER (its machine fields), UTF-8. */
function ixmlChunk(mark) {
    const user = `ai_generated=true;digital_source_type=${mark.digitalSourceType ?? ''};generator=${mark.generator ?? 'Stackapella'}`;
    const body = `<?xml version="1.0" encoding="UTF-8"?>\n<BWFXML><IXML_VERSION>1.61</IXML_VERSION><NOTE>${xml(mark.text)}</NOTE><USER>${xml(user)}</USER></BWFXML>`;
    return subChunk('iXML', new TextEncoder().encode(body));
}

/**
 * A Broadcast Wave bext chunk (EBU Tech 3285, version 1) whose Description is `text` (ASCII, at most 256 bytes; the rest
 * of the fixed fields empty), so broadcast tools read the AI marking too.
 */
function bextChunk(text) {
    const body = new Uint8Array(602);
    const ascii = [...String(text)].map((c) => (c.charCodeAt(0) < 128 ? c.charCodeAt(0) : 63)).slice(0, 256);
    body.set(ascii, 0);
    new DataView(body.buffer).setUint16(346, 1, true); // Version 1
    return subChunk('bext', body);
}
