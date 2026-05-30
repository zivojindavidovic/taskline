<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Single source of truth for whether the current request is served by the
 * hosted "Cloud" product or a "Self-hosted" instance.
 *
 * Resolution rules (config/deployment.php):
 *   - host in `cloud_hosts`                   -> cloud
 *   - host is local + port `local_cloud_port` -> cloud        (local simulation)
 *   - host is local + any other port          -> self-hosted  (local simulation)
 *   - any other custom domain or bare IP      -> self-hosted
 */
class Deployment
{
    /**
     * @return array{mode: string, host: string}
     */
    public static function resolve(Request $request): array
    {
        $host = strtolower($request->getHost());
        $port = (int) $request->getPort();

        $cloudHosts = config('deployment.cloud_hosts', []);
        $localHosts = config('deployment.local_hosts', ['localhost', '127.0.0.1', '::1']);

        if (in_array($host, $cloudHosts, true)) {
            $mode = 'cloud';
        } elseif (in_array($host, $localHosts, true)) {
            $mode = $port === (int) config('deployment.local_cloud_port', 8100)
                ? 'cloud'
                : 'self-hosted';
        } else {
            $mode = 'self-hosted';
        }

        return [
            'mode' => $mode,
            'host' => $request->getHttpHost(),
        ];
    }

    public static function mode(Request $request): string
    {
        return self::resolve($request)['mode'];
    }

    public static function isCloud(Request $request): bool
    {
        return self::mode($request) === 'cloud';
    }

    public static function isSelfHosted(Request $request): bool
    {
        return self::mode($request) === 'self-hosted';
    }
}
