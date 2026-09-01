<?php

use App\Http\Controllers\Api\TrackingApiController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'TrackSystem API',
]));

//throttle:tracking means: apply the named rate limiter called tracking.

Route::middleware('throttle:tracking')->group(function () {
    Route::post('/agent/activate', [TrackingApiController::class, 'activateAgent']); // First-time agent activation, returns api_token
    Route::post('/devices/register', [TrackingApiController::class, 'registerDevice']);  // Register/update device for employee
    Route::post('/heartbeat', [TrackingApiController::class, 'heartbeat']);  // Agent alive ping (online status)
    Route::post('/sessions/start', [TrackingApiController::class, 'startSession']);  // Mark work session start/login
    Route::post('/sessions/end', [TrackingApiController::class, 'endSession']);  // Mark work session end/logout
    Route::post('/system-events', [TrackingApiController::class, 'storeSystemEvent']);  // Save startup/shutdown/lock/unlock events
    Route::post('/activity-logs', [TrackingApiController::class, 'storeActivityLog']);  // Save app/window activity logs
    Route::post('/website-logs', [TrackingApiController::class, 'storeWebsiteLog']);   // Save browser URL/domain activity logs
    Route::post('/sync/batch', [TrackingApiController::class, 'batchSync']);  // Bulk sync queued offline events
	
});



