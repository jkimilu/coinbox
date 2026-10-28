<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $token = $request->bearerToken();
        $key = $token ? ApiKey::findByToken($token)?->load('merchant') : null;

        if (! $key || ! $key->isUsable() || ! $key->merchant?->isActive()) {
            return response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'Invalid or missing API key.']], 401);
        }

        if ($ability && ! $key->allows($ability)) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => "API key lacks the [{$ability}] permission."]], 403);
        }

        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('api_key', $key);
        $request->attributes->set('merchant', $key->merchant);

        return $next($request);
    }
}
