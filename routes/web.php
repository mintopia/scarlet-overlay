<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapTileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/snow', [HomeController::class, 'snow'])->name('snow');
Route::get('/openseamap/{z}/{x}/{y}', [MapTileController::class, 'seamap'])->name('openseamap');

Route::get('/admin/settings', fn () => Inertia::render('Admin/Settings'))->name('admin.settings');
