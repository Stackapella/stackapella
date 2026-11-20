<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Worker\AiNeedsController;
use App\Http\Controllers\Api\Worker\AiUploadsController;
use Illuminate\Support\Facades\Route;

/*
| The AI voices worker API (contract/api.md, "The AI voices worker"): the batch renderer, on its bearer token
| (AuthenticateAiWorker), with no session, cookie or CSRF. Mounted at /api/worker/ai, named worker.ai.*.
*/

Route::get('/needs', AiNeedsController::class)->name('needs');
Route::post('/uploads', [AiUploadsController::class, 'store'])->name('uploads.create');
Route::post('/uploads/{upload}/complete', [AiUploadsController::class, 'complete'])->name('uploads.complete');
Route::get('/uploads/{upload}', [AiUploadsController::class, 'show'])->name('uploads.show');
