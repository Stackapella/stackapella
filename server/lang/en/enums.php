<?php

declare(strict_types=1);

return [
    'admin_role' => [
        'admin' => 'Admin',
        'editor' => 'Editor',
    ],
    'block_type' => [
        'email' => 'Email address',
        'email_domain' => 'Email domain',
        'ip' => 'IP address',
        'ip_range' => 'IP range',
        'card_fingerprint' => 'Card',
    ],
    'credit_mode' => [
        'name' => 'By name',
        'anonymous' => 'Anonymous',
    ],
    'submission_status' => [
        'uploading' => 'Uploading',
        'processing' => 'Processing',
        'submitted' => 'Submitted',
        'in_review' => 'In review',
        'retake' => 'Another take asked for',
        'rejected' => 'Not used',
        'accepted' => 'Now the guide',
        'failed' => 'Upload failed',
    ],
    'template_access' => [
        'free' => 'Free',
        'plus' => 'Plus',
    ],
    'security_event_type' => [
        'admin_sign_in' => 'Admin sign-in',
        'sign_up' => 'Sign-up',
        'sign_in' => 'Sign-in',
        'password_reset' => 'Password reset',
        'email_changed' => 'Email changed',
        'account_deleted' => 'Account deleted',
        'sign_in_refused' => 'Sign-in refused',
        'second_factor_failed' => 'Wrong second-factor code',
        'second_factor_locked' => 'Second factor locked',
        'rate_limited' => 'Hit a limit',
        'blocked' => 'Stopped by the blocklist',
        'trial_refused' => 'Trial refused (the account or the card had one)',
        'payment_failed' => 'A Plus payment failed',
        'share' => 'A harmony shared',
        'report' => 'A shared harmony reported',
    ],
];
