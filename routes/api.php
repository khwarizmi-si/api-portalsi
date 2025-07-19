<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DirectMessageController;
use App\Http\Controllers\StoryViewController;
use App\Http\Controllers\AccountController;


/*
|--------------------------------------------------------------------------
| 🚀 PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

// 🔐 Register
Route::post('/register', function (Request $request) {
    $request->validate([
        'username'   => 'required|string|unique:users',
        'full_name'  => 'required|string',
        'email'      => 'required|email|unique:users',
        'password'   => 'required|min:6'
    ]);

    $user = User::create([
        'username'         => $request->username,
        'full_name'        => $request->full_name,
        'email'            => $request->email,
        'password_hash'    => bcrypt($request->password),
    ]);

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'message' => 'User registered successfully',
        'token' => $token,
        'user' => $user
    ], 201);
});

// 🔐 Login
Route::post('/login', function (Request $request) {
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required'
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password_hash)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'token' => $token,
        'user' => $user
    ]);
});

// 👤 Public Profile (no auth)
Route::get('/profile/{id}', [ProfileController::class, 'show']);

/*
|--------------------------------------------------------------------------
| 🔐 PROTECTED ROUTES (auth:sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // 🔐 Logout
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    });

    // 👤 Get authenticated user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // 📮 POSTS
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{id}', [PostController::class, 'show']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);

    // 💬 COMMENTS
    Route::get('/posts/{post_id}/comments', [CommentController::class, 'index']);
    Route::post('/posts/{post_id}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{id}', [CommentController::class, 'update']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

    // ❤️ LIKES
    Route::post('/posts/{post_id}/like', [LikeController::class, 'toggle']);
    Route::get('/posts/{post_id}/likes', [LikeController::class, 'index']);

    // 🤝 FOLLOWS
    Route::post('/follow/{id}', [FollowController::class, 'follow']);
    Route::delete('/unfollow/{id}', [FollowController::class, 'unfollow']);
    Route::get('/users/{id}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{id}/following', [FollowController::class, 'following']);

    // 📷 STORIES
    Route::post('/stories', [StoryController::class, 'store']);
    Route::get('/stories/feed', [StoryController::class, 'feed']);
    Route::delete('/stories/{id}', [StoryController::class, 'destroy']);
    Route::post('/stories/{id}/view', [StoryController::class, 'view']); // ✅ Tambahan: Story View

    // 🔔 NOTIFICATIONS
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read/all', [NotificationController::class, 'markAllAsRead']);

    // 📫 DIRECT MESSAGES
    Route::post('/messages/send', [DirectMessageController::class, 'send']);
    Route::get('/messages/conversation/{user_id}', [DirectMessageController::class, 'conversation']);
    Route::patch('/messages/{id}/read', [DirectMessageController::class, 'markAsRead']);

    // 📸 MEDIA UPLOAD
    Route::post('/upload', [App\Http\Controllers\MediaController::class, 'upload']);

    // 🔎 EXPLORE
    Route::get('/explore', [PostController::class, 'explore']);

    // 👤 ACCOUNT SETTINGS
    Route::middleware('auth:sanctum')->group(function () {
    Route::put('/account/settings', [AccountController::class, 'update']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);
    Route::delete('/account/delete', [AccountController::class, 'destroy']);

    // 🔎 SEARCH
    Route::get('/users/search', [ProfileController::class, 'search']);
    });
    
});




/*
|--------------------------------------------------------------------------
| 🛑 FALLBACK - 404 Not Found
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return response()->json([
        'error' => 'Endpoint not found (404)'
    ], 404);
});
