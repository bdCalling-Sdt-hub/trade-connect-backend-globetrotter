<?php

namespace App\Http\Middleware;

use App\Mail\UnauthorizedAccessAttempt;
use Closure;
use Illuminate\Http\Request;
use Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->role === 'ADMIN') {
            return $next($request);
        }
        Mail::to('mdmaksudbhuiyan595@gmail.com')->queue(
            new UnauthorizedAccessAttempt($request->ip(), auth()->user()->email ?? 'Guest')
        );
        return response()->json(['error' => 'Access denied.'], 403);
    }
}
