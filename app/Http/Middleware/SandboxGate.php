<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang akses staging (pengganti Cloudflare Access): kalau browser belum punya cookie
 * "sandbox_access" yang sah, redirect ke form email+PIN. Cookie di-set oleh
 * SandboxGateController::verify() setelah PIN (dibuat admin lewat panel) cocok & belum dipakai.
 *
 * HARUS tetap tidak aktif kecuali di staging -- lihat config('services.sandbox_gate.enabled').
 */
class SandboxGate
{
    public const COOKIE = 'sandbox_access';

    /** Path yang harus tetap bisa diakses walau gate aktif -- webhook (server Sumopod, bukan
     *  browser) dan /up (health check Docker, lihat Dockerfile). Rute gate sendiri dikecualikan
     *  lewat nama rute di bawah supaya tidak infinite redirect. */
    private const EXCLUDED_PATHS = ['webhooks/sumopod', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('services.sandbox_gate.enabled')) {
            return $next($request);
        }

        // Panel admin (Filament) jalan di domain terpisah dengan middleware stack sendiri --
        // TAPI Livewire mendaftarkan endpoint /livewire/update secara GLOBAL (tanpa domain(),
        // lewat grup 'web') untuk SEMUA komponen Livewire di aplikasi ini, termasuk milik
        // Filament. Tanpa pengecualian ini, klik "Reset"/"Buat" di tab Sandbox (aksi Livewire)
        // ikut kena gerbang -- browser admin tidak pernah dapat cookie sandbox_access (beda
        // domain dari gerbang publik) sehingga di-redirect ke halaman gate.
        if ($request->getHost() === config('app.admin_domain')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (in_array($routeName, ['sandbox-gate.show', 'sandbox-gate.verify'], true)) {
            return $next($request);
        }

        if ($request->is(self::EXCLUDED_PATHS)) {
            return $next($request);
        }

        if ($request->cookie(self::COOKIE)) {
            return $next($request);
        }

        return redirect()->guest(route('sandbox-gate.show'));
    }
}
