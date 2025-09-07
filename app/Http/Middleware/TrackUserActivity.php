<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\WebSocketService;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
  protected $webSocketService;

  public function __construct(WebSocketService $webSocketService)
  {
    $this->webSocketService = $webSocketService;
  }

  /**
   * Handle an incoming request.
   */
  public function handle(Request $request, Closure $next): Response
  {
    $response = $next($request);

    // Track activity for authenticated users
    if ($request->user()) {
      $this->webSocketService->updateUserActivity($request->user());
    }

    return $response;
  }
}
