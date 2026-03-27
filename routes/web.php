<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\FloodWatchPolygonsController;
use App\Http\Controllers\FloodWatchRiverLevelsController;
use App\Http\Controllers\FloodWatchTilesController;
use App\Http\Controllers\FloodWatchWarningsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LocationBookmarkController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EnsureFloodWatchSession;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::get('/flood-watch/polygons', FloodWatchPolygonsController::class)
    ->middleware([EnsureFloodWatchSession::class, 'throttle:flood-watch-api'])
    ->name('flood-watch.polygons');
Route::get('/flood-watch/river-levels', FloodWatchRiverLevelsController::class)
    ->middleware([EnsureFloodWatchSession::class, 'throttle:flood-watch-api'])
    ->name('flood-watch.river-levels');
Route::get('/api/lake/warnings/tiles/{z}/{x}/{y}.pbf', [FloodWatchTilesController::class, 'warningsTile'])
    ->middleware(['throttle:flood-watch-api'])
    ->whereNumber(['z', 'x', 'y'])
    ->name('flood-watch.tiles.warnings');
Route::get('/api/lake/polygons/tiles/{dataset}/{z}/{x}/{y}.pbf', [FloodWatchTilesController::class, 'polygonsTile'])
    ->middleware(['throttle:flood-watch-api'])
    ->whereNumber(['z', 'x', 'y'])
    ->name('flood-watch.tiles.polygons');

Route::get('/api/lake/warnings', FloodWatchWarningsController::class)
    ->middleware(['throttle:flood-watch-api'])
    ->name('flood-watch.warnings');

Route::livewire('/', 'flood-watch-dashboard');

Route::redirect('/dashboard', '/')->name('dashboard');

Route::middleware('auth')->get('/admin', DashboardController::class)->name('admin.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/bookmarks', [LocationBookmarkController::class, 'store'])->name('bookmarks.store');
    Route::post('/bookmarks/{bookmark}/default', [LocationBookmarkController::class, 'setDefault'])->name('bookmarks.set-default');
    Route::delete('/bookmarks/{bookmark}', [LocationBookmarkController::class, 'destroy'])->name('bookmarks.destroy');
});

require __DIR__.'/auth.php';
