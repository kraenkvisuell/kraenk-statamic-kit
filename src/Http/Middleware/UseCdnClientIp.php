<?php

namespace Kraenkvisuell\StatamicKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The site is served through Bunny CDN. Bunny writes its own edge IP *first*
 * into X-Forwarded-For (followed by the visitor's IP), so trusting that
 * header made all visitors behind one edge share a single IP – and
 * Statamic's login rate limit (4 attempts per minute per IP) locked
 * everyone out with a 429. bootstrap/app.php therefore trusts only the
 * X-Forwarded-Host/Port/Proto headers, and this middleware takes the
 * visitor's IP from Bunny's X-Real-IP: when it is there, it becomes the
 * request's remote address.
 *
 * Runs before TrustProxies. Like trusting the proxy headers, this relies on
 * the origin only being reachable through the CDN.
 */
class UseCdnClientIp
{
    public function handle(Request $request, Closure $next)
    {
        $ip = trim((string) $request->headers->get('X-Real-IP'));

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $request->server->set('REMOTE_ADDR', $ip);
        }

        return $next($request);
    }
}
