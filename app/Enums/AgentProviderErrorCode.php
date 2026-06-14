<?php

namespace App\Enums;

enum AgentProviderErrorCode: string
{
    case InvalidCredentials = 'invalid_credentials';
    case RateLimited = 'rate_limited';
    case TimedOut = 'timed_out';
    case MalformedOutput = 'malformed_output';
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderError = 'provider_error';
}
