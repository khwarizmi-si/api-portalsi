<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Override the default render to return JSON for API routes
     */
    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*')) {
            // Handle validation error (422)
            if ($exception instanceof ValidationException) {
                return response()->json([
                    'message' => 'Validasi gagal, silakan coba kembali.',
                    'errors' => $exception->errors(),
                ], 422);
            }

            // Handle route not found (404)
            if ($exception instanceof NotFoundHttpException) {
                return response()->json([
                    'error' => 'Endpoint not found',
                ], 404);
            }

            // Handle method not allowed (405)
            if ($exception instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'error' => 'HTTP method not allowed',
                ], 405);
            }

            // Handle unauthenticated / invalid token (401, not 500)
            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'error' => 'Unauthenticated',
                ], 401);
            }

            // Generic HTTP exception (e.g., 403, 401, etc)
            if ($exception instanceof HttpException) {
                return response()->json([
                    'error' => $exception->getMessage(),
                ], $exception->getStatusCode());
            }

            // All other unhandled errors (500)
            return response()->json([
                'error' => 'Server error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return parent::render($request, $exception);
    }
}
