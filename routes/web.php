<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapTileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/snow', [HomeController::class, 'snow'])->name('snow');
Route::get('/openseamap/{z}/{x}/{y}', [MapTileController::class, 'seamap'])->name('openseamap');
