<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/firebase-service-account.json'));

        $this->messaging = $factory->createMessaging();
    }

    /**
     * Kirim notifikasi ke semua perangkat user (berdasarkan user_id)
     */
    public function sendToUser($userId, $title, $body, $data = [])
    {
        // Ambil token FCM dari tabel user_fcm_tokens
        $tokens = DB::table('user_fcm_tokens')
            ->where('user_id', $userId)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            Log::info("Tidak ada FCM token untuk user ID {$userId}");
            return;
        }

        $notification = Notification::create($title, $body);

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withData($data);

        try {
            $response = $this->messaging->sendMulticast($message, $tokens);
            Log::info("FCM terkirim ke {$response->successes()->count()} perangkat, gagal: {$response->failures()->count()}");
        } catch (\Exception $e) {
            Log::error("Gagal mengirim notifikasi FCM: " . $e->getMessage());
        }
    }
}
