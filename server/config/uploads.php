<?php

declare(strict_types=1);

/*
| Presigned uploads (docs/trust-and-safety-plan.md, "Uploads"; the abuse review, 2026-11-21). A
| presigned PUT can't bind its size (S3 and Spaces never sign Content-Length), so links are short
| and every one is kept in upload_links, where the sweeper (app:uploads:sweep, every five minutes)
| deletes anything bigger than declared at once and every version left at a link's key once it's done.
*/

return [
    // How long a link opens a PUT for. The app asks for one and sends at once, so a few minutes is plenty
    // (S3 checks the expiry when the PUT starts, not when it ends, so a slow upload still finishes).
    'link_minutes' => (int) (env('UPLOAD_LINK_MINUTES') ?: 3),
    // How long after its link expires an exactly-sized upload waits for its complete before it's swept.
    'keep_minutes' => 60,
];
