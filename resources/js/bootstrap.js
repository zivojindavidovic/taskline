import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Reverb connection is resolved at runtime so the built image is host-independent.
// Precedence: server-injected config (window.__TASKLINE__) -> Vite build env
// (dev) -> values derived from the current page (self-hosted behind nginx /app).
const cfg = (window.__TASKLINE__ && window.__TASKLINE__.reverb) || {};
const env = import.meta.env;
const loc = window.location;

const scheme = cfg.scheme || env.VITE_REVERB_SCHEME || loc.protocol.replace(':', '');
const forceTLS = scheme === 'https';
const wsHost = cfg.host || env.VITE_REVERB_HOST || loc.hostname;
const wsPort = Number(cfg.port || env.VITE_REVERB_PORT || (forceTLS ? 443 : 80));

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: cfg.key || env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort,
    wssPort: wsPort,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
});
