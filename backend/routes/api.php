<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Club\ClubController;
use App\Http\Controllers\Api\V1\Member\MemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'success' => true,
        'message' => 'LC Beenachi Connect API is healthy.',
        'data' => [
            'service' => 'lc-beenachi-connect-backend',
            'status' => 'ok',
        ],
    ]));

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::middleware('club.permission:club.view,club')->group(function (): void {
            Route::get('/clubs/{club}', [ClubController::class, 'show']);
            Route::get('/clubs/{club}/me', [ClubController::class, 'me']);

            Route::get('/clubs/{club}/members', [MemberController::class, 'index']);
            Route::get('/clubs/{club}/members/{member}', [MemberController::class, 'show']);
        });
    });
});
