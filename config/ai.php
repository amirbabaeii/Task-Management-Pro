<?php

return [
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 4000),

    'context' => [
        'comment_limit' => (int) env('AI_CONTEXT_COMMENT_LIMIT', 20),
        'activity_limit' => (int) env('AI_CONTEXT_ACTIVITY_LIMIT', 30),
    ],

    'providers' => [
        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => (int) env('OPENAI_TIMEOUT', 60),
            'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
        ],
    ],
];
