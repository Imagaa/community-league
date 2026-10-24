<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect('/login')
                ->withErrors(['email' => 'Login dengan Google gagal. Silakan coba lagi.']);
        }

        if (Auth::check()) {
            return $this->linkToCurrentUser($googleUser);
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            // Tautkan akun email yang sudah ada ke Google.
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: 'Pengguna',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar_path' => $googleUser->getAvatar(),
                'password' => bcrypt(Str::random(40)),
                'terms_version' => User::TERMS_VERSION,
                'terms_accepted_at' => now(),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }

    /**
     * Dipanggil saat tombol "Hubungkan Google" di tab Keamanan diklik oleh user yang SUDAH
     * login -- beda dari flow login/register di atas, harus tautkan google_id ke user yang
     * sedang aktif, bukan cari-atau-buat user lain berdasarkan email Google.
     */
    private function linkToCurrentUser(SocialiteUser $googleUser): RedirectResponse
    {
        $currentUser = Auth::user();

        $alreadyLinkedElsewhere = User::where('google_id', $googleUser->getId())
            ->where('id', '!=', $currentUser->id)
            ->exists();

        if ($alreadyLinkedElsewhere) {
            return redirect()->route('akun.keamanan')
                ->withErrors(['google' => 'Akun Google ini sudah terhubung ke akun Community League lain.']);
        }

        $currentUser->forceFill(['google_id' => $googleUser->getId()])->save();

        return redirect()->route('akun.keamanan')->with('status', 'google-linked');
    }
}
