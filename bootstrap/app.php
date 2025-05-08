<?php

use App\Http\Middleware\JwtMiddleware;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

$errorResponse = new class {
    use ApiResponse;
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        //web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        //commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt' => \App\Http\Middleware\JwtMiddleware::class,
        ]);
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($errorResponse) {
        $exceptions->renderable(function (Exception $e) use ($errorResponse) {
            return $errorResponse->errorResponse($e);
        });
    })
    ->withProviders([
        Tymon\JWTAuth\Providers\LaravelServiceProvider::class,
    ])
    ->create();
