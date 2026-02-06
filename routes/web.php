<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::livewire('/', 'flood-watch-dashboard')->name('flood-watch.dashboard');

Route::redirect('/dashboard', '/')->name('dashboard');

Route::livewire('/activities', 'activity-feed')->name('activities');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
