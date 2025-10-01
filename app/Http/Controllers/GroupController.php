<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GroupController extends Controller
{
// 🔹 1. Buat grup + tambah member langsung
public function store(Request $request)
{
    $user = Auth::user();

    // ❌ Hapus validasi verifikasi
    // if (!$user->is_verified) {
    //     return response()->json(['message' => 'Hanya user terverifikasi (centang biru) yang bisa membuat grup.'], 403);
    // }

    $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'avatar' => 'nullable|file|image|mimes:jpg,jpeg,png|max:10240',
        'cover' => 'nullable|file|image|mimes:jpg,jpeg,png|max:10240',
        'members' => 'nullable|array',
        'members.*' => 'string', // username atau email
    ]);

    $group = new Group();
    $group->owner_id = $user->user_id;
    $group->name = $request->name;
    $group->description = $request->description;

    if ($request->hasFile('avatar')) {
        $path = $request->file('avatar')->store('uploads/group-avatars', 'public');
        $group->avatar_url = asset('storage/' . $path);
    }

    if ($request->hasFile('cover')) {
        $path = $request->file('cover')->store('uploads/group-covers', 'public');
        $group->cover_url = asset('storage/' . $path);
    }

    $group->save();

    // Tambah owner sebagai admin
    GroupMember::create([
        'group_id' => $group->id,
        'user_id' => $user->user_id,
        'role' => 'admin',
        'joined_at' => now(),
        'is_muted' => false,
    ]);

    // Jika ada members dari request
    if ($request->filled('members')) {
        foreach ($request->members as $identifier) {
            $target = User::where('username', $identifier)
                ->orWhere('email', $identifier)
                ->first();

            if (!$target) {
                continue; // skip kalau user tidak ditemukan
            }

            if (GroupMember::where('group_id', $group->id)
                ->where('user_id', $target->user_id)
                ->exists()) {
                continue;
            }

            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $target->user_id,
                'role' => 'member',
                'joined_at' => now(),
                'is_muted' => false,
            ]);
        }
    }

    return response()->json([
        'message' => 'Grup berhasil dibuat.',
        'group' => $group->load('members.user')
    ]);
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

        if ($membership->role === 'admin') {
            $adminCount = GroupMember::where('group_id', $group->id)->where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => 'Admin terakhir tidak bisa keluar dari grup.'], 403);
            }
        }

        $membership->delete();

        return response()->json(['message' => 'Berhasil keluar dari grup.']);
    }

// 🔹 4. Detail grup
public function show(Group $group)
{
    // 🔹 Load relasi owner & members dengan field tambahan
    $group->load([
        'owner:user_id,username,is_verified',
        'members.user:user_id,username,is_verified'
    ]);

    return response()->json([
        'group' => [
            'id'          => $group->id,
            'name'        => $group->name,
            'description' => $group->description,
            'avatar_url'  => $group->avatar_url,
            'cover_url'   => $group->cover_url,
            'owner' => [
                'user_id'    => $group->owner->user_id,
                'username'   => $group->owner->username,
                'is_verified'=> (bool) $group->owner->is_verified,
            ],
        ],
        'members' => $group->members->map(function ($member) {
            return [
                'user_id'    => $member->user->user_id,
                'username'   => $member->user->username,
                'is_verified'=> (bool) $member->user->is_verified,
                'role'       => $member->role,
            ];
        }),
    ]);
}


    // 🔹 5. Update grup (PUT /groups/{group})
    public function update(Request $request, Group $group)
    {
        $user = auth()->user();

        if ($group->owner_id !== $user->user_id) {
            return response()->json(['message' => 'Tidak diizinkan mengedit grup ini.'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'avatar' => 'nullable|file|image|mimes:jpg,jpeg,png|max:10240',
            'cover' => 'nullable|file|image|mimes:jpg,jpeg,png|max:10240',
        ]);

        $group->name = $validatedData['name'];
        $group->description = $validatedData['description'] ?? $group->description;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('uploads/group-avatars', 'public');
            $group->avatar_url = asset('storage/' . $avatarPath);
        }

        if ($request->hasFile('cover')) {
            $coverPath = $request->file('cover')->store('uploads/group-covers', 'public');
            $group->cover_url = asset('storage/' . $coverPath);
        }

        $group->save();

        return response()->json([
            'message' => 'Grup berhasil diperbarui.',
            'group' => $group
        ]);
    }
    
    // 🔹 6. HAPUS GRUP (DELETE /groups/{group})
    public function destroy(Group $group)
{
    $user = auth()->user();

    if ($group->owner_id !== $user->user_id) {
        return response()->json([
            'message' => 'Kamu tidak diizinkan menghapus grup ini.'
        ], 403);
    }

    // Hapus avatar & cover dari storage jika ada
    if ($group->avatar_url) {
        $this->hapusFileStorage($group->avatar_url);
    }
    if ($group->cover_url) {
        $this->hapusFileStorage($group->cover_url);
    }

    $group->delete();

    return response()->json([
        'message' => 'Grup berhasil dihapus.'
    ]);
}

// Fungsi bantu hapus file dari public disk
private function hapusFileStorage($url)
{
    $path = str_replace(asset('storage') . '/', '', $url);
    if (\Storage::disk('public')->exists($path)) {
        \Storage::disk('public')->delete($path);
    }
}

// 🔹 7. Tambah anggota (POST /groups/{group}/members)
public function addMember(Request $request, Group $group)
{
    $request->validate([
        'identifier' => 'required|string', // username atau email
        'role' => 'nullable|in:member,admin',
    ]);

    $user = Auth::user();

    // Hanya admin yang bisa menambah anggota
    $isAdmin = GroupMember::where('group_id', $group->id)
        ->where('user_id', $user->user_id)
        ->where('role', 'admin')
        ->exists();

    if (!$isAdmin) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // Temukan user dari username atau email
    $target = User::where('username', $request->identifier)
        ->orWhere('email', $request->identifier)
        ->first();

    if (!$target) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // Cegah duplikat anggota
    if (GroupMember::where('group_id', $group->id)->where('user_id', $target->user_id)->exists()) {
        return response()->json(['message' => 'User already joined'], 409);
    }

    GroupMember::create([
        'group_id' => $group->id,
        'user_id' => $target->user_id,
        'role' => $request->role ?? 'member',
        'joined_at' => now(),
        'is_muted' => false,
    ]);
    broadcast(new \App\Events\MemberAdded($group, $target))->toOthers();

    return response()->json(['message' => 'User added to group'], 201);
}

public function listMembers(Group $group)
{
    $members = GroupMember::with('user')
        ->where('group_id', $group->id)
        ->get()
        ->map(function ($member) {
            return [
                'user_id' => $member->user_id,
                'full_name' => $member->user->full_name,
                'role' => $member->role,
                'joined_at' => $member->joined_at,
                'is_muted' => (bool) $member->is_muted,
                'username' => $member->user->username,
                'profile_picture_url' => $member->user->profile_picture_url,
                'is_online' => (bool) $member->user->is_online,
                'last_seen' => $member->user->last_seen,
            ];
        });

    return response()->json([
        'data' => $members
    ]);
}


public function promoteToAdmin(Group $group, User $user)
{
    return $this->updateRole($group, $user, 'admin');
}

public function demoteToMember(Group $group, User $user)
{
    return $this->updateRole($group, $user, 'member');
}

protected function updateRole(Group $group, User $target, string $role)
{
    $actor = Auth::user();

    $isAdmin = GroupMember::where('group_id', $group->id)
        ->where('user_id', $actor->user_id)
        ->where('role', 'admin')
        ->exists();

    if (!$isAdmin) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $member = GroupMember::where('group_id', $group->id)
        ->where('user_id', $target->user_id)
        ->first();

    if (!$member) {
        return response()->json(['message' => 'User is not a member'], 404);
    }

    $member->role = $role;
    $member->save();

    return response()->json(['message' => 'Role updated']);
}

public function removeMember(Group $group, User $user)
{
    $actor = Auth::user();

    $isAdmin = GroupMember::where('group_id', $group->id)
        ->where('user_id', $actor->user_id)
        ->where('role', 'admin')
        ->exists();

    if (!$isAdmin) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $deleted = GroupMember::where('group_id', $group->id)
        ->where('user_id', $user->user_id)
        ->delete();
         if ($deleted) {
        // 👇 TAMBAHKAN INI: Siarkan bahwa seorang anggota telah dihapus
        // Anda perlu membuat Event 'MemberRemoved'
        broadcast(new \App\Events\MemberRemoved($group, $user))->toOthers();
    }

    return response()->json(['message' => $deleted ? 'Removed' : 'Not found']);
}

public function muteMember(Group $group, User $user)
{
    return $this->toggleMute($group, $user, true);
}

public function unmuteMember(Group $group, User $user)
{
    return $this->toggleMute($group, $user, false);
}

protected function toggleMute(Group $group, User $target, bool $mute)
{
    $actor = Auth::user();

    $isAdmin = GroupMember::where('group_id', $group->id)
        ->where('user_id', $actor->user_id)
        ->where('role', 'admin')
        ->exists();

    if (!$isAdmin) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $member = GroupMember::where('group_id', $group->id)
        ->where('user_id', $target->user_id)
        ->first();

    if (!$member) {
        return response()->json(['message' => 'User not found in group'], 404);
    }

    $member->is_muted = $mute;
    $member->save();

    return response()->json(['message' => $mute ? 'User muted' : 'User unmuted']);
}

public function checkRole($groupId)
{
    $user = Auth::user();

    $membership = GroupMember::where('group_id', $groupId)
        ->where('user_id', $user->user_id) // ✅ BUKAN $user->id
        ->first();

    if (!$membership) {
        return response()->json([
            'status' => 'error',
            'message' => 'User bukan member dari grup ini'
        ], 404);
    }

    return response()->json([
        'status' => 'success',
        'group_id' => $groupId,
        'user_id' => $user->user_id,
        'role' => $membership->role,
    ]);
}


}
