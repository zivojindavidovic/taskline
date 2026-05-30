<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment detection
    |--------------------------------------------------------------------------
    |
    | Taskline ships in two flavours: the hosted "Cloud" product on the
    | canonical domain, and "Self-hosted" instances that customers run on
    | their own domain or bare IP. The auth flow (registration in particular)
    | branches on which one is detected from the incoming request host/port.
    |
    | Resolution rules (see HandleInertiaRequests::resolveDeployment):
    |   - host in `cloud_hosts`                  -> cloud
    |   - host is local + port `local_cloud_port`-> cloud   (local simulation)
    |   - host is local + any other port         -> self-hosted (local sim)
    |   - any other custom domain or bare IP     -> self-hosted
    |
    */

    'cloud_hosts' => [
        'tasklines.com',
        'www.tasklines.com',
    ],

    'local_hosts' => [
        'localhost',
        '127.0.0.1',
        '::1',
    ],

    // Local-dev simulation: run `php artisan serve --port=8100` to preview the
    // Cloud flow, or `--port=8101` to preview the Self-hosted flow.
    'local_cloud_port'       => 8100,
    'local_self_hosted_port' => 8101,

];
