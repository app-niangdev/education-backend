<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        health: '/up',
        commands: __DIR__.'/../routes/console.php',
        // Les regles d'ecoute des canaux WebSocket. La route d'autorisation
        // qui les applique est declaree a la main dans routes/api.php : celle
        // que Laravel poserait ici passe par le garde « web », donc par une
        // session — l'application n'en a pas, elle authentifie par JWT.
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn() => null);

        // En tete du groupe API : une IP sous blocage repart avec un 429 avant
        // qu'aucune route, aucun controleur ni aucune authentification n'ait
        // ete touche. C'est ce placement qui garantit qu'une tentative faite
        // pendant un blocage ne coute rien et ne prolonge rien.
        $middleware->prependToGroup('api', \App\Http\Middleware\BlockSuspiciousIp::class);

        $middleware->alias([
        'jwt.auth'   => \App\Http\Middleware\JwtAuthenticate::class,
        'ip.blocked' => \App\Http\Middleware\BlockSuspiciousIp::class,
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
    // Force Laravel à traiter TOUTES les requêtes API comme JSON
    $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
        return $request->is('api/*');
    });

    $exceptions->render(function (AuthenticationException $e, Request $request) {
        return response()->json([
            'message' => 'Non authentifié.'
            ], 401);
    });

    $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
        return response()->json([
            'message' => 'Action non autorisée.',
        ], 403);
    });
})
    ->create();
