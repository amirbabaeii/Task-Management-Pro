<?php

return [
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 4000),

    'providers' => [
        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => (int) env('OPENAI_TIMEOUT', 60),
            'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
        ],
    ],
];
