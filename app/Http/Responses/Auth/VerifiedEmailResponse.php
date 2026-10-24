<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;

/**
 * Bawaan Fortify redirect ke home + query string "?verified=1", lalu home menampilkan
 * banner. User minta halaman KHUSUS yang bilang "berhasil diverifikasi" dengan tombol
 * kembali -- bukan cuma banner di atas halaman lain. Route tujuan (verification.success)
 * statis, jadi tidak perlu query string apa pun.
 */
class VerifiedEmailResponse implements VerifyEmailResponse
{
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->route('verification.success');
    }
}
