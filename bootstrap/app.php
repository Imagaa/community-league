<?php

use App\Http\Middleware\SandboxGate;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottleAuthEndpoints;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Container hanya dijangkau lewat Traefik di jaringan overlay Docker (tidak ada port
        // publik langsung), jadi percaya semua proxy aman di sini. Tanpa ini, Laravel tidak tahu
        // request aslinya HTTPS (koneksi Traefik->container tetap HTTP), sehingga url()/asset()
        // menghasilkan http:// dan browser memblokirnya sebagai mixed content di halaman https://.
        $middleware->trustProxies(at: '*');

        $middleware->appendToGroup('web', ThrottleAuthEndpoints::class);
        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->appendToGroup('web', SandboxGate::class);

        // Webhook Sumopod datang dari server mereka, bukan browser dengan token CSRF kita --
        // keasliannya diverifikasi lewat header X-Webhook-Token di controller-nya sendiri.
        $middleware->validateCsrfTokens(except: ['webhooks/sumopod']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
