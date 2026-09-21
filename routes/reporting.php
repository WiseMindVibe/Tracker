<?php

use App\Http\Controllers\ReportingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reporting routes
|--------------------------------------------------------------------------
| Include this file from routes/web.php:
|
|   require __DIR__.'/reporting.php';
|
| Both routes sit behind 'auth' (standard Laravel session auth) rather than
| the old tracker's custom TrackerAdminAccess token gate — the new tracker
| already has real user accounts, so there's no need to recreate a
| bolt-on token/cookie gate. Add 'verified' or a role/permission
| middleware here too if only certain users should see reporting.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/reporting', [ReportingController::class, 'index'])->name('reporting.index');
    Route::post('/reporting/report', [ReportingController::class, 'report'])->name('reporting.report');
});
