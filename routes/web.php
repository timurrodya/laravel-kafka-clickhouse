<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\GuestSearchController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\PlacementController;
use App\Http\Controllers\PlacementVariantController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AvailabilityController::class, 'index'])->name('availability.index');
Route::get('/search', [GuestSearchController::class, 'index'])->name('search.index');
Route::get('/stats', [StatsController::class, 'index'])->name('stats.index');

Route::get('/hotels', [HotelController::class, 'index'])->name('hotels.index');
Route::get('/hotels/{hotel}', [HotelController::class, 'show'])->name('hotels.show');
Route::get('/hotels/{hotel}/placements/create', [PlacementController::class, 'create'])->name('placements.create');
Route::post('/hotels/{hotel}/placements', [PlacementController::class, 'store'])->name('placements.store');
Route::get('/placements/{placement}/edit', [PlacementController::class, 'edit'])->name('placements.edit');
Route::put('/placements/{placement}', [PlacementController::class, 'update'])->name('placements.update');
Route::delete('/placements/{placement}', [PlacementController::class, 'destroy'])->name('placements.destroy');

Route::get('/placements/{placement}/variants/create', [PlacementVariantController::class, 'create'])->name('placement-variants.create');
Route::post('/placements/{placement}/variants', [PlacementVariantController::class, 'store'])->name('placement-variants.store');
Route::get('/placement-variants/{placementVariant}/edit', [PlacementVariantController::class, 'edit'])->name('placement-variants.edit');
Route::put('/placement-variants/{placementVariant}', [PlacementVariantController::class, 'update'])->name('placement-variants.update');
Route::delete('/placement-variants/{placementVariant}', [PlacementVariantController::class, 'destroy'])->name('placement-variants.destroy');

Route::get('/api/docs', fn () => view('api-docs'))->name('api.docs');
