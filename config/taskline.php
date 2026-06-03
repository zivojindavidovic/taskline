<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend Reverb (websocket) connection
    |--------------------------------------------------------------------------
    |
    | These values are exposed to the browser (see resources/views/app.blade.php)
    | so the frontend can connect to Reverb. Leave host/port/scheme null to let
    | the browser derive them from the current page (recommended for self-hosted
    | installs behind nginx's /app proxy) — that keeps the built image
    | host-independent. Only the public app key is shared with the client.
    |
    */

    'reverb' => [
        'key'    => env('REVERB_APP_KEY'),
        'host'   => env('REVERB_FRONTEND_HOST'),
        'port'   => env('REVERB_FRONTEND_PORT'),
        'scheme' => env('REVERB_FRONTEND_SCHEME'),
    ],

];
