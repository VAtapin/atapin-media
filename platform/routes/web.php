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
    Route::middleware('can:users.manage')->prefix('desktop')->group(function(){
        Route::get('users',[\App\Http\Controllers\UserController::class,'index'])->name('users.index');
        Route::post('users',[\App\Http\Controllers\UserController::class,'store'])->name('users.store');
        Route::patch('users/{user}',[\App\Http\Controllers\UserController::class,'update'])->name('users.update');
    });
    Route::middleware('can:imports.manage')->prefix('desktop')->group(function(){
        Route::get('imports',[\App\Http\Controllers\ImportController::class,'index'])->name('imports.index');
        Route::post('imports',[\App\Http\Controllers\ImportController::class,'store'])->name('imports.store');
        Route::get('imports/{record}',[\App\Http\Controllers\ImportController::class,'show'])->name('imports.show');
        Route::get('collections/{collection}',[\App\Http\Controllers\ImportController::class,'collection'])->name('collections.show');
    });
    Route::middleware('can:projects.manage')->prefix('desktop')->group(function () {
        Route::resource('projects', \App\Http\Controllers\ProjectController::class)->except('destroy');
        Route::get('tasks',[\App\Http\Controllers\TaskController::class,'index'])->name('tasks.index');
        Route::post('tasks',[\App\Http\Controllers\TaskController::class,'store'])->name('tasks.store');
        Route::patch('tasks/{task}',[\App\Http\Controllers\TaskController::class,'update'])->name('tasks.update');
        Route::get('calendar',\App\Http\Controllers\CalendarController::class)->name('calendar');
    });
    Route::post('/logout', [AuthController::class,'destroy'])->name('logout');
    Route::get('/desktop', DesktopController::class)->middleware('can:desktop.view')->name('desktop');
    Route::get('/desktop/wallpaper', [SettingsController::class, 'wallpaper'])->middleware('can:desktop.view')->name('desktop.wallpaper');
    Route::get('/desktop/media', [MediaController::class,'index'])->middleware('can:media.view')->name('media.index');
    Route::post('/desktop/media', [MediaController::class,'store'])->middleware('can:media.upload')->name('media.store');
    Route::get('/desktop/media/{media}', [MediaController::class,'show'])->middleware('can:media.view')->name('media.show');
    Route::patch('/desktop/media/{media}', [MediaController::class,'update'])->middleware('can:media.edit')->name('media.update');
    Route::get('/desktop/media/{media}/download', [MediaController::class,'download'])->middleware('can:media.view')->name('media.download');
    Route::get('/desktop/media/{media}/preview', [MediaController::class,'preview'])->middleware('can:media.view')->name('media.preview');
    Route::get('/desktop/shop', [\App\Http\Controllers\ShopController::class, 'index'])->middleware('can:shop.manage')->name('shop.index');
    Route::post('/desktop/shop', [\App\Http\Controllers\ShopController::class, 'store'])->middleware('can:shop.manage')->name('shop.store');
    Route::get('/desktop/settings', [SettingsController::class,'edit'])->middleware('can:settings.manage')->name('settings');
    Route::put('/desktop/settings', [SettingsController::class,'update'])->middleware('can:settings.manage');
    Route::post('/desktop/settings/roles', [SettingsController::class,'storeRole'])->middleware('can:users.manage')->name('settings.roles.store');
    Route::put('/desktop/settings/roles/{role}', [SettingsController::class,'updateRole'])->middleware('can:users.manage')->name('settings.roles.update');
    Route::get('/desktop/audit', [DesktopController::class,'audit'])->middleware('can:audit.view')->name('audit');
});
