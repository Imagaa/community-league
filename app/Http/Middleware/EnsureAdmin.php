<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gerbang semua rute dashboard admin (ADMIN_DOMAIN): tamu ke halaman masuk admin, non-admin 403. */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('admin.login'));
        }

        abort_unless($user->isAdmin(), 403);

        return $next($request);
    }

    /** Dipanggil dari boot() tiap komponen Livewire admin. */
    public static function authorize(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }
}
