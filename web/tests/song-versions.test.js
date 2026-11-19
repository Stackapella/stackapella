// A library song's versions (js/song-versions.js; Hillel, 2026-11-18, "One song, US/UK switch"): a tap opens the version
// kept on this device, else the one people here start on, else the song's own; a link names a version by its own id.
import { asVersion, entryFor, keepVersion, startingVersion, versionsOf } from '/js/song-versions.js';
import { local } from '/js/storage.js';
import { assert, test } from './harness.js';

const assertEqual = (actual, expected, what = '') => assert(actual === expected, `${what} ${JSON.stringify(actual)} is not ${JSON.stringify(expected)}`.trim());

const carol = {
    id: 'midnight-clear',
    summary: true,
    songVersions: [{ id: 'midnight-clear', name: 'us', here: false }, { id: 'midnight-clear-uk', name: 'uk', here: true }],
};
const plain = { id: 'plain', summary: true, songVersions: [] };

test('song versions: here first, then a kept pick wins, and a song without versions is itself', () => {
    local.remove('stackapella.songVersion.midnight-clear');
    assertEqual(startingVersion(carol), 'midnight-clear-uk', 'the one people here start on');
    keepVersion(carol, 'us');
    assertEqual(startingVersion(carol), 'midnight-clear', 'the kept pick');
    keepVersion(carol, 'zz');
    assertEqual(startingVersion(carol), 'midnight-clear-uk', 'a pick it no longer has falls back');
    local.remove('stackapella.songVersion.midnight-clear');
    assertEqual(startingVersion(plain), 'plain');
    assertEqual(versionsOf(plain).length, 0);
    assertEqual(versionsOf({ id: 'x' }).length, 0, 'an older catalogue without songVersions');
});

test('song versions: a version is the song\'s summary under its own id, and a link finds it', () => {
    const uk = asVersion(carol, 'midnight-clear-uk');
    assertEqual(uk.id, 'midnight-clear-uk');
    assert(uk.summary && uk.songVersions === carol.songVersions, 'still a summary, its versions kept');
    assert(asVersion(carol, 'midnight-clear') === carol, 'its own id is the song');
    assertEqual(entryFor([plain, carol], 'midnight-clear-uk')?.id, 'midnight-clear-uk');
    assertEqual(entryFor([plain, carol], 'plain')?.id, 'plain');
    assertEqual(entryFor([plain, carol], 'nothing'), null);
});
