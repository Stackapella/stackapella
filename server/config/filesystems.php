<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Filesystems
|--------------------------------------------------------------------------
|
| Production writes ONLY to DigitalOcean Spaces (App Platform has no persistent disk):
| - media:   the public bucket behind the CDN (published mixes, official guides, share cards,
|            avatars, preview clips). Keys are random and immutable, so files cache for a year.
| - private: every take, beta take and export, reached only through temporaryUrl().
| - backup:  database dumps and the weekly copy.
|
| Code only uses the aliases Storage::disk('media'), ('private') and ('backup'). MEDIA_DISK,
| PRIVATE_DISK and BACKUP_DISK point them at local disks in development and at Spaces in
| production (docs/conventions.md, "Storage").
|
*/

$backupKey = env('BACKUP_SPACES_KEY');
$backupSecret = env('BACKUP_SPACES_SECRET');

$spaces = static fn (string $bucket, ?string $url = null, string $visibility = 'private', ?string $key = null, ?string $secret = null): array => [
    'driver' => 's3',
    'key' => $key ?? env('SPACES_KEY'),
    'secret' => $secret ?? env('SPACES_SECRET'),
    'region' => env('SPACES_REGION') ?: 'nyc3',
    'bucket' => $bucket,
    'endpoint' => env('SPACES_ENDPOINT') ?: 'https://nyc3.digitaloceanspaces.com',
    'url' => $url,
    'visibility' => $visibility,
    'use_path_style_endpoint' => false,
    'throw' => true,
    'report' => true,
];

$cdnUrl = env('CDN_URL');

$disks = [

    'local' => [
        'driver' => 'local',
        'root' => storage_path('app/private'),
        'serve' => false,
        'throw' => false,
        'report' => false,
    ],

    'media_local' => [
        'driver' => 'local',
        'root' => storage_path('app/media'),
        'url' => rtrim((string) (env('MEDIA_URL') ?: 'http://127.0.0.1:8000/media'), '/'),
        'visibility' => 'public',
        'throw' => true,
        'report' => false,
    ],

    'private_local' => [
        'driver' => 'local',
        'root' => storage_path('app/private-media'),
        'throw' => true,
        'report' => false,
    ],

    'backup_local' => [
        'driver' => 'local',
        'root' => storage_path('app/backups'),
        'throw' => true,
        'report' => false,
    ],

    'spaces_media' => $spaces((string) (env('SPACES_BUCKET') ?: 'stackapella-media'), is_string($cdnUrl) && $cdnUrl !== '' ? $cdnUrl : null, 'public'),
    'spaces_private' => $spaces((string) (env('SPACES_PRIVATE_BUCKET') ?: 'stackapella-private')),
    // Its own key where one is set (BACKUP_SPACES_KEY, scoped to the backup bucket): a leaked app
    // key then can't delete the backups.
    'spaces_backup' => $spaces((string) (env('SPACES_BACKUP_BUCKET') ?: 'stackapella-backup'), null, 'private', is_string($backupKey) && $backupKey !== '' ? $backupKey : null, is_string($backupSecret) && $backupSecret !== '' ? $backupSecret : null),

];

// An unknown disk name leaves the alias unconfigured, which the readiness check reports.
$disks['media'] = $disks[(string) (env('MEDIA_DISK') ?: 'media_local')] ?? null;
$disks['private'] = $disks[(string) (env('PRIVATE_DISK') ?: 'private_local')] ?? null;
$disks['backup'] = $disks[(string) (env('BACKUP_DISK') ?: 'backup_local')] ?? null;

// Locally, private files get temporaryUrl()s too: Laravel serves the alias at a signed route.
if (($disks['private']['driver'] ?? null) === 'local') {
    $disks['private']['serve'] = true;
    $disks['private']['url'] = rtrim((string) (env('APP_URL') ?: 'http://127.0.0.1:8000'), '/').'/private-media';
}

return [

    'default' => env('FILESYSTEM_DISK') ?: 'local',

    'disks' => array_filter($disks),

    'links' => [
        public_path('media') => storage_path('app/media'),
    ],

];
