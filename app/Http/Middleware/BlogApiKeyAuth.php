<?php

namespace App\Http\Middleware;

use App\Models\BlogApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blog API: "Authorization: Bearer agb_..." with a key from Admin → Blog → API keys.
 * The key is put on the request as the "blogApiKey" attribute.
 */
class BlogApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = BlogApiKey::findByPlain($request->bearerToken());

        if (! $key) {
            return response()->json([
                'message' => 'A valid Blog API key is required. Send it as "Authorization: Bearer agb_...". Create one under Admin → Blog → API keys.',
            ], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        // Record use at most once a minute, so busy clients do not write on every request.
        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }

        $request->attributes->set('blogApiKey', $key);

        return $next($request);
    }
}
