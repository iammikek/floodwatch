<?php

use App\Http\Controllers\FloodWatchPolygonsController;
use App\Http\Controllers\FloodWatchRiverLevelsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LocationBookmarkController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::get('/flood-watch/polygons', FloodWatchPolygonsController::class)->name('flood-watch.polygons');
Route::get('/flood-watch/river-levels', FloodWatchRiverLevelsController::class)->name('flood-watch.river-levels');

Route::livewire('/', 'flood-watch-dashboard');

Route::redirect('/dashboard', '/')->name('dashboard');

Route::middleware('auth')->get('/admin', App\Http\Controllers\Admin\DashboardController::class)->name('admin.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/bookmarks', [LocationBookmarkController::class, 'store'])->name('bookmarks.store');
    Route::post('/bookmarks/{bookmark}/default', [LocationBookmarkController::class, 'setDefault'])->name('bookmarks.set-default');
    Route::delete('/bookmarks/{bookmark}', [LocationBookmarkController::class, 'destroy'])->name('bookmarks.destroy');
});

require __DIR__.'/auth.php';
