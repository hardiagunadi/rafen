<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'payment/callback',
            'subscription/payment/callback',
            'webhook/wa/session',
            'webhook/wa/message',
            'webhook/wa/auto-reply',
            'webhook/wa/status',
            'webhook/session',
            'webhook/message',
            'webhook/auto-reply',
            'webhook/status',
        ]);
        $middleware->alias([
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'tenant.module' => \App\Http\Middleware\EnsureTenantModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
