<?php

return [

    // GROQ_API_KEY is the Laravel AI SDK name. STT_API_KEY still works if that one is unset.
    'groq_key' => env('GROQ_API_KEY', env('STT_API_KEY')),

    'model' => env('STT_MODEL', 'whisper-large-v3-turbo'),

    'timeout' => (int) env('STT_TIMEOUT', 120),

    'rate_limit_per_minute' => (int) env('MEMO_RATE_LIMIT', 30),

];
