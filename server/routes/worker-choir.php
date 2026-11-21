<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Worker\ChoirController;
use Illuminate\Support\Facades\Route;

/*
| Hear yourself as a choir's render jobs (contract/api.md, "The AI voices worker", worker.choir.*): the same worker,
| token and stack as routes/worker.php. Mounted at /api/worker/choir, named worker.choir.*.
*/

Route::post('/claim', [ChoirController::class, 'claim'])->name('claim');
Route::post('/jobs/{job}/parts', [ChoirController::class, 'startPart'])->name('parts.create');
Route::post('/parts/{part}/complete', [ChoirController::class, 'completePart'])->name('parts.complete');
Route::post('/jobs/{job}/finish', [ChoirController::class, 'finish'])->name('jobs.finish');
Route::post('/jobs/{job}/extend', [ChoirController::class, 'extend'])->name('jobs.extend');
Route::post('/status', [ChoirController::class, 'status'])->name('status');
Route::post('/jobs/{job}/reference', [ChoirController::class, 'startReference'])->name('references.create');
Route::post('/references/{reference}/complete', [ChoirController::class, 'completeReference'])->name('references.complete');
