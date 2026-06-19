<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEnvironmentController;
use App\Http\Controllers\Admin\AdminLogController;
use App\Http\Controllers\Admin\BoatMetricsController;
use App\Http\Controllers\Admin\CanonicalCatalogController;
use App\Http\Controllers\Admin\ExploreController;
use App\Http\Controllers\Admin\JourneyController;
use App\Http\Controllers\Admin\JourneyViewController as AdminJourneyViewController;
use App\Http\Controllers\Admin\PlanGroupController;
use App\Http\Controllers\Admin\PlannerController;
use App\Http\Controllers\Admin\PlanRouteController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StreamMonitorController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TrackerController;
use App\Http\Controllers\Admin\TracksController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasskeyController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JourneyViewController;
use App\Http\Controllers\LcarsController;
use App\Http\Controllers\MapTileController;
use App\Http\Controllers\OverlayController;
use App\Http\Controllers\PublicPlannerController;
use App\Http\Controllers\SrtMetricsController;
use App\Http\Controllers\WeatherMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/', fn () => redirect('/dashboard'));

// Hidden LCARS easter egg (see PLAN.md / ADR 0001). Public — exposes only data the
// public dashboard already broadcasts. Reached via Konami code on either dashboard.
Route::get('/lcars', [LcarsController::class, 'index'])->name('lcars');
Route::get('/lcars/series', [LcarsController::class, 'series'])->name('lcars.series');

Route::get('/overlay', [OverlayController::class, 'index'])->name('overlay');
Route::get('/camera', [OverlayController::class, 'camera'])->name('camera');
Route::get('/snow', [HomeController::class, 'snow'])->name('snow');
Route::get('/openseamap/{z}/{x}/{y}', [MapTileController::class, 'seamap'])->name('openseamap');
Route::get('/openseamap-dark/{z}/{x}/{y}', [MapTileController::class, 'seamapDark'])->name('openseamap-dark');
Route::get('/satellite/{z}/{y}/{x}', [MapTileController::class, 'satellite'])->where(['z' => '[0-9]+', 'y' => '[0-9]+', 'x' => '[0-9]+']);
Route::get('/seamark/{z}/{x}/{y}', [MapTileController::class, 'seamarkOverlay'])->name('seamark');
Route::get('/osm/{z}/{x}/{y}', [MapTileController::class, 'osm'])->name('osm');
Route::get('/cartodb-dark/{z}/{x}/{y}', [MapTileController::class, 'cartodbDark'])->name('cartodb-dark');
Route::get('/depth/{z}/{x}/{y}', [MapTileController::class, 'depthContours'])->name('depth-contours');
Route::get('/metrics/srt', SrtMetricsController::class)->name('metrics.srt');
Route::get('/metrics/weather', WeatherMetricsController::class)->name('metrics.weather');

Route::get('/journey', [JourneyViewController::class, 'index'])->name('journey.index');
Route::get('/journey/{journey:slug}', [JourneyViewController::class, 'show'])->name('journey.show');
Route::get('/api/journey/{journey:slug}/track', [JourneyViewController::class, 'track'])->name('journey.track');

Route::get('/planner/{plan:slug}', [PublicPlannerController::class, 'show'])->name('planner.public');

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
    Route::put('/passkey/{id}', [PasskeyController::class, 'update']);
    Route::delete('/passkey/{id}', [PasskeyController::class, 'destroy']);
    Route::get('/passkey/list', [PasskeyController::class, 'list']);
});

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::put('/settings/identity', [SettingsController::class, 'updateIdentity'])->name('admin.settings.identity');
    Route::put('/settings/stream', [SettingsController::class, 'updateStream'])->name('admin.settings.stream');
    Route::put('/settings/camera', [SettingsController::class, 'updateCamera'])->name('admin.settings.camera');
    Route::post('/settings/force-reload', [SettingsController::class, 'forceReload'])->name('admin.settings.force-reload');
    Route::get('broadcast', [StreamMonitorController::class, 'index'])->name('admin.broadcast');
    Route::post('broadcast/pull', [StreamMonitorController::class, 'updatePull'])->name('admin.broadcast.pull');
    Route::redirect('stream', 'broadcast');
    Route::get('/tracker', [TrackerController::class, 'index'])->name('admin.tracker');
    Route::get('/metrics', [BoatMetricsController::class, 'index'])->name('admin.metrics');
    Route::get('/explore', [ExploreController::class, 'index'])->name('admin.explore');
    Route::get('/explore/series', [ExploreController::class, 'series'])->name('admin.explore.series');
    Route::get('/explore/current', [ExploreController::class, 'current'])->name('admin.explore.current');
    Route::get('/environment', [AdminEnvironmentController::class, 'index'])->name('admin.environment');
    Route::get('/environment/series', [AdminEnvironmentController::class, 'series'])->name('admin.environment.series');
    Route::redirect('/weather', '/admin/environment');
    Route::get('/log', [AdminLogController::class, 'index'])->name('admin.log');
    Route::get('/tracks', [TracksController::class, 'index'])->name('admin.tracks');
    Route::patch('/ship-log/{shipLog}', [AdminLogController::class, 'update'])->name('admin.ship-log.update');
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
    Route::get('/journeys/{journey}', [AdminJourneyViewController::class, 'show'])->name('admin.journeys.show');
    Route::get('/journeys/{journey}/edit', [JourneyController::class, 'edit'])->name('admin.journeys.edit');
    Route::put('/journeys/{journey}', [JourneyController::class, 'update'])->name('admin.journeys.update');
    Route::delete('/journeys/{journey}', [JourneyController::class, 'destroy'])->name('admin.journeys.destroy');
    Route::post('/journeys/{journey}/start', [JourneyController::class, 'start'])->name('admin.journeys.start');
    Route::post('/journeys/{journey}/end', [JourneyController::class, 'end'])->name('admin.journeys.end');
    Route::post('/journeys/{journey}/gpx', [JourneyController::class, 'uploadGpx'])->name('admin.journeys.gpx');
    Route::post('/journeys/{journey}/reimport', [JourneyController::class, 'reimport'])->name('admin.journeys.reimport');

    // Planner
    Route::get('/planner', [PlannerController::class, 'index'])->name('admin.planner');
    Route::post('/planner', [PlannerController::class, 'store'])->name('admin.planner.store');
    Route::get('/planner/{plan}', [PlannerController::class, 'show'])->name('admin.planner.show');
    Route::put('/planner/{plan}', [PlannerController::class, 'update'])->name('admin.planner.update');
    Route::delete('/planner/{plan}', [PlannerController::class, 'destroy'])->name('admin.planner.destroy');
    Route::post('/planner/{plan}/share', [PlannerController::class, 'share'])->name('admin.planner.share');
    Route::delete('/planner/{plan}/share', [PlannerController::class, 'unshare'])->name('admin.planner.unshare');
    Route::post('/planner/{plan}/groups', [PlanGroupController::class, 'store'])->name('admin.planner.groups.store');
    Route::put('/planner/groups/{group}', [PlanGroupController::class, 'update'])->name('admin.planner.groups.update');
    Route::put('/planner/groups/{group}/reorder', [PlanGroupController::class, 'reorder'])->name('admin.planner.groups.reorder');
    Route::delete('/planner/groups/{group}', [PlanGroupController::class, 'destroy'])->name('admin.planner.groups.destroy');
    Route::post('/planner/groups/{group}/routes', [PlanRouteController::class, 'store'])->name('admin.planner.routes.store');
    Route::put('/planner/routes/{route}', [PlanRouteController::class, 'update'])->name('admin.planner.routes.update');
    Route::delete('/planner/routes/{route}', [PlanRouteController::class, 'destroy'])->name('admin.planner.routes.destroy');

    // Canonical metric catalog
    Route::get('metrics/catalog', [CanonicalCatalogController::class, 'index'])->name('admin.catalog');
    Route::post('metrics/catalog', [CanonicalCatalogController::class, 'store'])->name('admin.catalog.store');
    Route::put('metrics/catalog/{metric}', [CanonicalCatalogController::class, 'update'])->name('admin.catalog.update');
    Route::delete('metrics/catalog/{metric}', [CanonicalCatalogController::class, 'destroy'])->name('admin.catalog.destroy');
    Route::post('metrics/catalog/test', [CanonicalCatalogController::class, 'test'])->name('admin.catalog.test');
    Route::post('metrics/catalog/rollback', [CanonicalCatalogController::class, 'rollback'])->name('admin.catalog.rollback');
});
