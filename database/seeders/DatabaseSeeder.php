<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ──────────────────────────────────────────────
        $users = [];
        $userData = [
            ['username' => 'dev_portalsi', 'full_name' => 'Dev Portal SI', 'email' => 'dev@portalsi.local', 'role' => 'dev', 'is_verified' => true],
            ['username' => 'alice_wonder', 'full_name' => 'Alice Wonder', 'email' => 'alice@portalsi.local', 'role' => 'student', 'is_verified' => true],
            ['username' => 'bob_builder', 'full_name' => 'Bob Builder', 'email' => 'bob@portalsi.local', 'role' => 'teacher', 'is_verified' => true],
            ['username' => 'carol_parent', 'full_name' => 'Carol Parent', 'email' => 'carol@portalsi.local', 'role' => 'parent', 'is_verified' => false],
            ['username' => 'dave_student', 'full_name' => 'Dave Student', 'email' => 'dave@portalsi.local', 'role' => 'student', 'is_verified' => false],
        ];

        foreach ($userData as $i => $u) {
            $users[$i + 1] = User::create(array_merge($u, [
                'password_hash' => Hash::make('password123'),
                'bio' => "Halo, saya {$u['full_name']} di Portal SI 👋",
                'email_verified_at' => $u['is_verified'] ? now() : null,
                'is_online' => false,
            ]));
        }

        // ── Posts ──────────────────────────────────────────────
        $posts = [];
        $postData = [
            ['user_id' => 1, 'caption' => 'Selamat datang di Portal SI! 🚀 Platform sosial media untuk komunitas sekolah.'],
            ['user_id' => 2, 'caption' => 'Hari ini belajar Flutter sangat menyenangkan! 💙 #coding #flutter'],
            ['user_id' => 2, 'caption' => 'Weekend vibes 🌸✨'],
            ['user_id' => 3, 'caption' => 'Minggu ini ujian tengah semester, semangat semuanya! 📚'],
            ['user_id' => 3, 'caption' => 'Tips belajar efektif: teknik Pomodoro 🍅'],
            ['user_id' => 4, 'caption' => 'Bangga sama anak-anak yang terus berkembang 👏'],
            ['user_id' => 5, 'caption' => 'Baru selesai project Laravel + Flutter 🔥'],
            ['user_id' => 1, 'caption' => 'Update fitur baru: Direct Message via WebSocket! 💬'],
        ];

        foreach ($postData as $i => $p) {
            $posts[$i + 1] = Post::create(array_merge($p, [
                'media_url' => 'https://picsum.photos/seed/post' . ($i + 1) . '/800/600',
                'is_video' => false,
                'is_archived' => false,
            ]));
        }

        // ── Likes ──────────────────────────────────────────────
        foreach ([
            ['user_id' => 2, 'post_id' => 1],
            ['user_id' => 3, 'post_id' => 1],
            ['user_id' => 4, 'post_id' => 1],
            ['user_id' => 5, 'post_id' => 1],
            ['user_id' => 1, 'post_id' => 2],
            ['user_id' => 3, 'post_id' => 2],
            ['user_id' => 1, 'post_id' => 4],
            ['user_id' => 2, 'post_id' => 4],
            ['user_id' => 5, 'post_id' => 7],
            ['user_id' => 1, 'post_id' => 7],
        ] as $l) {
            Like::create($l);
        }

        // ── Comments ───────────────────────────────────────────
        foreach ([
            ['post_id' => 1, 'user_id' => 2, 'content' => 'Wah keren banget! 🎉'],
            ['post_id' => 1, 'user_id' => 3, 'content' => 'Semoga bermanfaat untuk semua.'],
            ['post_id' => 1, 'user_id' => 5, 'content' => 'Ikut senang! 😊'],
            ['post_id' => 2, 'user_id' => 1, 'content' => 'Flutter memang the best! 💙'],
            ['post_id' => 2, 'user_id' => 3, 'content' => 'Ayo belajar bareng!'],
            ['post_id' => 4, 'user_id' => 1, 'content' => 'Semangat! 💪'],
            ['post_id' => 7, 'user_id' => 2, 'content' => 'Keren project-nya! 🔥'],
        ] as $c) {
            Comment::create($c);
        }

        // ── Stories ────────────────────────────────────────────
        foreach ([1, 2, 3] as $uid) {
            Story::create([
                'user_id' => $uid,
                'media_url' => 'https://picsum.photos/seed/story' . $uid . '/800/1200',
                'type' => 'image',
                'caption' => 'Story dari ' . $users[$uid]->username,
                'expires_at' => now()->addHours(24),
            ]);
        }
    }
}
