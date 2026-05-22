<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BoatMetricsController;
use App\Http\Controllers\Admin\JourneyController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\StreamMonitorController;
use App\Http\Controllers\Admin\TrackerController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasskeyController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapTileController;
use App\Http\Controllers\OverlayController;
use App\Http\Controllers\SrtMetricsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/', fn () => redirect('/dashboard'));

Route::get('/overlay', [OverlayController::class, 'index'])->name('overlay');
Route::get('/snow', [HomeController::class, 'snow'])->name('snow');
Route::get('/openseamap/{z}/{x}/{y}', [MapTileController::class, 'seamap'])->name('openseamap');
Route::get('/metrics/srt', SrtMetricsController::class)->name('metrics.srt');

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
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::put('/settings/identity', [SettingsController::class, 'updateIdentity'])->name('admin.settings.identity');
    Route::put('/settings/port', [SettingsController::class, 'updatePort'])->name('admin.settings.port');
    Route::put('/settings/stream', [SettingsController::class, 'updateStream'])->name('admin.settings.stream');
    Route::get('/stream', [StreamMonitorController::class, 'index'])->name('admin.stream');
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

    // Journey management
    Route::get('/journeys', [JourneyController::class, 'index'])->name('admin.journeys');
    Route::get('/journeys/create', [JourneyController::class, 'create'])->name('admin.journeys.create');
    Route::get('/journeys/import', [JourneyController::class, 'importForm'])->name('admin.journeys.import');
    Route::post('/journeys', [JourneyController::class, 'store'])->name('admin.journeys.store');
    Route::post('/journeys/import', [JourneyController::class, 'import'])->name('admin.journeys.import.store');
    Route::get('/journeys/{journey}/edit', [JourneyController::class, 'edit'])->name('admin.journeys.edit');
    Route::put('/journeys/{journey}', [JourneyController::class, 'update'])->name('admin.journeys.update');
    Route::delete('/journeys/{journey}', [JourneyController::class, 'destroy'])->name('admin.journeys.destroy');
    Route::post('/journeys/{journey}/end', [JourneyController::class, 'end'])->name('admin.journeys.end');
    Route::post('/journeys/{journey}/gpx', [JourneyController::class, 'uploadGpx'])->name('admin.journeys.gpx');
});
