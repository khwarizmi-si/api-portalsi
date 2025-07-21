<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Str;
use App\Models\User;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DirectMessageController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\AuthController;

// 🚀 PUBLIC ROUTES
Route::post('/register', function (Request $request) {
    $request->validate([
        'username'   => 'required|string|unique:users',
        'full_name'  => 'required|string',
        'email'      => 'required|email|unique:users',
        'password'   => 'required|min:6',
        'role'       => 'in:teacher,parent,student,other'
    ]);

    if ($request->role === 'dev') {
        return response()->json([
            'message' => 'Role "dev" tidak diizinkan untuk registrasi publik.'
        ], 403);
    }

    $user = User::create([
        'username'         => $request->username,
        'full_name'        => $request->full_name,
        'email'            => $request->email,
        'password_hash'    => bcrypt($request->password),
        'role'             => $request->role ?? 'student'
    ]);

    $user->sendEmailVerificationNotification();

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'message' => 'User registered successfully. Please verify your email.',
        'token' => $token,
        'user' => $user
    ], 201);
});

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

// 📩 Email Verification
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = \App\Models\User::findOrFail($id);

    // Validasi hash
    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Invalid verification link.'], 403);
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }

    return redirect('https://portalsi.com/verified-success');
})->middleware('signed')->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return response()->json(['message' => 'Link verifikasi dikirim ke email.']);
})->middleware(['auth:sanctum'])->name('verification.send');

// 🔑 Forgot Password
Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    $status = Password::sendResetLink($request->only('email'));
    if ($status === Password::PASSWORD_RESET) {
        return response()->json(['message' => 'Password berhasil direset.'], 200);
    } else {
        return response()->json(['message' => 'Reset gagal, token invalid atau expired.'], 400);
    }
});

Route::post('/reset-password', function (Request $request) {
    $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|confirmed',
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->password_hash = Hash::make($password);
            $user->save();
            event(new PasswordReset($user));
        }
    );

    return response()->json([
        'message' => $status === Password::PASSWORD_RESET
            ? 'Password berhasil direset.'
            : 'Reset gagal, token invalid atau expired.'
    ]);
});

Route::get('/profile/{id}', [ProfileController::class, 'show']);

// 🔐 PROTECTED ROUTES
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    });

    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'email_verified' => $request->user()->hasVerifiedEmail()
        ]);
    });

    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{id}', [PostController::class, 'show']);
    Route::get('/posts/{post_id}/comments', [CommentController::class, 'index']);
    Route::get('/posts/{post_id}/likes', [LikeController::class, 'index']);
    Route::get('/users/{id}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{id}/following', [FollowController::class, 'following']);
    Route::get('/stories/feed', [StoryController::class, 'feed']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/messages/conversation/{user_id}', [DirectMessageController::class, 'conversation']);
    Route::get('/explore', [PostController::class, 'explore']);
    Route::get('/users/search', [ProfileController::class, 'search']);
});

Route::middleware(['auth:sanctum', 'verified.api'])->group(function () {
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);

    Route::post('/posts/{post_id}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{id}', [CommentController::class, 'update']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

    Route::post('/posts/{post_id}/like', [LikeController::class, 'toggle']);

    Route::post('/follow/{id}', [FollowController::class, 'follow']);
    Route::delete('/unfollow/{id}', [FollowController::class, 'unfollow']);

    Route::post('/stories', [StoryController::class, 'store']);
    Route::delete('/stories/{id}', [StoryController::class, 'destroy']);
    Route::post('/stories/{id}/view', [StoryController::class, 'view']);

    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read/all', [NotificationController::class, 'markAllAsRead']);

    Route::post('/messages/send', [DirectMessageController::class, 'send']);
    Route::patch('/messages/{id}/read', [DirectMessageController::class, 'markAsRead']);

    Route::post('/upload', [MediaController::class, 'upload']);

    Route::put('/account/settings', [AccountController::class, 'update']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);
    Route::delete('/account/delete', [AccountController::class, 'destroy']);
});

Route::fallback(function () {
    return response()->json([
        'error' => 'Endpoint not found (404)'
    ], 404);
});
