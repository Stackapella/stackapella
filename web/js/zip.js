// A ZIP of files, stored as they are (no compression: WAVs barely shrink), for Stems: each part
// on its own in one download. Plain code, no library.

const CRC_TABLE = (() => {
    const table = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        table[n] = c >>> 0;
    }
    return table;
})();

const DOS_DATE = 0x0021; // 1980-01-01: a date of 0 isn't valid, and some tools warn

/** Names as files: no folders, nothing a file system refuses, and each unique ("Alto (2).wav"). */
export function fileNames(names) {
    const seen = new Map();
    return names.map((name) => {
        const clean = name.replace(/[\\/:*?"<>|\u0000-\u001f]+/g, '-').replace(/^\.+/, '').trim() || 'part';
        const count = (seen.get(clean.toLowerCase()) ?? 0) + 1;
        seen.set(clean.toLowerCase(), count);
        if (count === 1) return clean;
        const dot = clean.lastIndexOf('.');
        return dot > 0 ? `${clean.slice(0, dot)} (${count})${clean.slice(dot)}` : `${clean} (${count})`;
    });
}

export function crc32(bytes) {
    let crc = 0xffffffff;
    for (let i = 0; i < bytes.length; i++) crc = CRC_TABLE[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);
    return (crc ^ 0xffffffff) >>> 0;
}

/** `files`: [{ name, bytes: Uint8Array }] as a ZIP Blob. Names are UTF-8. */
export function zip(files) {
    const encoder = new TextEncoder();
    const local = [];
    const central = [];
    let offset = 0;
    for (const file of files) {
        const name = encoder.encode(file.name);
        const crc = crc32(file.bytes);
        const head = new DataView(new ArrayBuffer(30));
        head.setUint32(0, 0x04034b50, true);
        head.setUint16(4, 20, true); // version needed
        head.setUint16(6, 0x0800, true); // UTF-8 names
        head.setUint16(12, DOS_DATE, true);
        head.setUint32(14, crc, true);
        head.setUint32(18, file.bytes.length, true);
        head.setUint32(22, file.bytes.length, true);
        head.setUint16(26, name.length, true);
        local.push(new Uint8Array(head.buffer), name, file.bytes);
        const entry = new DataView(new ArrayBuffer(46));
        entry.setUint32(0, 0x02014b50, true);
        entry.setUint16(4, 20, true);
        entry.setUint16(6, 20, true);
        entry.setUint16(8, 0x0800, true);
        entry.setUint16(14, DOS_DATE, true);
        entry.setUint32(16, crc, true);
        entry.setUint32(20, file.bytes.length, true);
        entry.setUint32(24, file.bytes.length, true);
        entry.setUint16(28, name.length, true);
        entry.setUint32(42, offset, true);
        central.push(new Uint8Array(entry.buffer), name);
        offset += 30 + name.length + file.bytes.length;
    }
    const size = central.reduce((sum, part) => sum + part.length, 0);
    const end = new DataView(new ArrayBuffer(22));
    end.setUint32(0, 0x06054b50, true);
    end.setUint16(8, files.length, true);
    end.setUint16(10, files.length, true);
    end.setUint32(12, size, true);
    end.setUint32(16, offset, true);
    return new Blob([...local, ...central, new Uint8Array(end.buffer)], { type: 'application/zip' });
}
