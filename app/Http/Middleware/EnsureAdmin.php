<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('user_id')) {
            return redirect()->route('login')->with('error', 'Please sign in to continue.');
        }

        if ($request->session()->get('role') !== 'admin') {
            return redirect()->route('user.dashboard')->with('error', 'Administrator access is required for that page.');
        }

        return $next($request);
    }
}
