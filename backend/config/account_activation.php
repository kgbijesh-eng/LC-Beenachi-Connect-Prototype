<?php

return [
    'bootstrap_enabled' => env('ACCOUNT_ACTIVATION_BOOTSTRAP_ENABLED', false),
    'expires_minutes' => (int) env('ACCOUNT_ACTIVATION_EXPIRES_MINUTES', 30),
    'frontend_url' => env('ACCOUNT_ACTIVATION_FRONTEND_URL', 'http://localhost:3000'),
];
