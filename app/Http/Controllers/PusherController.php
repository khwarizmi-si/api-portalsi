<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Pusher\Pusher;

class PusherController extends Controller
{
    public function pusherAuth(Request $request)
    {
        $user = auth()->user();
        $socketId = $request->input('socket_id');
        $channelName = $request->input('channel_name');

        $key = config('broadcasting.connections.pusher.key'); 
        $secret = config('broadcasting.connections.pusher.secret'); 
        $appId = config('broadcasting.connections.pusher.app_id'); 

        if ($user) {
            $pusher = new Pusher($key, $secret, $appId, [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS' => true,
            ]);
            $auth = $pusher->socketAuth($channelName, $socketId);
            return response($auth, 200);
        }

        return response('Forbidden', 403);
    }
}
