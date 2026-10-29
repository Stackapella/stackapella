<?php

declare(strict_types=1);

return [
    'sign_out' => 'Sign out',

    'sign_in' => [
        'title' => 'Sign in to admin',
        'intro' => 'Use the Google account that runs Stackapella. You\'ll confirm it\'s you with your passkey next.',
        'google' => 'Sign in with Google',
        'ended' => 'Your admin session ended. Sign in again to carry on.',
        'signed_out' => 'You\'re signed out.',
        'your_google_id' => 'Your email is on the admin list, but this Google ID isn\'t yet. Add it to ADMIN_GOOGLE_IDS (or EDITOR_GOOGLE_IDS), sign in again, then give yourself the role with php artisan app:admin:grant and this ID:',
        'errors' => [
            'cancelled' => 'Signing in was cancelled. Try again when you\'re ready.',
            'expired' => 'That took a little too long. Please try again.',
            'failed' => 'Google didn\'t answer. Please try again in a moment.',
            'unverified' => 'Google hasn\'t confirmed this account\'s email yet, so it can\'t be used to sign in.',
            'not_admin' => 'This Google account isn\'t an admin of Stackapella.',
            'suspended' => 'This account is suspended.',
            'blocked' => 'This account can\'t sign in.',
        ],
    ],

    'not_admin' => [
        'title' => 'Not an admin account',
        'body' => 'You\'re signed in, but this account can\'t open admin. Sign out and use the account that runs Stackapella.',
    ],

    'second_factor' => [
        'setup_title' => 'Protect your admin account',
        'setup_intro' => 'Admin needs a second step every time you sign in. Add a passkey, and an authenticator app as a backup.',
        'passkey_heading' => 'A passkey',
        'passkey_intro' => 'Your phone or laptop confirms it\'s you with your face, fingerprint or screen lock.',
        'passkey_add' => 'Add a passkey',
        'passkey_use' => 'Use my passkey',
        'passkey' => 'Passkey',
        'totp_heading' => 'An authenticator app',
        'totp_intro' => 'Scan this with an authenticator app, then type the 6-digit code it shows.',
        'totp_secret' => 'Or type this key: :secret',
        'totp_confirm' => 'Turn on the authenticator app',
        'code_label' => 'The 6-digit code',
        'code_or_recovery_label' => 'A code from your authenticator app, or a recovery code',
        'code_submit' => 'Continue',
        'codes_title' => 'Save your recovery codes',
        'codes_intro' => 'Each code works once, if you lose your passkey and your phone. Keep them somewhere safe. They won\'t be shown again.',
        'codes_saved' => 'I\'ve saved them',
        'challenge_title' => 'Confirm it\'s you',
        'challenge_intro' => 'One more step to open admin.',
        'sudo_intro' => 'This change needs a fresh check. Confirm it\'s you, and you\'ll go straight back.',
        'unsupported' => 'This browser can\'t use passkeys here. Use the authenticator app instead.',
        'cancelled' => 'Cancelled. Try again when you\'re ready.',
        'too_slow' => 'That took too long. Please try again.',
        'passkey_not_saved' => 'That passkey couldn\'t be saved. Please try again.',
        'passkey_failed' => 'That passkey didn\'t work. Try again, or use a code.',
        'code_mismatch' => 'That code didn\'t match. Check your phone\'s clock and try again.',
        'code_failed' => 'That code didn\'t work. Try again, or use a recovery code.',
        'locked' => 'Too many wrong codes, so admin is locked for a while. Every admin has been emailed.',
        'confirm_first' => 'Confirm it\'s you first.',
    ],

    'mail' => [
        'browser' => 'Browser: :agent',
        'method' => [
            'passkey' => 'passkey',
            'code' => 'code',
        ],
        'factor' => [
            'passkey' => 'a passkey',
            'totp' => 'an authenticator app',
        ],
        'signed_in' => [
            'subject' => 'New admin sign-in: :name',
            'title' => 'New admin sign-in',
            'how' => ':name opened admin with a :method.',
            'unexpected' => 'If you didn\'t expect this, remove them from ADMIN_GOOGLE_IDS in the App Platform settings.',
        ],
        'locked' => [
            'subject' => 'Admin locked: :name',
            'title' => 'Too many wrong codes',
            'what' => 'Someone typed too many wrong second-factor codes for :name, so admin is locked for a while.',
            'unexpected' => 'If it wasn\'t them, someone may have their Google sign-in. Check it, and their sessions.',
        ],
        'added' => [
            'subject' => 'New second factor: :name',
            'title' => 'A second factor was added',
            'what' => ':name added :method to their admin account.',
            'unexpected' => 'If you didn\'t expect this, reset their second factor from the console.',
        ],
        'reset' => [
            'subject' => 'Second factor reset: :name',
            'title' => 'A second factor was reset',
            'what' => 'Every passkey and the authenticator app for :name were removed from the console.',
            'unexpected' => 'They\'ll set up a new one the next time they open admin.',
        ],
    ],
];
