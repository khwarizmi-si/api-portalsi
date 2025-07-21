<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GroupController extends Controller
{
    // 🔹 1. Buat grup
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user->is_verified) {
            return response()->json(['message' => 'Hanya user terverifikasi yang bisa membuat grup.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'avatar_url' => 'nullable|url',
            'cover_url' => 'nullable|url',
        ]);

        $group = Group::create([
            'owner_id' => $user->user_id,
            'name' => $request->name,
            'description' => $request->description,
            'avatar_url' => $request->avatar_url,
            'cover_url' => $request->cover_url,
        ]);

        // Otomatis join sebagai admin
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->user_id,
            'role' => 'admin',
        ]);

        return response()->json(['message' => 'Grup berhasil dibuat.', 'group' => $group]);
    }

    // 🔹 2. Join grup
    public function join(Group $group)
    {
        $user = Auth::user();

        if (GroupMember::where('group_id', $group->id)->where('user_id', $user->user_id)->exists()) {
            return response()->json(['message' => 'Kamu sudah bergabung dalam grup ini.'], 409);
        }

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->user_id,
            'role' => 'member',
        ]);

        return response()->json(['message' => 'Berhasil bergabung ke grup.']);
    }

    // 🔹 3. Leave grup
    public function leave(Group $group)
    {
        $user = Auth::user();

        $membership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$membership) {
            return response()->json(['message' => 'Kamu belum tergabung dalam grup ini.'], 404);
        }

        // Admin tidak bisa keluar jika masih satu-satunya admin
        if ($membership->role === 'admin') {
            $adminCount = GroupMember::where('group_id', $group->id)->where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => 'Admin terakhir tidak bisa keluar dari grup.'], 403);
            }
        }

        $membership->delete();

        return response()->json(['message' => 'Berhasil keluar dari grup.']);
    }

    // 🔹 4. Lihat detail grup
    public function show(Group $group)
    {
        $group->load([
            'owner:user_id,username',
            'members.user:user_id,username'
        ]);
    
        return response()->json([
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'avatar_url' => $group->avatar_url,
                'cover_url' => $group->cover_url,
                'owner' => [
                    'user_id' => $group->owner->user_id,
                    'username' => $group->owner->username,
                ],
            ],
            'members' => $group->members->map(function ($member) {
                return [
                    'user_id' => $member->user->user_id,
                    'username' => $member->user->username,
                    'role' => $member->role,
                ];
            }),
        ]);
    }

    // 🔹 5. Update grup (mendukung multipart/form-data dengan _method=PUT)
    public function update(Request $request, Group $group)
    {
        $user = auth()->user();

        if ($group->owner_id !== $user->user_id) {
            return response()->json(['message' => 'Tidak diizinkan mengedit grup ini.'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'avatar' => 'nullable|file|image|mimes:jpg,jpeg,png|max:2048',
            'cover' => 'nullable|file|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $group->name = $validatedData['name'];
        $group->description = $validatedData['description'] ?? $group->description;

        if ($request->hasFile('avatar')) {
            Log::debug('Avatar file detected');
            $avatarPath = $request->file('avatar')->store('uploads/group-avatars', 'public');
            $group->avatar_url = asset('storage/' . $avatarPath);
        } else {
            Log::debug('Avatar file NOT detected');
        }

        if ($request->hasFile('cover')) {
            Log::debug('Cover file detected');
            $coverPath = $request->file('cover')->store('uploads/group-covers', 'public');
            $group->cover_url = asset('storage/' . $coverPath);
        } else {
            Log::debug('Cover file NOT detected');
        }

        $group->save();

        return response()->json([
            'message' => 'Grup berhasil diperbarui.',
            'group' => $group
        ]);
    }
}
