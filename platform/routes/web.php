<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DesktopController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SettingsController;

Route::get('/', fn () => view('home'))->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class,'create'])->name('login');
    Route::post('/login', [AuthController::class,'store']);
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class,'destroy'])->name('logout');
    Route::get('/desktop', DesktopController::class)->middleware('can:desktop.view')->name('desktop');
    Route::get('/desktop/wallpaper', [SettingsController::class, 'wallpaper'])->middleware('can:desktop.view')->name('desktop.wallpaper');
    Route::patch('/desktop/profile', [\App\Http\Controllers\UserController::class,'updateProfile'])->name('profile.update');
    Route::get('/desktop/profile/avatar', [\App\Http\Controllers\UserController::class,'avatar'])->name('profile.avatar');

    Route::middleware('can:users.manage')->prefix('desktop')->group(function () {
        Route::post('users', [\App\Http\Controllers\UserController::class,'store'])->name('users.store');
        Route::patch('users/{user}', [\App\Http\Controllers\UserController::class,'update'])->name('users.update');
    });
    Route::middleware('can:imports.manage')->prefix('desktop')->group(function () {
        Route::post('imports', [\App\Http\Controllers\ImportController::class,'store'])->name('imports.store');
    });
    Route::middleware('can:projects.manage')->prefix('desktop')->group(function () {
        Route::post('projects', [\App\Http\Controllers\ProjectController::class,'store'])->name('projects.store');
        Route::put('projects/{project}', [\App\Http\Controllers\ProjectController::class,'update'])->name('projects.update');
        Route::post('tasks', [\App\Http\Controllers\TaskController::class,'store'])->name('tasks.store');
        Route::patch('tasks/{task}', [\App\Http\Controllers\TaskController::class,'update'])->name('tasks.update');
    });
    Route::post('/desktop/media', [MediaController::class,'store'])->middleware('can:media.upload')->name('media.store');
    Route::patch('/desktop/media/{media}', [MediaController::class,'update'])->middleware('can:media.edit')->name('media.update');
    Route::get('/desktop/media/{media}/download', [MediaController::class,'download'])->middleware('can:media.view')->name('media.download');
    Route::get('/desktop/media/{media}/preview', [MediaController::class,'preview'])->middleware('can:media.view')->name('media.preview');
    Route::post('/desktop/shop', [\App\Http\Controllers\ShopController::class, 'store'])->middleware('can:shop.manage')->name('shop.store');
    Route::put('/desktop/settings', [SettingsController::class,'update'])->middleware('can:settings.manage')->name('settings');
    Route::post('/desktop/settings/roles', [SettingsController::class,'storeRole'])->middleware('can:users.manage')->name('settings.roles.store');
    Route::put('/desktop/settings/roles/{role}', [SettingsController::class,'updateRole'])->middleware('can:users.manage')->name('settings.roles.update');
});
