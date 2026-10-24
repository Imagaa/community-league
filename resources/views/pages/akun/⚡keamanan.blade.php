<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Keamanan')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $delete_password = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update(['password' => $validated['password']]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password diperbarui.'));
    }

    public function confirmAccountDeletion(): void
    {
        $this->validate([
            'delete_password' => $this->currentPasswordRules(),
        ]);

        Auth::user()->update(['scheduled_deletion_at' => now()->addDays(30)]);

        app(Logout::class)();

        $this->redirect('/', navigate: false);
    }
}; ?>

<div class="flex flex-col gap-4">
    <x-akun-page-header :title="__('Keamanan')" />

    @if (session('status') === 'google-linked')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ __('Akun Google berhasil dihubungkan.') }}
        </div>
    @endif

    <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
        <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Email') }}</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ auth()->user()->email }}</p>
        @unless (auth()->user()->hasVerifiedEmail())
            <div class="mt-3 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                <flux:icon name="exclamation-triangle" class="size-5 shrink-0" />
                <span class="flex-1">{{ __('Email Anda belum diverifikasi.') }}</span>
                <flux:link :href="route('verification.notice')" wire:navigate class="shrink-0 font-medium">
                    {{ __('Verifikasi sekarang') }}
                </flux:link>
            </div>
        @endunless
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
        <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Ganti Password') }}</h2>
        <form wire:submit="updatePassword" class="flex flex-col gap-4">
            <flux:input wire:model="current_password" :label="__('Password saat ini')" type="password" viewable />
            <flux:input wire:model="password" :label="__('Password baru')" type="password" viewable />
            <flux:input wire:model="password_confirmation" :label="__('Konfirmasi password')" type="password" viewable />
            <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
        </form>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
        <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Akun Terhubung') }}</h2>
        @if (auth()->user()->google_id)
            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Terhubung ke Google.') }}</p>
        @else
            <a href="{{ route('auth.google.redirect') }}" class="inline-block rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-navy-700 dark:text-zinc-300 dark:hover:bg-navy-800">
                {{ __('Hubungkan Google') }}
            </a>
        @endif
    </section>

    <section class="rounded-2xl border border-red-200 bg-white p-4 dark:border-red-900 dark:bg-navy-900">
        <h2 class="mb-1 text-sm font-semibold text-red-700 dark:text-red-400">{{ __('Hapus Akun') }}</h2>
        <p class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('Akun akan dihapus permanen 30 hari setelah konfirmasi. Login kembali dalam masa itu untuk membatalkan.') }}
        </p>

        <flux:modal.trigger name="confirm-account-deletion">
            <flux:button variant="danger">{{ __('Hapus Akun') }}</flux:button>
        </flux:modal.trigger>
    </section>

    <flux:modal name="confirm-account-deletion" :show="$errors->has('delete_password')" focusable class="max-w-lg">
        <form wire:submit="confirmAccountDeletion" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Yakin ingin menghapus akun?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Akun akan dihapus permanen dalam 30 hari. Masuk kembali sebelum itu untuk membatalkan.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="delete_password" :label="__('Password')" type="password" viewable />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Batal') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" type="submit">{{ __('Hapus Akun') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
