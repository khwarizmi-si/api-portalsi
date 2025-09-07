<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\WebSocketService;
use App\Models\User;

class WebSocketController extends Controller
{
  protected $webSocketService;

  public function __construct(WebSocketService $webSocketService)
  {
    $this->webSocketService = $webSocketService;
  }

  /**
   * Handle WebSocket connection authentication
   */
  public function authenticate(Request $request)
  {
    $user = Auth::user();

    if (!$user) {
      return response()->json(['error' => 'Unauthorized'], 401);
    }

    // Mark user as online
    $this->webSocketService->markUserOnline($user);

    return response()->json([
      'user_id' => $user->user_id,
      'username' => $user->username,
      'full_name' => $user->full_name,
      'profile_picture_url' => $user->profile_picture_url,
    ]);
  }

  /**
   * Handle WebSocket disconnection
   */
  public function disconnect(Request $request)
  {
    $user = Auth::user();

    if ($user) {
      $this->webSocketService->markUserOffline($user);
    }

    return response()->json(['message' => 'Disconnected successfully']);
  }

  /**
   * Get user's online status
   */
  public function getOnlineStatus(Request $request, $userId)
  {
    $isOnline = $this->webSocketService->isUserOnline($userId);
    $user = User::find($userId);

    if (!$user) {
      return response()->json(['error' => 'User not found'], 404);
    }

    return response()->json([
      'user_id' => $user->user_id,
      'is_online' => $isOnline,
      'last_seen' => $user->last_seen?->toISOString(),
    ]);
  }

  /**
   * Get online followers count
   */
  public function getOnlineFollowersCount(Request $request)
  {
    $user = Auth::user();
    $onlineFollowers = $this->webSocketService->getOnlineFollowers($user);

    return response()->json([
      'count' => $onlineFollowers->count(),
      'followers' => $onlineFollowers->map(function ($follower) {
        return [
          'user_id' => $follower->user_id,
          'username' => $follower->username,
          'full_name' => $follower->full_name,
          'profile_picture_url' => $follower->profile_picture_url,
        ];
      })
    ]);
  }

  /**
   * Get total online users count
   */
  public function getTotalOnlineCount()
  {
    $count = $this->webSocketService->getOnlineUsersCount();

    return response()->json(['count' => $count]);
  }

  /**
   * Update user activity (called periodically from frontend)
   */
  public function updateActivity(Request $request)
  {
    $user = Auth::user();

    if ($user) {
      $this->webSocketService->updateUserActivity($user);
    }

    return response()->json(['message' => 'Activity updated']);
  }
}
