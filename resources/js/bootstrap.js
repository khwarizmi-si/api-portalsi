import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// 👉 Tambahkan auth token kalau perlu (misalnya Sanctum / JWT)
window.axios.defaults.headers.common['Authorization'] = 
    "Bearer " + localStorage.getItem("token");

// =====================
// Laravel Echo + Pusher
// =====================
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    wsHost: import.meta.env.VITE_PUSHER_HOST 
        ? import.meta.env.VITE_PUSHER_HOST 
        : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 6001, // kalau pakai Laravel Reverb biasanya 6001
    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],

    // 🔑 custom auth endpoint (ganti dari /broadcasting/auth bawaan)
    authEndpoint: '/pusher/user-auth',
    auth: {
        headers: {
            Authorization: "Bearer " + localStorage.getItem("token"),
        }
    }
});
