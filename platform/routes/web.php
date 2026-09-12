<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DesktopController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SettingsController;

Route::get('/', fn () => view('home'))->name('home');
Route::get('/upload/{path?}', fn () => redirect('/desktop', 303))->where('path', '.*');
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
        Route::get('imports', [\App\Http\Controllers\ImportController::class, 'index'])->name('imports.index');
        Route::get('imports/options', [\App\Http\Controllers\ImportController::class, 'options'])->name('imports.options');
        Route::get('imports/files', [\App\Http\Controllers\ImportController::class, 'files'])->name('imports.files');
        Route::post('imports', [\App\Http\Controllers\ImportController::class,'store'])->name('imports.store');
        Route::post('imports/{run}/retry', [\App\Http\Controllers\ImportController::class,'retry'])->name('imports.retry');
        Route::post('imports/{run}/stop', [\App\Http\Controllers\ImportController::class,'stop'])->name('imports.stop');
    });
    Route::middleware('can:projects.manage')->prefix('desktop')->group(function () {
        Route::post('projects', [\App\Http\Controllers\ProjectController::class,'store'])->name('projects.store');
        Route::put('projects/{project}', [\App\Http\Controllers\ProjectController::class,'update'])->name('projects.update');
        Route::post('tasks', [\App\Http\Controllers\TaskController::class,'store'])->name('tasks.store');
        Route::patch('tasks/{task}', [\App\Http\Controllers\TaskController::class,'update'])->name('tasks.update');
    });
    Route::get('/desktop/media/library', [MediaController::class,'library'])->middleware('can:media.view')->name('media.library');
    Route::patch('/desktop/media/organize', [\App\Http\Controllers\MediaOrganizationController::class,'update'])->middleware('can:media.edit')->name('media.organize');
    Route::get('/desktop/media/collections', [\App\Http\Controllers\MediaCollectionController::class,'index'])->middleware('can:media.view')->name('media.collections');
    Route::post('/desktop/media/collections', [\App\Http\Controllers\MediaCollectionController::class,'store'])->middleware('can:media.edit');
    Route::get('/desktop/media/collections/{collection}', [\App\Http\Controllers\MediaCollectionController::class,'show'])->middleware('can:media.view');
    Route::patch('/desktop/media/collections/{collection}', [\App\Http\Controllers\MediaCollectionController::class,'update'])->middleware('can:media.edit');
    Route::patch('/desktop/media/collections/{collection}/members/{media}', [\App\Http\Controllers\MediaCollectionController::class,'member'])->middleware('can:media.edit');
    Route::get('/desktop/media/{media}/details', [MediaController::class,'details'])->middleware('can:media.view')->name('media.details');
    Route::post('/desktop/media/{media}/technical', [\App\Http\Controllers\MediaTechnicalController::class,'store'])->middleware(['can:media.edit','throttle:30,1']);
    Route::post('/desktop/media/{media}/cover', [\App\Http\Controllers\MediaCoverController::class,'store'])->middleware(['can:media.edit','can:content.edit'])->name('media.cover');
    Route::post('/desktop/media/{media}/classifications/{classification}/undo', [\App\Http\Controllers\ContentClassificationController::class,'undo'])->middleware(['can:media.edit', 'can:content.edit'])->name('media.classification.undo');
    Route::get('/desktop/content', [\App\Http\Controllers\ImportedContentController::class,'index'])->middleware('can:media.view')->name('content.index');
    Route::post('/desktop/content/local-links', [\App\Http\Controllers\LocalMediaLinkController::class,'repair'])->middleware(['can:media.edit','can:content.edit','throttle:5,1']);
    Route::post('/desktop/content/{record}/local-video', [\App\Http\Controllers\LocalMediaLinkController::class,'attach'])->middleware(['can:media.edit','can:content.edit']);
    Route::get('/desktop/content/{record}/imports/{snapshot}', [\App\Http\Controllers\ImportedContentController::class,'importVersion'])->middleware('can:media.view')->name('content.import-version');
    Route::get('/desktop/content/playlists', [\App\Http\Controllers\ImportedContentController::class,'playlists'])->middleware('can:media.view')->name('content.playlists');
    Route::get('/desktop/content/playlists/{collection}', [\App\Http\Controllers\ImportedContentController::class,'playlist'])->middleware('can:media.view')->name('content.playlist');
    Route::get('/desktop/content/{record}', [\App\Http\Controllers\ImportedContentController::class,'show'])->middleware('can:media.view')->name('content.show');
    Route::patch('/desktop/content/{record}', [\App\Http\Controllers\ImportedContentController::class,'update'])->middleware('can:content.edit')->name('content.update');
    Route::post('/desktop/content/classify', [\App\Http\Controllers\ContentClassificationController::class,'store'])->middleware(['can:media.edit', 'can:content.edit', 'throttle:30,1'])->name('content.classify');
    Route::post('/desktop/media/uploads', [MediaController::class,'uploadStart'])->middleware('can:media.upload')->name('media.uploads.start');
    Route::post('/desktop/media/uploads/{upload}/chunk', [MediaController::class,'uploadChunk'])->middleware('can:media.upload')->name('media.uploads.chunk');
    Route::post('/desktop/media/uploads/{upload}/finish', [MediaController::class,'uploadFinish'])->middleware('can:media.upload')->name('media.uploads.finish');
    Route::post('/desktop/media', [MediaController::class,'store'])->middleware('can:media.upload')->name('media.store');
    Route::patch('/desktop/media/{media}', [MediaController::class,'update'])->middleware('can:media.edit')->name('media.update');
    Route::get('/desktop/media/{media}/download', [MediaController::class,'download'])->middleware('can:media.view')->name('media.download');
    Route::get('/desktop/media/{media}/preview', [MediaController::class,'preview'])->middleware('can:media.view')->name('media.preview');
    Route::post('/desktop/shop', [\App\Http\Controllers\ShopController::class, 'store'])->middleware('can:shop.manage')->name('shop.store');
    Route::put('/desktop/settings', [SettingsController::class,'update'])->middleware('can:settings.manage')->name('settings');
    Route::post('/desktop/settings/roles', [SettingsController::class,'storeRole'])->middleware('can:users.manage')->name('settings.roles.store');
    Route::put('/desktop/settings/roles/{role}', [SettingsController::class,'updateRole'])->middleware('can:users.manage')->name('settings.roles.update');
});
