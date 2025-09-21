<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Jenssegers\Agent\Agent;
use App\Models\LoginHistory;

class LogSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        $request = request();

        $agent = new Agent();

        LoginHistory::create([
            'user_id'    => $user->id,
            'token_id'   => session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'device'     => $agent->device(),
            'browser'    => $agent->browser(),
            'platform'   => $agent->platform(),
            'login_at'   => now(),
        ]);
    }
}
