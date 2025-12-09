<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Content Security Policy
        // ⚠️ PRODUCTION NOTE: Remove 'unsafe-inline' and 'unsafe-eval' in production
        // Use nonces or hashes instead. These are included for Vite development compatibility.
        // For production CSP, see SECURITY.md for proper configuration.
        $cspDirective = config('app.env') === 'production'
            ? "default-src 'self'; " .
              "script-src 'self'; " .
              "style-src 'self'; " .
              "img-src 'self' data: https:; " .
              "font-src 'self' data:; " .
              "connect-src 'self'; " .
              "frame-ancestors 'none'; " .
              "base-uri 'self'; " .
              "form-action 'self';"
            : "default-src 'self'; " .
              "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
              "style-src 'self' 'unsafe-inline'; " .
              "img-src 'self' data: https:; " .
              "font-src 'self' data:; " .
              "connect-src 'self'; " .
              "frame-ancestors 'none'; " .
              "base-uri 'self'; " .
              "form-action 'self';";
        
        $response->headers->set('Content-Security-Policy', $cspDirective);

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');

        // Permissions policy (formerly Feature Policy)
        $response->headers->set('Permissions-Policy', 
            'geolocation=(), microphone=(), camera=()'
        );

        return $response;
    }
}
