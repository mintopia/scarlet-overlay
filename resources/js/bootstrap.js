import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const reverb = window.scarletConfig?.reverb ?? {};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverb.key,
    wsHost: reverb.host || window.location.hostname,
    wsPort: reverb.port ?? 80,
    wssPort: reverb.port ?? 443,
    forceTLS: (reverb.scheme ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
