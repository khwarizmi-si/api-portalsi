<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{

// 🔹 List semua pengumuman (terbaru dulu)
public function index()
{
    return Announcement::with([
        'creator:user_id,full_name,username,profile_picture_url'
    ])->latest()->get();
}


// 🔹 List hanya pengumuman yang pinned
public function pinned()
{
    return Announcement::with([
        'creator:user_id,full_name,username,profile_picture_url'
    ])->where('pinned', 1)
      ->latest()
      ->get();
}

    // 🔹 Tambah pengumuman (admin only)
    public function store(Request $request)
    {
        // Decode poll_data dulu sebelum validasi
        if ($request->filled('poll_data') && is_string($request->poll_data)) {
            $decoded = json_decode($request->poll_data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['poll_data' => $decoded]);
            }
        }

        $request->validate([
            'title'     => 'nullable|string|max:255',
            'content'   => 'nullable|string',
            'image'     => 'nullable|image|max:51200',
            'poll_data' => 'nullable|array',
            'pinned'    => 'boolean',
        ]);

        $data = $request->only(['title', 'content', 'pinned']);
        $data['created_by'] = Auth::user()->user_id;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('uploads/announcements', 'public');
            $data['image_url'] = asset('storage/' . $path);
        }

        if ($request->filled('poll_data')) {
            $data['poll_data'] = json_encode($request->poll_data);
        }

        $announcement = Announcement::create($data);
        $announcement->load('creator:user_id,full_name,username,profile_picture_url');
        event(new \App\Events\NewAnnouncement($announcement));
        return response()->json($announcement, 201);
    }

    // 🔹 Edit pengumuman (admin only)
    public function update(Request $request, Announcement $announcement)
    {
        $this->authorize('update', $announcement);

        // Decode poll_data dulu sebelum validasi
        if ($request->filled('poll_data') && is_string($request->poll_data)) {
            $decoded = json_decode($request->poll_data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['poll_data' => $decoded]);
            }
        }

        $request->validate([
            'title'     => 'nullable|string|max:255',
            'content'   => 'nullable|string',
            'image'     => 'nullable|image|max:51200',
            'poll_data' => 'nullable|array',
            'pinned'    => 'boolean',
        ]);

        $data = $request->only(['title', 'content', 'pinned']);

        if ($request->hasFile('image')) {
            // Hapus file lama jika ada
            if ($announcement->image_url) {
                $oldPath = str_replace(asset('storage/') . '/', '', $announcement->image_url);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('image')->store('uploads/announcements', 'public');
            $data['image_url'] = asset('storage/' . $path);
        }

        if ($request->filled('poll_data')) {
            $data['poll_data'] = json_encode($request->poll_data);
        }

        $announcement->update($data);
        $announcement->load('creator:user_id,full_name,username,profile_picture_url');
        event(new \App\Events\NewAnnouncement($announcement));
        return response()->json($announcement);
    }

    // 🔹 Hapus pengumuman (admin only)
    public function destroy(Announcement $announcement)
    {
        $this->authorize('delete', $announcement);

        // Hapus gambar dari storage jika ada
        if ($announcement->image_url) {
            $oldPath = str_replace(asset('storage/') . '/', '', $announcement->image_url);
            Storage::disk('public')->delete($oldPath);
        }

        $announcement->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
