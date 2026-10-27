<?php

namespace App\Http\Middleware;

use App\Support\GoogleLogin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGoogleLoginEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(GoogleLogin::enabled(), 404);

        return $next($request);
    }
}
