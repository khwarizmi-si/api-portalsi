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
use App\Http\Controllers\{
    PostController,
    CommentController,
    LikeController,
    FollowController,
    ProfileController,
    StoryController,
    NotificationController,
    DirectMessageController,
    AccountController,
    MediaController,
    AuthController,
    GroupController,
    GroupMessageController,
    AnnouncementController,
    PortfolioController
};

// 🚀 PUBLIC ROUTES
// 🚀 PUBLIC ROUTES
Route::post('/register', function (Request $request) {
    $request->validate([
        'username' => [
            'required',
            'string',
            'unique:users',
            'regex:/^[a-zA-Z0-9._]+$/'
        ],
        'full_name' => 'required|string',
        'email'     => 'required|email|unique:users',
        'password'  => 'required|min:6',
        'role'      => 'in:teacher,parent,student,other'
    ], [
        'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, dan underscore tanpa spasi atau simbol lain.'
    ]);

    if ($request->role === 'dev') {
        return response()->json([
            'message' => 'Role "dev" tidak diizinkan untuk registrasi publik.'
        ], 403);
    }

    $user = User::create([
        'username'            => strtolower($request->username), // 👈 Lowercase username
        'full_name'           => $request->full_name,
        'email'               => strtolower($request->email),     // 👈 Lowercase email
        'password_hash'       => bcrypt($request->password),
        'role'                => $request->role ?? 'student',
        'profile_picture_url' => 'https://api.portalsi.com/storage/default-profile.png'
    ]);

    $user->sendEmailVerificationNotification();

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'message' => 'User registered successfully. Please verify your email.',
        'token'   => $token,
        'user'    => $user
    ], 201);
});

Route::post('/login', function (Request $request) {
    $request->validate([
        'login'    => 'required|string', // bisa username atau email
        'password' => 'required|string',
    ]);

    // Coba cari berdasarkan email dulu, jika tidak ketemu cari sebagai username
    $user = User::where('email', $request->login)
                ->orWhere('username', $request->login)
                ->first();

    if (! $user || ! Hash::check($request->password, $user->password_hash)) {
        throw ValidationException::withMessages([
            'login' => ['The provided credentials are incorrect.'],
        ]);
    }

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'token'   => $token,
        'user'    => $user
    ]);
});

// 📩 Email Verification
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = \App\Models\User::findOrFail($id);
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
    return response()->json([
        'message' => $status === Password::RESET_LINK_SENT
            ? 'Link reset dikirim ke email.'
            : 'Gagal mengirim reset link.'
    ]);
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
        'message' => match ($status) {
            Password::PASSWORD_RESET => 'Password berhasil direset.',
            Password::INVALID_TOKEN => 'Token tidak valid atau sudah kadaluarsa.',
            Password::INVALID_USER => 'Email tidak ditemukan.',
            Password::RESET_THROTTLED => 'Terlalu sering mencoba. Coba beberapa saat lagi.',
            default => 'Reset password gagal.'
        }
    ], $status === Password::PASSWORD_RESET ? 200 : 400);
});

Route::get('/profile/{username}', [ProfileController::class, 'show']);

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

    // Public Feed
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{id}', [PostController::class, 'show']);
    Route::get('/posts/{post_id}/likes', [LikeController::class, 'index']);
    Route::get('/users/{id}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{id}/following', [FollowController::class, 'following']);
    Route::get('/stories/feed', [StoryController::class, 'feed']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/messages/conversation/{user_id}', [DirectMessageController::class, 'conversation']);
    Route::get('/explore', [PostController::class, 'explore']);
    Route::get('/users/search', [ProfileController::class, 'search']);

    // 🔐 Only for verified users
    Route::middleware('verified.api')->group(function () {
        // Post CRUD
        Route::post('/posts', [PostController::class, 'store']);
        Route::post('/posts/{id}/update', [PostController::class, 'update']);
        Route::delete('/posts/{id}', [PostController::class, 'destroy']);

        // Comments
        Route::get('/posts/{post_id}/comments', [CommentController::class, 'getCommentsByPost']);
        Route::post('/posts/{post_id}/comments', [CommentController::class, 'store']);
        Route::put('/comments/{id}', [CommentController::class, 'update']);
        Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

        // Likes & Follow
        Route::post('/posts/{post_id}/like', [LikeController::class, 'toggle']);
        Route::post('/follow/{id}', [FollowController::class, 'follow']);
        Route::delete('/unfollow/{id}', [FollowController::class, 'unfollow']);

        // Story
        Route::post('/stories', [StoryController::class, 'store']);
        Route::delete('/stories/{id}', [StoryController::class, 'destroy']);
        Route::post('/stories/{id}/view', [StoryController::class, 'view']);

        // Notifications
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/notifications/read/all', [NotificationController::class, 'markAllAsRead']);

        // DM
        Route::post('/messages/send', [DirectMessageController::class, 'send']);
        Route::patch('/messages/{id}/read', [DirectMessageController::class, 'markAsRead']);
        Route::delete('/messages/{id}', [DirectMessageController::class, 'destroy']);
        Route::get('/messages/chat-list', [DirectMessageController::class, 'chatList']);

        // Account
        Route::post('/account/settings', [AccountController::class, 'update']);
        Route::put('/account/password', [AccountController::class, 'updatePassword']);
        Route::delete('/account/delete', [AccountController::class, 'destroy']);

        // Groups CRUD
        Route::prefix('groups')->group(function () {
            Route::post('/', [GroupController::class, 'store']);
            Route::post('{group}/join', [GroupController::class, 'join']);
            Route::post('{group}/leave', [GroupController::class, 'leave']);
            Route::get('{group}', [GroupController::class, 'show']);
            Route::match(['put', 'post'], '{group}', [GroupController::class, 'update']);
            Route::delete('{group}', [GroupController::class, 'destroy']); // ✅ tambahkan ini
        });
        

        // Group Messages
        Route::prefix('groups/{group}')->group(function () {
            Route::post('/messages', [GroupMessageController::class, 'store']);
            Route::get('/messages', [GroupMessageController::class, 'index']);
            Route::delete('/messages/{message}', [GroupMessageController::class, 'destroy']);
            Route::post('/messages/{message}/pin', [GroupMessageController::class, 'togglePin']);
        });

        // Announcements
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/announcements', [AnnouncementController::class, 'index']);
        
            // Khusus user centang biru
            Route::middleware('onlyVerified')->group(function () {
                Route::post('/announcements', [AnnouncementController::class, 'store']);
                Route::post('/announcements/{announcement}', [AnnouncementController::class, 'update']);
                Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
            });
        });

        // Group Members
        Route::prefix('groups/{group}')->group(function () {
            Route::post('/members', [GroupController::class, 'addMember']); // 🔹 Tambah anggota dari username/email
            Route::get('/members', [GroupController::class, 'listMembers']); // 🔹 Lihat daftar anggota
            Route::post('/members/{user}/promote', [GroupController::class, 'promoteToAdmin']); // 🔹 Jadikan admin
            Route::post('/members/{user}/demote', [GroupController::class, 'demoteToMember']); // 🔹 Turunkan jadi member
            Route::delete('/members/{user}', [GroupController::class, 'removeMember']); // 🔹 Kick anggota
            Route::post('/members/{user}/mute', [GroupController::class, 'muteMember']); // 🔹 Mute
            Route::post('/members/{user}/unmute', [GroupController::class, 'unmuteMember']); // 🔹 Unmute
        });
        
        // Portfolios
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/portfolios', [PortfolioController::class, 'index']);
            Route::post('/portfolios', [PortfolioController::class, 'store']); // ⬅️ Buat baru
            Route::post('/portfolios/{portfolio}', [PortfolioController::class, 'update']); // ⬅️ Edit (pakai POST karena multipart)
            Route::delete('/portfolios/{portfolio}', [PortfolioController::class, 'destroy']);
        });

        // Acc atau tidak Followers ketika akun private
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/followers/{follower_id}/accept', [FollowController::class, 'acceptFollowRequest']);
            Route::post('/followers/{follower_id}/reject', [FollowController::class, 'rejectFollowRequest']);
            Route::get('/followers/pending', [FollowController::class, 'pendingFollowRequests']);
        });
        
        // GET Private atau tidak
        Route::get('/account/is-private', [AccountController::class, 'checkPrivateStatus'])
        ->middleware('auth:sanctum');


    });
});

// Fallback
Route::fallback(function () {
    return response()->json([
        'error' => 'Endpoint not found (404)'
    ], 404);
});
