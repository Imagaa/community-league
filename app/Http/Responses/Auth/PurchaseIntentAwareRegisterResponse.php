<?php

namespace App\Http\Responses\Auth;

use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

/**
 * Kalau registrasi ini dipicu dari "Masuk untuk beli" di halaman match (session
 * purchase_intent), kembalikan ke halaman match itu -- form beli muncul dengan nama terisi,
 * pembeli tinggal melengkapi nomor WhatsApp. Jangan ke halaman "cek email" seperti alur daftar
 * biasa; verifikasi email bisa menyusul (banner di halaman Akun sudah mengingatkan).
 */
class PurchaseIntentAwareRegisterResponse implements RegisterResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        $intent = $request->session()->pull('purchase_intent');
        $match = is_array($intent) && isset($intent['match_id']) ? GameMatch::find($intent['match_id']) : null;

        if ($match instanceof GameMatch) {
            $request->session()->forget('url.intended');

            return redirect()->to(route('match.show', $match).'#beli');
        }

        if ($request->session()->pull('membership_purchase_intent') === true) {
            return redirect()->route('membership');
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}
