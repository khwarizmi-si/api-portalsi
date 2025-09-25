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
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use App\Http\Controllers\UserSuggestionController;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
        'role' => 'in:teacher,parent,student,other'
    ], [
        'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, dan underscore tanpa spasi atau simbol lain.'
    ]);

    if ($request->role === 'dev') {
        return response()->json([
            'message' => 'Role "dev" tidak diizinkan untuk registrasi publik.'
        ], 403);
    }

    $user = User::create([
        'username' => strtolower($request->username), // 👈 Lowercase username
        'full_name' => $request->full_name,
        'email' => strtolower($request->email),     // 👈 Lowercase email
        'password_hash' => bcrypt($request->password),
        'role' => $request->role ?? 'student',
        'profile_picture_url' => 'https://api-new.portalsi.com/storage/default-profile.png',
        'banner_url' => 'https://api-new.portalsi.com/storage/default-banner.png',
    ]);

    $user->sendEmailVerificationNotification();

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'message' => 'User registered successfully. Please verify your email.',
        'token' => $token,
        'user' => $user
    ], 201);
});

// 🚀 Login + Cek Membership (tanpa Sanctum)
Route::post('/login-check', function (Request $request) {
    // 1️⃣ Validasi input
    $request->validate([
        'username' => 'required|string',
        'password' => 'required|string',
    ]);

    // 2️⃣ Cari user berdasarkan username (lowercase biar konsisten)
    $user = User::where('username', strtolower($request->username))->first();

    // 3️⃣ Cek apakah user ada & password cocok
    if (!$user || !Hash::check($request->password, $user->password_hash)) {
        return response()->json([
            'success' => false,
            'message' => 'Username atau password salah',
        ], 401);
    }

    // 4️⃣ Ambil grup user (relasi ke tabel groups)
    $groups = $user->groups()
        ->select('groups.id as group_id', 'groups.name')
        ->get();

    // 5️⃣ Return JSON
    return response()->json([
        'success' => true,
        'user'    => [
            'id'        => $user->user_id,
            'username'  => $user->username,
            'full_name' => $user->full_name,
            'role'      => $user->role,
        ],
        'groups'  => $groups,
    ]);
});

// 🚀 Bulk Register khusus Teacher tanpa email wajib
Route::post('/register-teachers', function (Request $request) {
    $teachers = $request->input('teachers');

    if (!$teachers || !is_array($teachers)) {
        return response()->json([
            'message' => 'Parameter teachers harus berupa array.'
        ], 400);
    }

    $createdUsers = [];

    foreach ($teachers as $teacherData) {
        if (empty($teacherData['username']) || empty($teacherData['password'])) {
            continue; // skip kalau tidak ada username / password
        }

        // cek username unik
        if (\App\Models\User::where('username', strtolower($teacherData['username']))->exists()) {
            continue; // skip kalau sudah ada
        }

        $user = \App\Models\User::create([
            'username' => strtolower($teacherData['username']),
            'password_hash' => bcrypt($teacherData['password']),
            'role' => 'teacher',
            'full_name' => $teacherData['full_name'] ?? null,
            'email' => $teacherData['email'] ?? null,
            'profile_picture_url' => 'https://api-new.portalsi.com/storage/default-profile.png',
            'banner_url' => 'https://api-new.portalsi.com/storage/default-banner.png'
        ]);

        // langsung verifikasi
        $user->markEmailAsVerified();

        $createdUsers[] = $user;
    }

    return response()->json([
        'message' => 'Teachers registered successfully.',
        'count' => count($createdUsers),
        'users' => $createdUsers
    ], 201);
});


// 🚀 Register khusus Parent tanpa email & full_name, auto verified
Route::post('/register-parent', function (Request $request) {
    $request->validate([
        'username' => [
            'required',
            'string',
            'unique:users',
            'regex:/^[a-zA-Z0-9._]+$/'
        ],
        'password' => 'required|min:1',
    ], [
        'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, dan underscore tanpa spasi atau simbol lain.'
    ]);

    $user = User::create([
        'username' => strtolower($request->username),
        'password_hash' => bcrypt($request->password),
        'role' => 'parent',
        'full_name' => null,
        'email' => null,
        'profile_picture_url' => 'https://api-new.portalsi.com/storage/default-profile.png',
        'banner_url' => 'https://api-new.portalsi.com/storage/default-banner.png'
    ]);

    // 🔥 langsung verifikasi email meskipun null
    $user->markEmailAsVerified();

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'message' => 'Parent registered successfully (auto verified).',
        'token' => $token,
        'user' => $user
    ], 201);
});



// ✅ Login API aman
Route::post('/login', function (Request $request) {
    // 1️⃣ Validasi input
    $request->validate([
        'login'    => 'required|string',
        'password' => 'required|string',
    ]);

    // 2️⃣ Cari user by email atau username (lowercase)
    $user = User::where(function ($query) use ($request) {
        $query->where('email', strtolower($request->login))
              ->orWhere('username', strtolower($request->login));
    })->first();

    // 3️⃣ Validasi kredensial
    if (!$user || !Hash::check($request->password, $user->password_hash)) {
        return response()->json([
            'code'    => 2001,
            'message' => 'Username/email atau password salah!'
        ], 401);
    }

    // 4️⃣ Validasi email verification
    if (!$user->hasVerifiedEmail()) {
        return response()->json([
            'code'    => 2002,
            'message' => 'Akun Anda belum diverifikasi. Silakan cek email Anda untuk melakukan verifikasi.'
        ], 403);
    }

    // 5️⃣ Generate token Sanctum
    $tokenResult = $user->createToken('api-token');
    $plainTextToken = $tokenResult->plainTextToken;

    // 6️⃣ Ambil token ID dari database
    $tokenId = \Laravel\Sanctum\PersonalAccessToken::where('token', hash('sha256', explode('|', $plainTextToken)[1]))
                ->first()?->id;

    // 7️⃣ Catat login history dengan aman
    try {
        $agent = new Jenssegers\Agent\Agent();

        LoginHistory::create([
            'user_id'    => $user->user_id, // pastikan user_id
            'token_id'   => $tokenId,
            'ip_address' => $request->ip() ?? 'unknown',
            'user_agent' => $request->header('User-Agent') ?? 'unknown',
            'device'     => $agent->device() ?? 'unknown',
            'browser'    => $agent->browser() ?? 'unknown',
            'platform'   => $agent->platform() ?? 'unknown',
            'login_at'   => now(),
        ]);

        Log::info('Login history recorded for user_id: ' . $user->user_id);

    } catch (\Exception $e) {
        Log::error('Skipping LoginHistory insert: '.$e->getMessage(), [
            'user' => $user,
            'token_id' => $tokenId
        ]);
    }

    // 8️⃣ Return response
    return response()->json([
        'code'    => 1001,
        'message' => 'Login successful',
        'token'   => $plainTextToken,
        'user'    => $user,
    ], 200);
});


// 📩 Email Verification
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = \App\Models\User::findOrFail($id);
    if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Invalid verification link.'], 403);
    }

    if (!$user->hasVerifiedEmail()) {
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

Route::middleware(['auth:sanctum'])->get('/parent-groups', function (Request $request) {
    $user = $request->user();

    // 🚫 Cek role user
    if ($user->role !== 'parent') {
        return response()->json([
            'error' => 'Hanya user dengan role parent yang dapat mengakses endpoint ini.'
        ], 403);
    }

    // 1️⃣ Ambil grup yang user ikuti, filter hanya id 1-6
    $groups = $user->groups()
        ->whereIn('groups.id', [1, 2, 3, 4, 5, 6])
        ->select('groups.id', 'groups.name', 'groups.description', 'groups.avatar_url')
        ->get();

    // 2️⃣ Hitung unread_message_count per grup
    $result = $groups->map(function ($group) use ($user) {
        $unreadCount = DB::table('group_messages')
            ->leftJoin('group_message_reads', function ($join) use ($user) {
                $join->on('group_messages.id', '=', 'group_message_reads.group_message_id') // ✅ pakai kolom yg benar
                     ->where('group_message_reads.user_id', '=', $user->user_id);
            })
            ->where('group_messages.group_id', $group->id)
            ->whereNull('group_message_reads.id')
            ->count();

        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'avatar_url' => $group->avatar_url,
            'unread_message_count' => (string) $unreadCount,
        ];
    });

    // 3️⃣ Return hasil
    return response()->json($result);
});


Route::get('/profile/{username}', [ProfileController::class, 'show']);

// 🔐 PROTECTED ROUTES
Route::middleware(['auth:sanctum'])->group(function () {
     Broadcast::routes(['middleware' => ['auth:sanctum']]);
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/user', [ProfileController::class, 'me']);
    });

    // Public Feed
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{id}', [PostController::class, 'show']);
    Route::get('/posts/{post_id}/likes', [LikeController::class, 'index']);
    Route::get('/users/{id}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{id}/following', [FollowController::class, 'following']);
    Route::get('/stories/feed', [StoryController::class, 'feed']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/explore', [PostController::class, 'explore']);
    Route::get('/users/search', [ProfileController::class, 'search']);

    // 🔐 Only for verified users
    Route::middleware('verified.api')->group(function () {
        // Post CRUD
        Route::post('/posts', [PostController::class, 'store']);
        Route::post('/posts/{id}/update', [PostController::class, 'update']);
        Route::delete('/posts/{id}', [PostController::class, 'destroy']);

        // User Suggestion
        Route::get('/suggestions', [UserSuggestionController::class, 'index']);

        // Comments
        Route::get('/posts/{post_id}/comments', [CommentController::class, 'getCommentsByPost']);
        Route::post('/posts/{post_id}/comments', [CommentController::class, 'store']);
        Route::put('/comments/{id}', [CommentController::class, 'update']);
        Route::delete('/comments/{id}', [CommentController::class, 'destroy']);
        Route::post('/comments/{comment_id}/like', [CommentController::class, 'like']);
        Route::delete('/comments/{comment_id}/like', [CommentController::class, 'unlike']);

        // Likes & Follow
        Route::post('/posts/{post_id}/like', [LikeController::class, 'toggle']);
        Route::post('/follow/{id}', [FollowController::class, 'follow']);
        Route::delete('/unfollow/{id}', [FollowController::class, 'unfollow']);

        // Story
        Route::post('/stories', [StoryController::class, 'store']);
        Route::delete('/stories/{id}', [StoryController::class, 'destroy']);
        Route::post('/stories/{id}/view', [StoryController::class, 'view']);
        Route::get('/stories/my', [StoryController::class, 'myStories']);
        Route::get('/stories/{id}/viewers', [StoryViewController::class, 'viewers']);
        Route::get('/stories/user/{userId}', [StoryController::class, 'getByUser']);
        Route::get('/stories/my/archived', [StoryController::class, 'myArchivedStories']);

        // Notifications
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/notifications/read/all', [NotificationController::class, 'markAllAsRead']);

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
            Route::delete('{group}', [GroupController::class, 'destroy']); 
            Route::get('{group}/role', [GroupController::class, 'checkRole']);
        });


        // Group Messages
        Route::prefix('groups/{group}')->group(function () {
            Route::post('/messages', [GroupMessageController::class, 'store']);
            Route::get('/messages', [GroupMessageController::class, 'index']);
            Route::delete('/messages/{message}', [GroupMessageController::class, 'destroy']);
            Route::post('/messages/{message}/pin', [GroupMessageController::class, 'togglePin']);
            Route::post('/messages/{message}/read', [GroupMessageController::class, 'markAsRead']);
            Route::get('/messages/{message}/read-info', [GroupMessageController::class, 'readInfo']);
            Route::get('/messages/unread', [GroupMessageController::class, 'unreadMessages']);
        });

        // Announcements
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/announcements', [AnnouncementController::class, 'index']);
            Route::get('/announcements/pinned', [AnnouncementController::class, 'pinned']); // hanya pinned

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

        // Bookmarks
        Route::middleware('auth:sanctum')->group(function () {
        Route::get('/bookmarks', [BookmarkController::class, 'index']);
        Route::post('/bookmarks/{postId}', [BookmarkController::class, 'store']);
        Route::delete('/bookmarks/{postId}', [BookmarkController::class, 'destroy']);
        });

        // 📌 Login History
        Route::prefix('login-histories')->group(function () {
        Route::get('/', [LoginHistoryController::class, 'index']); // list riwayat login user
        Route::delete('/{id}', [LoginHistoryController::class, 'destroy']); // hapus riwayat tertentu
    });


        // WebSocket Routes
        Route::prefix('websocket')->group(function () {
            Route::post('/authenticate', [WebSocketController::class, 'authenticate']);
            Route::post('/disconnect', [WebSocketController::class, 'disconnect']);
            Route::get('/online-status/{userId}', [WebSocketController::class, 'getOnlineStatus']);
            Route::get('/online-followers', [WebSocketController::class, 'getOnlineFollowersCount']);
            Route::get('/online-count', [WebSocketController::class, 'getTotalOnlineCount']);
            Route::post('/update-activity', [WebSocketController::class, 'updateActivity']);
        });

    });
});

// Fallback
Route::fallback(function () {
    return response()->json([
        'error' => 'Endpoint not found (404)'
    ], 404);
});
