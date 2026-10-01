<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Token manquant.'], 401);
        }

        try {
            $payload = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Token invalide ou expiré.'], 401);
        }

        if (($payload->token_type ?? '') !== 'access') {
            return response()->json(['message' => 'Access token requis.'], 401);
        }

        $user = User::find($payload->sub);

        if (! $user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 401);
        }

        // Injecte l'utilisateur dans la requête (compatible avec $request->user())
        Auth::setUser($user);

        return $next($request);
    }
}
