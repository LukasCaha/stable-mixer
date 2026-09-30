<?php

return [

    'api_key' => env('STT_API_KEY'),

    'base_url' => env('STT_BASE_URL', 'https://api.openai.com/v1'),

    'model' => env('STT_MODEL', 'whisper-1'),

    'timeout' => (int) env('STT_TIMEOUT', 120),

    'rate_limit_per_minute' => (int) env('MEMO_RATE_LIMIT', 30),

];
