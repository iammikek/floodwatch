<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['jsonapi'])->group(function (): void {
    Route::get('map-data', [App\Http\Controllers\Api\V1\MapDataController::class, 'index'])->name('api.v1.map-data');
    Route::get('floods', [App\Http\Controllers\Api\V1\FloodsController::class, 'index'])->name('api.v1.floods');
    Route::get('incidents', [App\Http\Controllers\Api\V1\IncidentsController::class, 'index'])->name('api.v1.incidents');
    Route::get('river-levels', [App\Http\Controllers\Api\V1\RiverLevelsController::class, 'index'])->name('api.v1.river-levels');
    Route::get('forecast', [App\Http\Controllers\Api\V1\ForecastController::class, 'index'])->name('api.v1.forecast');
    Route::get('weather', [App\Http\Controllers\Api\V1\WeatherController::class, 'index'])->name('api.v1.weather');
    Route::get('risk', [App\Http\Controllers\Api\V1\RiskController::class, 'index'])->name('api.v1.risk');
    Route::get('activities', [App\Http\Controllers\Api\V1\ActivitiesController::class, 'index'])->name('api.v1.activities');
    Route::post('chat', [App\Http\Controllers\Api\V1\ChatController::class, 'store'])->name('api.v1.chat');
});
