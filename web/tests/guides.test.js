// Real voices (js/recorded-guides.js): a guide published as AAC in an MP4 decodes to its master,
// lined up to the sample, at the file's rate and at another context rate.
import { useDatabase } from '/js/db.js';
import { guideKey, loadRecordedGuide, recordingApplies, trimDecoded } from '/js/recorded-guides.js';
import { assert, close, test } from './harness.js';

async function decodeAt(rate, file) {
    const ctx = new OfflineAudioContext(1, 1, rate);
    const recording = { url: `/tests/fixtures/${file}`, sampleRate: Number(file.match(/\d+/)[0]), frames: 3 * Number(file.match(/\d+/)[0]), offsetFrames: 0 };
    useDatabase('stackapella-test-guides');
    try {
        return { recording, guide: await loadRecordedGuide(recording, { key: guideKey('t', 2, file), sampleRate: rate, decode: (bytes) => ctx.decodeAudioData(bytes) }) };
    } finally {
        useDatabase('stackapella-test');
    }
}

async function master(file) {
    const ctx = new OfflineAudioContext(1, 1, 48000);
    const buffer = await ctx.decodeAudioData(await (await fetch(`/tests/fixtures/${file}`)).arrayBuffer());
    return buffer.getChannelData(0);
}

function lagAgainst(reference, samples, rate) {
    // The strongest chirp correlation within a few ms, by brute force on the first chirp.
    const from = Math.round(0.2 * rate) - 50;
    let best = 0;
    let bestScore = -Infinity;
    for (let lag = -40; lag <= 40; lag++) {
        let score = 0;
        for (let i = from; i < from + Math.round(0.02 * rate); i++) score += reference[i] * (samples[i + lag] ?? 0);
        if (score > bestScore) {
            bestScore = score;
            best = lag;
        }
    }
    return best;
}

test('guides: a decoded AAC guide is exactly as long as its master, lined up to the sample (48 kHz)', async () => {
    const { guide } = await decodeAt(48000, 'guide-48000.m4a');
    assert(guide.samples.length === 144000, `${guide.samples.length} frames`);
    const reference = await master('guide-48000.wav');
    assert(lagAgainst(reference, guide.samples, 48000) === 0, `off by ${lagAgainst(reference, guide.samples, 48000)} frames`);
});

test('guides: a 44.1 kHz guide decoded at 48 kHz is scaled, and still lines up', async () => {
    const { guide } = await decodeAt(48000, 'guide-44100.m4a');
    assert(guide.samples.length === 144000, `${guide.samples.length} frames at 48 kHz`);
    const ctx = new OfflineAudioContext(1, 1, 48000);
    const reference = (await ctx.decodeAudioData(await (await fetch('/tests/fixtures/guide-44100.wav')).arrayBuffer())).getChannelData(0);
    assert(Math.abs(lagAgainst(reference, guide.samples, 48000)) <= 1, `off by ${lagAgainst(reference, guide.samples, 48000)} frames`);
});

test('guides: a decode that kept the priming has it skipped, scaled to the rate', () => {
    const recording = { sampleRate: 44100, frames: 44100, offsetFrames: 22050 };
    const withPriming = new Float32Array(Math.round(1024 * 48000 / 44100) + 48000 + 900);
    withPriming[Math.round(1024 * 48000 / 44100)] = 1;
    const trimmed = trimDecoded(withPriming, recording, 48000);
    assert(trimmed.primingSkipped && trimmed.samples.length === 48000 && trimmed.samples[0] === 1, 'skipped');
    close(trimmed.offsetSeconds, 0.5, 1e-12, 'the offset, in seconds');
    const exact = trimDecoded(new Float32Array(48000 + 300), recording, 48000);
    assert(!exact.primingSkipped && exact.samples.length === 48000, 'a decode without priming is kept from its start');
});

test('guides: the real voice plays only in its key, at full speed, for its version, unless the synth is chosen', () => {
    const recording = { url: 'x' };
    const template = { version: 2 };
    assert(recordingApplies({ recording, version: 2, template }), 'as recorded');
    assert(!recordingApplies({ recording, version: 2, template, transpose: 2 }), 'another key');
    assert(!recordingApplies({ recording, version: 2, template, speed: 0.85 }), 'slower');
    assert(!recordingApplies({ recording, version: 2, template: { version: 3 } }), 'a newer version of the notes');
    assert(!recordingApplies({ recording, version: 2, template, voice: 'synth' }), 'the synth chosen');
    assert(!recordingApplies({ recording: null, version: 2, template }), 'no recording');
});
