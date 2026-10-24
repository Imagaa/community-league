<?php

namespace App\Http\Middleware;

use App\Models\MembershipSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Saklar Membership di admin: saat off, pembelian membership & penukaran poin ditutup. */
class EnsureMembershipEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! MembershipSettings::enabled()) {
            return redirect()->route('membership');
        }

        return $next($request);
    }
}
