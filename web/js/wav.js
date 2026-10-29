// WAV files: 16-bit PCM for export, any number of channels.

/**
 * Interleaves `channels` (Float32Arrays of equal length) into a 16-bit PCM WAV Blob. A `comment`
 * (the real voices' credits) goes in a LIST/INFO chunk as ICMT, which players show as the comment.
 */
export function encodeWav(channels, sampleRate, { comment = null } = {}) {
    const frames = channels[0].length;
    const count = channels.length;
    const dataBytes = frames * count * 2;
    const info = comment ? infoChunk(comment) : new Uint8Array(0);
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

/** LIST/INFO with one ICMT (comment), UTF-8, padded to an even length as RIFF requires. */
function infoChunk(comment) {
    const text = new TextEncoder().encode(`${comment}\0`);
    const padded = text.length + (text.length % 2);
    const out = new Uint8Array(12 + 8 + padded);
    const view = new DataView(out.buffer);
    const ascii = (at, value) => [...value].forEach((c, i) => view.setUint8(at + i, c.charCodeAt(0)));
    ascii(0, 'LIST');
    view.setUint32(4, out.length - 8, true);
    ascii(8, 'INFO');
    ascii(12, 'ICMT');
    view.setUint32(16, text.length, true);
    out.set(text, 20);
    return out;
}
