<?php

use App\Http\Controllers\NovelParserController;
use Illuminate\Support\Facades\Route;

Route::controller(NovelParserController::class)->group(function () {
    Route::get('/parse', 'parse');
});
