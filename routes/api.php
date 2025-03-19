<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ChannelController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Admin Authentication Routes
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminController::class, 'login'])->name('admin.login');
    Route::post('/logout', [AdminController::class, 'logout'])->middleware('auth:admin');
});

// Admin YouTube Channels Routes
Route::group(['prefix' => 'admin/youtube', 'middleware' => ['auth:admin']], function () {
    Route::group(['prefix' => 'channels'], function (){
        Route::get('/', [ChannelController::class, 'index'])->name('admin.channels.index');
        Route::post('/', [ChannelController::class, 'store'])->name('admin.channels.store');
        Route::get('/{channelID}', [ChannelController::class, 'show'])->name('admin.channels.show');
        Route::put('/{channelID}', [ChannelController::class, 'update'])->name('admin.channels.update');
        Route::delete('/{channelID}', [ChannelController::class, 'destroy'])->name('admin.channels.destroy');
    });
});

// Public YouTube Routes
Route::prefix('youtube')->group(function () {
    Route::get('/channels', [ChannelController::class, 'index'])->name('youtube.channels.index');
});
