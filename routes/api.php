<?php

use App\Http\Controllers\Api\ClickRedirectionController;
use App\Http\Controllers\Api\PostbackController;
use App\Http\Controllers\ModuleController;
use App\Support\Countries\CountryRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::patch('clicks/{click_id}/status', [ClickRedirectionController::class, 'updateStatus']);


Route::get('/api/m/{module}/table', [ModuleController::class, 'table']);

Route::get('/api/countries/search', function (Request $request, CountryRepository $countries) {
    return response()->json(
        $countries->search($request->query('q', ''))
    );
});
Route::get('/api/countries/all', function (CountryRepository $countries) {
    return response()->json($countries->all());
});
