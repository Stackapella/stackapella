// The AI marking inside exported files (EU AI Act Article 50; auth.config's `aiMark`): a WAV holding an AI voice carries
// it as LIST/INFO ICMT and ISFT and as a bext Description; an MP3 as an ID3 COMM and TXXX AI_GENERATED / AI_GENERATOR;
// a file without one is never marked.
import { encodeWav } from '/js/wav.js';
import { id3Tag } from '/js/mp3.js';
import { assert, test } from './harness.js';

const MARK = { text: 'AI-generated voice (Stackapella, SoulX-Singer)', generator: 'Stackapella voices (SoulX-Singer)', digitalSourceType: 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia' };

/** A WAV's chunks after "WAVE": [{ id, bytes }], and the INFO fields inside a LIST. */
function chunks(buffer) {
    const view = new DataView(buffer);
    const out = [];
    for (let at = 12; at + 8 <= buffer.byteLength; ) {
        const id = String.fromCharCode(...new Uint8Array(buffer, at, 4));
        const size = view.getUint32(at + 4, true);
        out.push({ id, bytes: new Uint8Array(buffer, at + 8, size) });
        at += 8 + size + (size % 2);
    }
    return out;
}
function info(list) {
    const fields = {};
    const view = new DataView(list.buffer, list.byteOffset, list.byteLength);
    for (let at = 4; at + 8 <= list.length; ) {
        const id = String.fromCharCode(...list.slice(at, at + 4));
        const size = view.getUint32(at + 4, true);
        fields[id] = new TextDecoder().decode(list.slice(at + 8, at + 8 + size)).replace(/\0+$/, '');
        at += 8 + size + (size % 2);
    }
    return fields;
}

test('ai mark: a WAV holding an AI voice carries the marking in INFO (ICMT, ISFT) and bext', async () => {
    const wav = await encodeWav([new Float32Array(480)], 48000, { comment: 'Real voices: Sarah K.', aiMark: MARK }).arrayBuffer();
    const all = chunks(wav);
    assert(all.some((c) => c.id === 'data') && all.some((c) => c.id === 'fmt '), 'still a playable WAV');
    const fields = info(all.find((c) => c.id === 'LIST').bytes);
    assert(fields.ICMT === `${MARK.text}\nReal voices: Sarah K.`, `ICMT: ${JSON.stringify(fields.ICMT)}`);
    assert(fields.ISFT === 'Stackapella', `ISFT: ${fields.ISFT}`);
    // iXML (the voices worker's and the server's table): NOTE the text, USER the machine fields.
    const ixml = new TextDecoder().decode(all.find((c) => c.id === 'iXML')?.bytes ?? new Uint8Array(0));
    assert(ixml.includes(`<NOTE>${MARK.text}</NOTE>`), ixml);
    assert(ixml.includes(`<USER>ai_generated=true;digital_source_type=${MARK.digitalSourceType};generator=${MARK.generator}</USER>`), ixml);
    const bext = all.find((c) => c.id === 'bext');
    assert(bext && bext.bytes.length === 602, 'a bext chunk');
    assert(new TextDecoder().decode(bext.bytes.slice(0, 256)).replace(/\0+$/, '') === MARK.text, 'bext Description is the marking');
});

test('ai mark: a WAV without an AI voice is not marked', async () => {
    const all = chunks(await encodeWav([new Float32Array(480)], 48000, { comment: 'Real voices: Sarah K.' }).arrayBuffer());
    assert(!all.some((c) => c.id === 'bext') && !all.some((c) => c.id === 'iXML'), 'no bext or iXML');
    const fields = info(all.find((c) => c.id === 'LIST').bytes);
    assert(fields.ICMT === 'Real voices: Sarah K.' && !fields.ISFT, JSON.stringify(fields));
    assert(!chunks(await encodeWav([new Float32Array(480)], 48000).arrayBuffer()).some((c) => c.id === 'LIST'), 'no credits, no LIST');
});

test('ai mark: an MP3 holding an AI voice carries ID3 COMM and TXXX AI_GENERATED / AI_GENERATOR / DIGITAL_SOURCE_TYPE', () => {
    const tag = id3Tag(MARK.text, { AI_GENERATED: 'true', AI_GENERATOR: MARK.generator, DIGITAL_SOURCE_TYPE: MARK.digitalSourceType });
    const size = (tag[6] << 21) | (tag[7] << 14) | (tag[8] << 7) | tag[9];
    assert(size === tag.length - 10, 'its size is its frames');
    const frames = [];
    for (let at = 10; at + 10 <= tag.length; ) {
        const id = String.fromCharCode(...tag.slice(at, at + 4));
        const n = (tag[at + 4] << 24) | (tag[at + 5] << 16) | (tag[at + 6] << 8) | tag[at + 7];
        frames.push({ id, body: tag.slice(at + 10, at + 10 + n) });
        at += 10 + n;
    }
    assert(frames.map((f) => f.id).join() === 'COMM,TXXX,TXXX,TXXX', frames.map((f) => f.id).join());
    const utf = (bytes) => new TextDecoder('utf-16le').decode(bytes.slice(2)).replace(/\0+$/, '');
    assert(utf(frames[0].body.slice(8)) === MARK.text, 'the comment is the marking');
    const txxx = frames.slice(1).map((f) => {
        const body = f.body.slice(1);
        let end = 2;
        while (end + 1 < body.length && !(body[end] === 0 && body[end + 1] === 0)) end += 2;
        return [utf(body.slice(0, end)), utf(body.slice(end + 2))];
    });
    assert(JSON.stringify(txxx) === JSON.stringify([['AI_GENERATED', 'true'], ['AI_GENERATOR', MARK.generator], ['DIGITAL_SOURCE_TYPE', MARK.digitalSourceType]]), JSON.stringify(txxx));
});
