<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasRole('OWNER')) {
            return response()->json([
                'message' => 'Access denied. This area is for owners only.',
            ], 403);
        }

        return $next($request);
    }
}
