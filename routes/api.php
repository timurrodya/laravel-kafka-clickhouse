<?php

use App\Http\Controllers\Api\AvailabilityController as ApiAvailabilityController;
use App\Http\Controllers\Api\PriceByDayController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::post('/availability', [ApiAvailabilityController::class, 'store']);
Route::post('/prices', [PriceByDayController::class, 'store']);
Route::get('/search', SearchController::class);
