<?php

use App\Http\Controllers\Admin\BoatMetricsController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TrackerController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasskeyController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapTileController;
use App\Http\Controllers\OverlayController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/overlay', [OverlayController::class, 'index'])->name('overlay');
Route::get('/snow', [HomeController::class, 'snow'])->name('snow');
Route::get('/openseamap/{z}/{x}/{y}', [MapTileController::class, 'seamap'])->name('openseamap');

Route::get('/login', [LoginController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'store'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

Route::get('/register', [RegisterController::class, 'show'])->middleware('guest');
Route::post('/register', [RegisterController::class, 'store'])->middleware('guest');

// Passkey login routes (guest)
Route::post('/passkey/login/options', [PasskeyController::class, 'loginOptions'])->middleware('guest');
Route::post('/passkey/login', [PasskeyController::class, 'login'])->middleware('guest');

// Passkey management routes (auth)
Route::middleware('auth')->group(function () {
    Route::post('/passkey/register/options', [PasskeyController::class, 'registerOptions']);
    Route::post('/passkey/register', [PasskeyController::class, 'register']);
    Route::delete('/passkey/{id}', [PasskeyController::class, 'destroy']);
    Route::get('/passkey/list', [PasskeyController::class, 'list']);
});

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::put('/settings/identity', [SettingsController::class, 'updateIdentity'])->name('admin.settings.identity');
    Route::put('/settings/passage', [SettingsController::class, 'updatePassage'])->name('admin.settings.passage');
    Route::put('/settings/port', [SettingsController::class, 'updatePort'])->name('admin.settings.port');
    Route::get('/tracker', [TrackerController::class, 'index'])->name('admin.tracker');
    Route::get('/metrics', [BoatMetricsController::class, 'index'])->name('admin.metrics');
    Route::get('/profile', [ProfileController::class, 'index'])->name('admin.profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password');
    Route::get('/team', [TeamController::class, 'index'])->name('admin.team');
    Route::post('/team/invite', [TeamController::class, 'invite'])->name('admin.team.invite');
    Route::post('/team/invite/{invite}/resend', [TeamController::class, 'resend'])->name('admin.team.resend');
    Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('admin.team.destroy');
    Route::delete('/team/invite/{invite}', [TeamController::class, 'destroyInvite'])->name('admin.team.destroy-invite');
});
