<?php

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
});
