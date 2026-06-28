<?php
use Jenssegers\Agent\Agent;
use App\Models\LoginHistory;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use App\Http\Controllers\UserSuggestionController;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Group;
use App\Http\Controllers\{
    PostController,
    CommentController,
    LikeController,
    FollowController,
    ProfileController,
    StoryController,
    StoryViewController,
    NotificationController,
    DirectMessageController,
    AccountController,
    MediaController,
    AuthController,
    GroupController,
    GroupMessageController,
    AnnouncementController,
    PortfolioController,
    BookmarkController,
    LoginHistoryController,
    WebSocketController,
    BulkRegisterController,
};

// ═══════════════════════════════════════════
// PUBLIC ROUTES
// ═══════════════════════════════════════════

$sendVerificationEmail = function (User $user, string $context): array {
    try {
        $user->sendEmailVerificationNotification();

        return [
            'status' => 'sent',
            'message' => 'Link verifikasi dikirim ke email.',
        ];
    } catch (\Throwable $e) {
        Log::error('Failed to send verification email: ' . $e->getMessage(), [
            'context' => $context,
            'user_id' => $user->user_id,
            'email' => $user->email,
        ]);

        return [
            'status' => 'failed',
            'message' => 'Email verifikasi gagal dikirim. Coba kirim ulang nanti atau hubungi admin.',
        ];
    }
};

Route::post('/register', function (Request $request) use ($sendVerificationEmail) {
    $validator = Validator::make($request->all(), [
        'username' => ['required', 'string', 'unique:users,username', 'regex:/^[a-zA-Z0-9._]+$/'],
        'full_name' => 'required|string',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:6',
        'role' => 'in:teacher,parent,student,other'
    ], [
        'username.required' => 'Username wajib diisi.',
        'username.unique' => 'Username sudah digunakan.',
        'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, underscore.',
        'full_name.required' => 'Nama lengkap wajib diisi.',
        'email.required' => 'Email wajib diisi.',
        'email.email' => 'Format email tidak valid.',
        'email.unique' => 'Email sudah terdaftar.',
        'password.required' => 'Password wajib diisi.',
        'password.min' => 'Password minimal 6 karakter.',
        'role.in' => 'Role tidak valid.'
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
    }

    if ($request->role === 'dev') {
        return response()->json(['message' => 'Role "dev" tidak diizinkan untuk registrasi publik.'], 403);
    }

    $user = User::create([
        'username' => strtolower($request->username),
        'full_name' => $request->full_name,
        'email' => strtolower($request->email),
        'password_hash' => bcrypt($request->password),
        'role' => $request->role ?? 'student',
        'profile_picture_url' => null,
        'banner_url' => null,
    ]);

    $verificationEmail = $sendVerificationEmail($user, 'register');
    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'message' => $verificationEmail['status'] === 'sent'
            ? 'User registered successfully. Please verify your email.'
            : 'Akun berhasil dibuat, tapi email verifikasi gagal dikirim. Coba kirim ulang nanti atau hubungi admin.',
        'verification_email_status' => $verificationEmail['status'],
        'token' => $token,
        'user' => $user
    ], 201);
});

Route::post('/login-check', function (Request $request) {
    $request->validate(['username' => 'required|string', 'password' => 'required|string']);
    $user = User::where('username', strtolower($request->username))->first();

    if (!$user || !Hash::check($request->password, $user->password_hash)) {
        return response()->json(['success' => false, 'message' => 'Username atau password salah'], 401);
    }

    $groups = $user->groups()->select('groups.id as group_id', 'groups.name')->get();

    return response()->json([
        'success' => true,
        'user' => ['id' => $user->user_id, 'username' => $user->username, 'full_name' => $user->full_name, 'role' => $user->role],
        'groups' => $groups,
    ]);
});

Route::post('/register-teachers', function (Request $request) {
    $teachers = $request->input('teachers');
    if (!$teachers || !is_array($teachers)) {
        return response()->json(['message' => 'Parameter teachers harus berupa array.'], 400);
    }
    $createdUsers = [];
    foreach ($teachers as $data) {
        if (empty($data['username']) || empty($data['password'])) continue;
        if (User::where('username', strtolower($data['username']))->exists()) continue;
        $user = User::create([
            'username' => strtolower($data['username']),
            'password_hash' => bcrypt($data['password']),
            'role' => 'teacher',
            'full_name' => $data['full_name'] ?? null,
            'email' => $data['email'] ?? null,
            'profile_picture_url' => null,
            'banner_url' => null
        ]);
        $user->markEmailAsVerified();
        $user->groups()->syncWithoutDetaching([
            1 => ['role' => 'member', 'is_muted' => 0],
            2 => ['role' => 'member', 'is_muted' => 0],
            3 => ['role' => 'member', 'is_muted' => 0],
            4 => ['role' => 'member', 'is_muted' => 0],
            5 => ['role' => 'member', 'is_muted' => 0],
            6 => ['role' => 'member', 'is_muted' => 0],
        ]);
        $createdUsers[] = $user;
    }
    return response()->json(['message' => 'Teachers registered & auto joined to groups 1–6 successfully.', 'count' => count($createdUsers), 'users' => $createdUsers], 201);
});

Route::post('/register-parent', function (Request $request) {
    $request->validate([
        'username' => ['required', 'string', 'unique:users', 'regex:/^[a-zA-Z0-9._]+$/'],
        'password' => 'required|min:1',
    ], ['username.regex' => 'Username hanya boleh berisi huruf, angka, titik, underscore.']);

    $user = User::create([
        'username' => strtolower($request->username),
        'password_hash' => bcrypt($request->password),
        'role' => 'parent',
        'full_name' => null,
        'email' => null,
        'profile_picture_url' => null,
        'banner_url' => null
    ]);
    $user->markEmailAsVerified();
    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json(['message' => 'Parent registered successfully (auto verified).', 'token' => $token, 'user' => $user], 201);
});

Route::post('/login', function (Request $request) use ($sendVerificationEmail) {
    $request->validate(['login' => 'required|string', 'password' => 'required|string']);
    $login = strtolower(trim($request->login));
    $user = User::where(function ($query) use ($login) {
        $query->whereRaw('LOWER(TRIM(email)) = ?', [$login])->orWhere('username', $login);
    })->first();

    if (!$user || !Hash::check($request->password, $user->password_hash)) {
        return response()->json(['code' => 2001, 'message' => 'Username/email atau password salah!'], 401);
    }

    if (!$user->hasVerifiedEmail()) {
        $cooldown = (int) config('auth.verification_resend_cooldown', 60);
        $cacheKey = 'email_verification_login_resend:' . $user->getKey();
        $now = now()->timestamp;
        $nextAt = (int) Cache::get($cacheKey, 0);

        if (empty($user->email)) {
            return response()->json(['code' => 2002, 'message' => 'Akun Anda belum memiliki email terikat.', 'verification_email_status' => 'missing_email'], 403);
        }
        if ($nextAt > $now) {
            $remaining = $nextAt - $now;
            return response()->json(['code' => 2002, 'message' => "Akun belum diverifikasi. Login lagi dalam {$remaining} detik untuk kirim ulang.", 'verification_email_status' => 'cooldown', 'resend_cooldown_seconds' => $remaining], 403);
        }
        $verificationEmail = $sendVerificationEmail($user, 'login');
        if ($verificationEmail['status'] !== 'sent') {
            return response()->json(['code' => 2002, 'message' => 'Gagal kirim ulang email verifikasi.', 'verification_email_status' => 'failed'], 403);
        }
        Cache::put($cacheKey, $now + $cooldown, $cooldown);

        return response()->json(['code' => 2002, 'message' => "Link verifikasi baru dikirim. Cek email atau login lagi dalam {$cooldown} detik.", 'verification_email_status' => 'sent', 'resend_cooldown_seconds' => $cooldown], 403);
    }

    $tokenResult = $user->createToken('api-token');
    $plainTextToken = $tokenResult->plainTextToken;
    $tokenId = PersonalAccessToken::where('token', hash('sha256', explode('|', $plainTextToken)[1]))->first()?->id;

    try {
        $agent = new Agent();
        LoginHistory::create([
            'user_id' => $user->user_id,
            'token_id' => $tokenId,
            'ip_address' => $request->ip() ?? 'unknown',
            'user_agent' => $request->header('User-Agent') ?? 'unknown',
            'device' => $agent->device() ?? 'unknown',
            'browser' => $agent->browser() ?? 'unknown',
            'platform' => $agent->platform() ?? 'unknown',
            'login_at' => now(),
        ]);
    } catch (\Exception $e) {
        Log::error('LoginHistory insert failed: ' . $e->getMessage());
    }

    return response()->json(['code' => 1001, 'message' => 'Login successful', 'token' => $plainTextToken, 'user' => $user], 200);
});

Route::post('/fcm/register', [App\Http\Controllers\FcmController::class, 'register']);

Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = User::findOrFail($id);
    if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Invalid verification link.'], 403);
    }
    if (!$user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }
    return redirect('https://portalsi.com/verified-success');
})->middleware('signed')->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) use ($sendVerificationEmail) {
    $verificationEmail = $sendVerificationEmail($request->user(), 'authenticated_resend');

    return response()->json([
        'message' => $verificationEmail['message'],
        'verification_email_status' => $verificationEmail['status'],
    ], $verificationEmail['status'] === 'sent' ? 200 : 500);
})->middleware(['auth:sanctum'])->name('verification.send');

Route::post('/email/resend-verification', function (Request $request) use ($sendVerificationEmail) {
    $request->validate([
        'login' => 'required|string',
        'password' => 'required|string',
    ]);

    $login = strtolower(trim($request->input('login')));
    $user = User::where(function ($query) use ($login) {
        $query->whereRaw('LOWER(TRIM(email)) = ?', [$login])
            ->orWhere('username', $login);
    })->first();

    if (!$user || !Hash::check($request->input('password'), $user->password_hash)) {
        return response()->json(['message' => 'Username/email atau password salah!'], 401);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json([
            'message' => 'Email akun ini sudah diverifikasi.',
            'verification_email_status' => 'already_verified',
        ]);
    }

    if (empty($user->email)) {
        return response()->json([
            'message' => 'Akun Anda belum memiliki email terikat.',
            'verification_email_status' => 'missing_email',
        ], 422);
    }

    $verificationEmail = $sendVerificationEmail($user, 'public_resend');

    return response()->json([
        'message' => $verificationEmail['message'],
        'verification_email_status' => $verificationEmail['status'],
    ], $verificationEmail['status'] === 'sent' ? 200 : 500);
});

Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    $email = strtolower(trim($request->input('email')));
    $matched = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->limit(2)->get(['user_id', 'email']);
    if ($matched->isEmpty()) return response()->json(['message' => 'Email tidak ditemukan.', 'status' => 'invalid_user'], 404);
    if ($matched->count() > 1) return response()->json(['message' => 'Duplikat email. Hubungi admin.', 'status' => 'duplicate_email'], 409);

    try {
        $status = Password::sendResetLink(['email' => $matched->first()->email]);
    } catch (\Throwable $e) {
        Log::error('Failed to send password reset email: ' . $e->getMessage(), [
            'email' => $matched->first()->email,
        ]);

        return response()->json(['message' => 'Gagal kirim reset link. Coba lagi nanti atau hubungi admin.', 'status' => 'failed'], 500);
    }

    return match ($status) {
        Password::RESET_LINK_SENT => response()->json(['message' => 'Link reset dikirim.', 'status' => 'sent']),
        Password::RESET_THROTTLED => response()->json(['message' => 'Tunggu 60 detik.', 'status' => 'throttled'], 429),
        default => response()->json(['message' => 'Gagal kirim reset link.', 'status' => 'failed'], 500),
    };
});

Route::post('/reset-password', function (Request $request) {
    $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|confirmed']);
    $email = strtolower(trim($request->input('email')));
    $status = Password::reset(
        ['email' => fn($q) => $q->whereRaw('LOWER(TRIM(email)) = ?', [$email]), 'password' => $request->password, 'password_confirmation' => $request->password_confirmation, 'token' => $request->token],
        function ($user, $password) {
            $user->password_hash = Hash::make($password);
            $user->save();
            event(new PasswordReset($user));
        }
    );
    return response()->json(['message' => match ($status) {
        Password::PASSWORD_RESET => 'Password berhasil direset.',
        Password::INVALID_TOKEN => 'Token tidak valid.',
        Password::INVALID_USER => 'Email tidak ditemukan.',
        Password::RESET_THROTTLED => 'Terlalu sering. Coba lagi nanti.',
        default => 'Reset gagal.'
    }], $status === Password::PASSWORD_RESET ? 200 : 400);
});

Route::post('/bind-email', function (Request $request) use ($sendVerificationEmail) {
    $request->validate(['email' => 'required|email|unique:users,email']);
    $user = $request->user();
    if (!empty($user->email)) return response()->json(['message' => 'Email sudah terikat.'], 400);
    $user->email = strtolower($request->email);
    $user->email_verified_at = null;
    $user->save();
    $verificationEmail = $sendVerificationEmail($user, 'bind_email');

    return response()->json([
        'message' => $verificationEmail['status'] === 'sent'
            ? 'Email berhasil ditambahkan. Silakan verifikasi.'
            : $verificationEmail['message'],
        'verification_email_status' => $verificationEmail['status'],
    ], $verificationEmail['status'] === 'sent' ? 200 : 500);
})->middleware(['auth:sanctum']);

Route::get('/profile/{username}', [ProfileController::class, 'show']);

// ═══════════════════════════════════════════
// PROTECTED ROUTES (auth:sanctum)
// ═══════════════════════════════════════════

Route::middleware(['auth:sanctum'])->group(function () {
    Broadcast::routes(['middleware' => ['auth:sanctum']]);

    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    });

    Route::get('/user', [ProfileController::class, 'me']);
    Route::get('/account/is-private', [AccountController::class, 'checkPrivateStatus']);
    Route::get('/mutuals', [ProfileController::class, 'mutuals']);

    // Feed
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{id}', [PostController::class, 'show']);
    Route::get('/posts/{post_id}/likes', [LikeController::class, 'index']);
    Route::get('/posts/{post_id}/comments', [CommentController::class, 'getCommentsByPost']);

    Route::get('/users/{id}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{id}/following', [FollowController::class, 'following']);
    Route::get('/users/search', [ProfileController::class, 'search']);

    Route::get('/stories/feed', [StoryController::class, 'feed']);
    Route::get('/stories/feed/user/{userId}', [StoryController::class, 'feedUser']);
    Route::get('/stories/my', [StoryController::class, 'myStories']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read/all', [NotificationController::class, 'markAllAsRead']);

    Route::get('/explore', [PostController::class, 'explore']);
    Route::get('/circle-avatar/{id}', [PostController::class, 'circleAvatar']);
    Route::get('/clips/{id}', [PostController::class, 'clips']);

    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/pinned', [AnnouncementController::class, 'pinned']);

    // DMs
    Route::get('/messages/conversation/{user_id}', [DirectMessageController::class, 'conversation']);
    Route::post('/messages/send', [DirectMessageController::class, 'send']);
    Route::patch('/messages/{id}/read', [DirectMessageController::class, 'markAsRead']);
    Route::patch('/messages/user/{user_id}/read', [DirectMessageController::class, 'markAsReadByUser']);
    Route::delete('/messages/{id}', [DirectMessageController::class, 'destroy']);
    Route::get('/messages/chat-list', [DirectMessageController::class, 'chatList']);
    Route::get('/messages/unread/{user_id}', [DirectMessageController::class, 'unreadConversation']);
    Route::get('/messages/conversation-from/{user_id}', [DirectMessageController::class, 'conversationFromUser']);
    Route::get('/messages/channels', [DirectMessageController::class, 'channels']);

    // Login History
    Route::get('/login-histories', [LoginHistoryController::class, 'index']);
    Route::delete('/login-histories/{id}', [LoginHistoryController::class, 'destroy']);

    // Bookmarks
    Route::get('/bookmarks', [BookmarkController::class, 'index']);
    Route::post('/bookmarks/{postId}', [BookmarkController::class, 'store']);
    Route::delete('/bookmarks/{postId}', [BookmarkController::class, 'destroy']);

    // Follow requests
    Route::post('/followers/{follower_id}/accept', [FollowController::class, 'acceptFollowRequest']);
    Route::post('/followers/{follower_id}/reject', [FollowController::class, 'rejectFollowRequest']);
    Route::get('/followers/pending', [FollowController::class, 'pendingFollowRequests']);

    // Special groups (parent/teacher only)
    Route::get('/special-groups', function (Request $request) {
        $user = $request->user();
        if (!in_array($user->role, ['parent', 'teacher'])) {
            return response()->json(['error' => 'Hanya parent & teacher.'], 403);
        }
        $groups = $user->groups()->whereIn('groups.id', [1,2,3,4,5,6])->select('groups.id','groups.name','groups.description','groups.avatar_url')->get();
        return response()->json($groups->map(function ($g) use ($user) {
            $unread = DB::table('group_messages')
                ->leftJoin('group_message_reads', fn($j) => $j->on('group_messages.id','=','group_message_reads.group_message_id')->where('group_message_reads.user_id', $user->user_id))
                ->where('group_messages.group_id', $g->id)->whereNull('group_message_reads.id')->count();
            return ['id'=>$g->id,'name'=>$g->name,'description'=>$g->description,'avatar_url'=>$g->avatar_url,'unread_message_count'=>(string)$unread];
        }));
    });

    // WebSocket
    Route::post('/websocket/authenticate', [WebSocketController::class, 'authenticate']);
    Route::post('/websocket/disconnect', [WebSocketController::class, 'disconnect']);
    Route::get('/websocket/online-status/{userId}', [WebSocketController::class, 'getOnlineStatus']);
    Route::get('/websocket/online-followers', [WebSocketController::class, 'getOnlineFollowersCount']);
    Route::get('/websocket/online-count', [WebSocketController::class, 'getTotalOnlineCount']);
    Route::post('/websocket/update-activity', [WebSocketController::class, 'updateActivity']);

    // ── VERIFIED USERS ONLY ──
    Route::middleware('verified.api')->group(function () {
        Route::post('/posts', [PostController::class, 'store']);
        Route::post('/posts/{id}/update', [PostController::class, 'update']);
        Route::delete('/posts/{id}', [PostController::class, 'destroy']);
        Route::post('/posts/{post_id}/like', [LikeController::class, 'toggle']);
        Route::post('/posts/{post_id}/comments', [CommentController::class, 'store']);

        Route::put('/comments/{id}', [CommentController::class, 'update']);
        Route::delete('/comments/{id}', [CommentController::class, 'destroy']);
        Route::post('/comments/{comment_id}/like', [CommentController::class, 'like']);
        Route::delete('/comments/{comment_id}/like', [CommentController::class, 'unlike']);

        Route::post('/follow/{id}', [FollowController::class, 'follow']);
        Route::delete('/unfollow/{id}', [FollowController::class, 'unfollow']);

        Route::post('/stories', [StoryController::class, 'store']);
        Route::delete('/stories/{id}', [StoryController::class, 'destroy']);
        Route::post('/stories/{id}/view', [StoryController::class, 'view']);
        Route::get('/stories/{id}/viewers', [StoryViewController::class, 'viewers']);
        Route::get('/stories/user/{userId}', [StoryController::class, 'getByUser']);
        Route::get('/stories/my/archived', [StoryController::class, 'myArchivedStories']);

        Route::get('/suggestions', [UserSuggestionController::class, 'index']);

        Route::post('/account/settings', [AccountController::class, 'update']);
        Route::put('/account/password', [AccountController::class, 'updatePassword']);
        Route::delete('/account/delete', [AccountController::class, 'destroy']);

        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::post('/announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

        Route::post('/groups', [GroupController::class, 'store']);
        Route::post('groups/{group}/join', [GroupController::class, 'join']);
        Route::post('groups/{group}/leave', [GroupController::class, 'leave']);
        Route::get('groups/{group}', [GroupController::class, 'show']);
        Route::match(['put','post'], 'groups/{group}', [GroupController::class, 'update']);
        Route::delete('groups/{group}', [GroupController::class, 'destroy']);
        Route::get('groups/{group}/role', [GroupController::class, 'checkRole']);

        Route::post('groups/{group}/messages', [GroupMessageController::class, 'store']);
        Route::get('groups/{group}/messages', [GroupMessageController::class, 'index']);
        Route::delete('groups/{group}/messages/{message}', [GroupMessageController::class, 'destroy']);
        Route::post('groups/{group}/messages/{message}/pin', [GroupMessageController::class, 'togglePin']);
        Route::post('groups/{group}/messages/{message}/read', [GroupMessageController::class, 'markAsRead']);
        Route::get('groups/{group}/messages/{message}/read-info', [GroupMessageController::class, 'readInfo']);
        Route::get('groups/{group}/messages/unread', [GroupMessageController::class, 'unreadMessages']);

        Route::post('groups/{group}/members', [GroupController::class, 'addMember']);
        Route::get('groups/{group}/members', [GroupController::class, 'listMembers']);
        Route::post('groups/{group}/members/{user}/promote', [GroupController::class, 'promoteToAdmin']);
        Route::post('groups/{group}/members/{user}/demote', [GroupController::class, 'demoteToMember']);
        Route::delete('groups/{group}/members/{user}', [GroupController::class, 'removeMember']);
        Route::post('groups/{group}/members/{user}/mute', [GroupController::class, 'muteMember']);
        Route::post('groups/{group}/members/{user}/unmute', [GroupController::class, 'unmuteMember']);

        Route::get('/portfolios', [PortfolioController::class, 'index']);
        Route::post('/portfolios', [PortfolioController::class, 'store']);
        Route::post('/portfolios/{portfolio}', [PortfolioController::class, 'update']);
        Route::delete('/portfolios/{portfolio}', [PortfolioController::class, 'destroy']);
    });
});

// WebSocket routes (inside auth:sanctum, moved below)

// Fallback
Route::fallback(function () {
    return response()->json(['error' => 'Endpoint not found (404)'], 404);
});
