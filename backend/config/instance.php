<?php

return [
    // Deployment-owned identity. Never derive this from a request or membership order.
    'organisation_code' => env('INSTANCE_ORGANISATION_CODE'),
];
