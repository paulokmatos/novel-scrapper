<?php

use App\Http\Controllers\NovelParserController;
use Illuminate\Support\Facades\Route;

Route::controller(NovelParserController::class)->group(function () {
    Route::get('/novels/{page?}', 'list');
    Route::get('/novel/chapters', 'listChapters');
    Route::post('/novel/chapters/parse', 'parse');
});
