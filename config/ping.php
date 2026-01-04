<?php

return [
    'stale_seconds' => env('PING_STALE_SECONDS', 60),
    'fail_threshold' => env('PING_FAIL_THRESHOLD', 3),
];
