<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BrowserLiveController;
Route::middleware(['auth','can:content.publish'])->prefix('desktop/live-studio')->group(function(){
    Route::get('/server',[BrowserLiveController::class,'status']);
    Route::post('/server',[BrowserLiveController::class,'configure'])->middleware(['can:settings.manage','throttle:5,1']);
    Route::post('/events/{record}/browser',[BrowserLiveController::class,'start'])->middleware('throttle:5,1');
    Route::post('/events/{record}/podcast',[BrowserLiveController::class,'podcast'])->middleware(['can:content.edit','can:media.view','throttle:5,1']);
    Route::post('/sessions/{session}/heartbeat',[BrowserLiveController::class,'heartbeat'])->middleware('throttle:10,1');
    Route::delete('/sessions/{session}',[BrowserLiveController::class,'stop'])->middleware('throttle:10,1');
    Route::post('/events/{record}/disconnect',[BrowserLiveController::class,'disconnect'])->middleware(['can:live.manage','throttle:5,1']);
});
