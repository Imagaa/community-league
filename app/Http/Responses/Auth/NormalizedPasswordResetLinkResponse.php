<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Satu respons yang sama persis dipakai baik email-nya terdaftar maupun tidak -- Fortify
 * defaultnya menampilkan pesan BEDA ("email tidak ditemukan" vs "link terkirim"), yang
 * memungkinkan siapa pun mencoba-coba email dan tahu persis mana yang terdaftar di sistem
 * cuma dari respons form lupa password ini. Link reset yang SEBENARNYA tetap
 * hanya terkirim ke email yang benar-benar terdaftar -- ini cuma menyamakan TAMPILANNYA.
 */
class NormalizedPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    private const MESSAGE = 'Kalau email tersebut terdaftar di sistem kami, link reset password sudah dikirim ke alamat tersebut.';

    public function toResponse($request)
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => self::MESSAGE], 200)
            : back()->with('status', self::MESSAGE);
    }
}
