<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Turnstile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Nonaktif saat testing dan pengembangan lokal agar test tidak
        // bergantung pada jaringan.
        if (! config('services.turnstile.secret')) {
            return;
        }

        if (blank($value)) {
            $fail('Verifikasi keamanan gagal. Muat ulang halaman dan coba lagi.');

            return;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

        if (! $response->successful() || ! ($response->json('success') ?? false)) {
            $fail('Verifikasi keamanan gagal. Muat ulang halaman dan coba lagi.');
        }
    }
}
