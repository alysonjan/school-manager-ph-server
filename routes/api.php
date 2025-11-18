<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\auth\AuthenticationController;
use App\Http\Controllers\api\attendance\AttendanceController;
use App\Http\Controllers\api\messages\MessagesController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider within the "api" middleware group.
|
*/

// Public routes
Route::post('/login', [AuthenticationController::class, 'login']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::post('/logout', [AuthenticationController::class, 'logout']);

    // Attendance Routes
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'attendance']);
        Route::patch('/{recordId}/read', [AttendanceController::class, 'markAttendanceAsRead']);
        Route::patch('/read-all', [AttendanceController::class, 'markAllAttendanceAsRead']);
    });

    // Messages Routes
    Route::prefix('messages')->group(function () {
        Route::get('/', [MessagesController::class, 'getMessagesData']);
        Route::get('/unread-count', [MessagesController::class, 'getUnreadCount']);
        Route::put('/{recordId}/read', [MessagesController::class, 'markMessageAsRead']);
        Route::put('/read-all', [MessagesController::class, 'markAllMessagesAsRead']);
    });
});