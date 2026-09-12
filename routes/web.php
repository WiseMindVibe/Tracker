<?php

use App\Http\Controllers\BlogRelayController;
use App\Http\Controllers\BufferRelayController;
use App\Http\Controllers\ConversionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\testController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('test', [testController::class, 'test'])->name('test');
});

// Route::resource('/m/{module}', [ModuleController::class]);

Route::get('/m/{module}', [ModuleController::class, 'index'])->name('modules.index');
Route::get('/m/{module}/create', [ModuleController::class, 'create'])->name('modules.create');
Route::post('/m/{module}', [ModuleController::class, 'store'])->name('modules.store');
Route::get('/m/{module}/{id}/view', [ModuleController::class, 'view'])->name('moudles.view');
Route::get('/m/{module}/{id}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
Route::put('/m/{module}/{id}', [ModuleController::class, 'update'])->name('modules.update');
Route::delete('/m/{module}/{id}', [ModuleController::class, 'destroy'])->name('modules.destroy');

Route::get('/conversions', [ConversionController::class, 'index'])->name('conversions.index');
Route::get('/conversions/{click}/history', [ConversionController::class, 'history'])->name('conversions.history');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])
    ->name('notifications.mark-all-read');
Route::patch('/notifications/{notification}/toggle-read', [NotificationController::class, 'toggleRead'])
    ->name('notifications.toggle-read');

Route::get('/r', [RedirectController::class, 'handle'])->name('click.redirect');

// routes/web.php — separate group, only active on non-tracker domains
Route::domain('{domain}')->group(function () {
    Route::get('/_t/enter/{clickId}', [BlogRelayController::class, 'enter'])->name('blog.enter');
    Route::get('/_t/return/{clickId}', [BlogRelayController::class, 'returnFromBuffer'])->name('blog.return');
    Route::get('/_t/bounce/{clickId}', [BufferRelayController::class, 'bounce'])->name('buffer.bounce');
});

require __DIR__.'/settings.php';
require __DIR__.'/postback.php';
require __DIR__.'/reporting.php';
