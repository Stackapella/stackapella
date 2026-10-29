<?php

declare(strict_types=1);

use App\Http\Controllers\Dev\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The stand-in for a presigned PUT to Spaces (local and tests only)
|--------------------------------------------------------------------------
|
| Outside the `web` group, like Spaces itself: no session, no cookies and no CSRF token. The
| signature in the URL is the only key (App\Support\Storage\Uploads).
|
*/

if (app()->environment('local', 'testing')) {
    Route::put('/dev/upload/{key}', UploadController::class)->where('key', '[A-Za-z0-9/_\-]+(\.[A-Za-z0-9]+)?')->middleware('signed')->name('dev.upload');
}
