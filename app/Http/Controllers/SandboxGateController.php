<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SandboxGate;
use App\Models\SandboxAccessCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SandboxGateController extends Controller
{
    public function show(): View
    {
        return view('pages.sandbox-gate');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'pin' => ['required', 'string', 'size:6'],
        ]);

        $code = SandboxAccessCode::query()
            ->where('email', $data['email'])
            ->where('pin', $data['pin'])
            ->whereNull('used_at')
            ->first();

        if (! $code) {
            return back()->withErrors([
                'pin' => 'Email atau kode salah, atau kode sudah pernah dipakai.',
            ])->withInput();
        }

        $code->update(['used_at' => now()]);

        return redirect()->intended(route('home'))
            ->withCookie(cookie()->forever(SandboxGate::COOKIE, (string) $code->id));
    }
}
