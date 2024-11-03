<?php

namespace App\Exceptions;

use App\Http\Resources\ApiResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiExceptionHandler extends ExceptionHandler
{
    protected $dontReport = [
        // ...
    ];

    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $request = app()->make('request');
        if ($request->is('api/*')) {
        
            $this->reportable(function (Throwable $e) {

                if ($this->shouldReport($e)) {

                    Log::error('Exception occurred', [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            });

            $this->renderable(function (ValidationException $e) {

                return new ApiResource(
                    ['errors' => $e->errors()],
                    $e->getMessage(),
                    422
                );
            });

            $this->renderable(function (AuthenticationException $e) {
                return new ApiResource(
                    null,
                    $e->getMessage(),
                    401
                );
            });
            if(app()->environment('production')) {
                $this->renderable(function (Throwable $e) {
                    $statusCode = $this->isHttpException($e) ? $e->getStatusCode() : 500;

                    return new ApiResource(
                    null,
                    $this->isHttpException($e) ? $e->getMessage() : 'An unexpected error occurred',
                    $statusCode
                    );
                });
            }
        }
    }
}
