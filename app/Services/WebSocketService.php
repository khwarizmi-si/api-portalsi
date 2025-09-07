<?php

namespace App\Services;

use App\Models\User;
use App\Events\UserOnlineStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebSocketService
{
  /**
   * Mark user as online
   */
  public function markUserOnline(User $user): void
  {
    $user->update([
      'is_online' => true,
      'last_activity' => now(),
    ]);

    // Cache user online status for quick access
    Cache::put("user_online_{$user->user_id}", true, 300); // 5 minutes

    // Broadcast online status to followers
    broadcast(new UserOnlineStatus($user, true));
  }

  /**
   * Mark user as offline
   */
  public function markUserOffline(User $user): void
  {
    $user->update([
      'is_online' => false,
      'last_seen' => now(),
    ]);

    // Remove from cache
    Cache::forget("user_online_{$user->user_id}");

    // Broadcast offline status to followers
    broadcast(new UserOnlineStatus($user, false));
  }

  /**
   * Update user activity
   */
  public function updateUserActivity(User $user): void
  {
    $user->update([
      'last_activity' => now(),
    ]);

    // Refresh cache
    Cache::put("user_online_{$user->user_id}", true, 300);
  }

  /**
   * Check if user is online
   */
  public function isUserOnline(int $userId): bool
  {
    return Cache::has("user_online_{$userId}") ||
      User::where('user_id', $userId)->where('is_online', true)->exists();
  }

  /**
   * Get online users count
   */
  public function getOnlineUsersCount(): int
  {
    return User::where('is_online', true)->count();
  }

  /**
   * Get online followers of a user
   */
  public function getOnlineFollowers(User $user): \Illuminate\Database\Eloquent\Collection
  {
    return $user->followers()
      ->where('is_online', true)
      ->get();
  }

  /**
   * Clean up stale online statuses
   */
  public function cleanupStaleStatuses(): void
  {
    $staleThreshold = now()->subMinutes(10);

    $staleUsers = User::where('is_online', true)
      ->where('last_activity', '<', $staleThreshold)
      ->get();

    foreach ($staleUsers as $user) {
      $this->markUserOffline($user);
    }
  }
}
