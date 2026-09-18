<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsSuperAdmin
{
    /**
     * Handle an incoming request.
     * Only allows users with the SUPER-ADMIN role to proceed.
     * Does NOT log out — just redirects non-admins to login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        file_put_contents(storage_path('logs/mw-debug.txt'),
            date('Y-m-d H:i:s') . ' | user=' . ($user?->email ?? 'null')
            . ' | roles=' . ($user ? $user->getRoleNames()->implode(',') : 'none')
            . ' | has_role=' . ($user?->hasRole('SUPER-ADMIN') ? 'yes' : 'no')
            . PHP_EOL,
            FILE_APPEND
        );

        if (! $user || ! $user->hasRole('SUPER-ADMIN')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
