<?php

use App\Http\Controllers\PostbackController;
use Illuminate\Support\Facades\Route;

Route::match(
    ['GET', 'POST'],
    '/postback/{affiliateCatalog}',
    PostbackController::class
)->name('postback');
