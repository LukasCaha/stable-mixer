<?php

use App\Http\Controllers\MemoAudioController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->group(function () {
    Route::get('/memos/{memo}/audio', MemoAudioController::class)->name('memos.audio');
});
