// The stems' ZIP (js/zip.js): a well-formed archive, stored, whose files come back byte for byte.
import { crc32, zip } from '/js/zip.js';
import { assert, test } from './harness.js';

test('zip: the CRC is the standard one, and the files read back from the archive', async () => {
    assert(crc32(new TextEncoder().encode('123456789')) === 0xcbf43926, 'the check value');
    const a = new Uint8Array([1, 2, 3, 4]);
    const b = new TextEncoder().encode('Alto ünd Bass');
    const bytes = new Uint8Array(await zip([{ name: 'Alto.wav', bytes: a }, { name: 'Bäss.wav', bytes: b }]).arrayBuffer());
    const view = new DataView(bytes.buffer);
    const end = bytes.length - 22;
    assert(view.getUint32(end, true) === 0x06054b50 && view.getUint16(end + 10, true) === 2, 'two entries');
    // Walk the central directory to each local file.
    let at = view.getUint32(end + 16, true);
    const names = [];
    for (let i = 0; i < 2; i++) {
        const nameLength = view.getUint16(at + 28, true);
        const local = view.getUint32(at + 42, true);
        const size = view.getUint32(at + 20, true);
        names.push(new TextDecoder().decode(bytes.subarray(at + 46, at + 46 + nameLength)));
        const data = bytes.subarray(local + 30 + view.getUint16(local + 26, true), local + 30 + view.getUint16(local + 26, true) + size);
        assert(crc32(data) === view.getUint32(at + 16, true), `file ${i} intact`);
        at += 46 + nameLength;
    }
    assert(names.join() === 'Alto.wav,Bäss.wav', names.join());
});

test('zip: names become files, never folders, and each is unique', async () => {
    const { fileNames } = await import('/js/zip.js');
    const names = fileNames(['Song - Alto.wav', 'Song - Alto.wav', 'Song - A/B.wav', '..hidden.wav']);
    assert(names.join('|') === 'Song - Alto.wav|Song - Alto (2).wav|Song - A-B.wav|hidden.wav', names.join('|'));
});
