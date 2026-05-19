<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapTileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/overlay', [HomeController::class, 'index'])->name('overlay');
Route::get('/snow', [HomeController::class, 'snow'])->name('snow');
Route::get('/openseamap/{z}/{x}/{y}', [MapTileController::class, 'seamap'])->name('openseamap');

Route::get('/login', [LoginController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'store'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/settings', fn () => Inertia::render('Admin/Settings'))->name('admin.settings');
    Route::get('/tracker', fn () => Inertia::render('Admin/Tracker'))->name('admin.tracker');
    Route::get('/metrics', fn () => Inertia::render('Admin/BoatMetrics'))->name('admin.metrics');
    Route::get('/profile', fn () => Inertia::render('Admin/Profile'))->name('admin.profile');
    Route::get('/team', fn () => Inertia::render('Admin/Team'))->name('admin.team');
});
