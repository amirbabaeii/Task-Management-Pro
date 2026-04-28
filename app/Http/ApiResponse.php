<?php

namespace App\Http;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Builds the {type, message, data?} envelope used by every JSON endpoint.
 * Replaces the legacy ApiResource which conflated response shaping with
 * envelope building.
 */
class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = '',
        int $status = 200,
    ): JsonResponse {
        return self::respond('success', $message, $status, $data);
    }

    public static function error(
        string $message,
        int $status = 400,
        mixed $data = null,
    ): JsonResponse {
        return self::respond('error', $message, $status, $data);
    }

    private static function respond(
        string $type,
        string $message,
        int $status,
        mixed $data,
    ): JsonResponse {
        $payload = [
            'type' => $type,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = self::resolve($data);
        }

        return response()->json($payload, $status);
    }

    private static function resolve(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve();
        }

        if ($data instanceof Arrayable) {
            return $data->toArray();
        }

        return $data;
    }
}
