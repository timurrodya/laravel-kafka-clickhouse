<?php

use App\Http\Controllers\AvailabilityController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AvailabilityController::class, 'index'])->name('availability.index');
