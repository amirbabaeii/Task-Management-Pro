<?php

return [
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 4000),

    'execution' => [
        'enabled' => (bool) env('AI_AGENT_EXECUTION_ENABLED', true),
        'queue' => env('AI_AGENT_QUEUE', 'agents'),
        'queue_timeout' => (int) env('AI_AGENT_QUEUE_TIMEOUT', 120),
        'retry_backoff' => [30, 120, 300],
    ],

    'context' => [
        'comment_limit' => (int) env('AI_CONTEXT_COMMENT_LIMIT', 20),
        'activity_limit' => (int) env('AI_CONTEXT_ACTIVITY_LIMIT', 30),
    ],

    'retention' => [
        'raw_payload_days' => (int) env('AI_RUN_RAW_PAYLOAD_RETENTION_DAYS', 30),
        'run_days' => (int) env('AI_RUN_RETENTION_DAYS', 180),
    ],

    'providers' => [
        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => (int) env('OPENAI_TIMEOUT', 60),
            'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
        ],
    ],
];
