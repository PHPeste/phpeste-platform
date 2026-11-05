<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        // A UI do Scramble (/docs/*) carrega Stoplight Elements do unpkg e
        // usa script/style inline — precisa de uma CSP mais frouxa que o
        // resto da aplicação, que não serve HTML nenhum.
        if ($request->is('docs/*')) {
            return "default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com; "
                ."style-src 'self' 'unsafe-inline' https://unpkg.com; img-src 'self' data: https:; "
                ."connect-src 'self'; frame-ancestors 'none'";
        }

        return "default-src 'self'; frame-ancestors 'none'; base-uri 'self'";
    }
}
