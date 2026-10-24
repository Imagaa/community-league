<?php

use App\Actions\Booking\CreateBooking;
use App\Exceptions\BookingException;
use App\Exceptions\DuplicateBookingException;
use App\Models\GameMatch;
use App\Models\User;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public GameMatch $match;

    public string $guest_name = '';
    public string $guest_email = '';
    public string $guest_phone = '';
    public bool $terms = false;

    /** Modal "pastikan email benar" sebelum booking tamu dibuat. */
    public bool $showEmailConfirm = false;

    public bool $duplicateBooking = false;

    /** Tamu memilih "Beli tanpa login" -- baru form data tamu ditampilkan. */
    public bool $guestMode = false;

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $this->guest_name = $user->name ?? '';
        $this->guest_phone = $user->phone ?? '';
    }

    #[Computed]
    public function maxQuantity(): int
    {
        return min(CreateBooking::MAX_QUANTITY, $this->match->remainingSlots());
    }

    public function buyAuthenticated(CreateBooking $createBooking)
    {
        /** @var User $user */
        $user = Auth::user();

        $this->guest_phone = WhatsApp::local($this->guest_phone);
        $this->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_phone' => ['required', 'regex:'.WhatsApp::LOCAL_PATTERN],
        ], $this->messages(), ['guest_name' => 'nama', 'guest_phone' => 'nomor WhatsApp']);

        // Nama bisa diubah di sini, WA diminta sekali -- keduanya disimpan ke akun.
        $user->update(['name' => trim($this->guest_name), 'phone' => $this->guest_phone]);

        return $this->book($createBooking, ['user_id' => $user->id]);
    }

    /**
     * Ke halaman masuk (ada tautan daftar di sana). Setelah masuk -- email, Google, atau daftar
     * baru -- pembeli kembali ke halaman match ini dan form beli versi akun langsung tampil.
     */
    public function loginToBuy()
    {
        $back = route('match.show', $this->match).'#beli';
        session([
            'url.intended' => $back,
            'purchase_intent' => ['match_id' => $this->match->id],
        ]);

        return $this->redirect(route('login'));
    }

    public function startGuest(): void
    {
        $this->guestMode = true;
    }

    public function confirmGuest(): void
    {
        $this->guest_phone = WhatsApp::local($this->guest_phone);
        $this->guest_email = trim($this->guest_email);

        $this->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_phone' => ['required', 'regex:'.WhatsApp::LOCAL_PATTERN],
            'guest_email' => ['required', 'email', 'max:255'],
            'terms' => ['accepted'],
        ], $this->messages(), ['guest_name' => 'nama lengkap', 'guest_phone' => 'nomor WhatsApp', 'guest_email' => 'email']);

        $this->showEmailConfirm = true;
    }

    public function buyAsGuest(CreateBooking $createBooking)
    {
        $this->confirmGuest();
        $this->showEmailConfirm = false;

        return $this->book($createBooking, [
            'guest_name' => trim($this->guest_name),
            'guest_email' => $this->guest_email,
            'guest_phone' => $this->guest_phone,
        ]);
    }

    /** @param  array<string, mixed>  $who */
    private function book(CreateBooking $createBooking, array $who)
    {
        $this->duplicateBooking = false;

        try {
            $booking = $createBooking->handle($this->match, $who + [
                'quantity' => 1,
                'terms_version' => User::TERMS_VERSION,
            ]);
        } catch (DuplicateBookingException $e) {
            $this->duplicateBooking = true;
            $this->addError('booking', $e->getMessage());

            return null;
        } catch (BookingException $e) {
            $this->addError('booking', $e->getMessage());

            return null;
        }

        return $this->redirect($booking->urlFor('booking.payment'));
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'guest_phone.regex' => 'Nomor WhatsApp harus diawali 08 dan terdiri dari 10-14 angka.',
            'terms.accepted' => 'Anda harus menyetujui Syarat & Ketentuan.',
        ];
    }
}; ?>

@php
    $inputClass = 'rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-900 dark:border-navy-700 dark:bg-navy-950 dark:text-white';
    $primary = 'w-full rounded-lg bg-red-600 py-2.5 text-center text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60 dark:bg-orange-600 dark:hover:bg-orange-700';
@endphp
<section id="beli" class="scroll-mt-24 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
    @error('booking')
        <p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600 dark:bg-red-950 dark:text-red-400">
            {{ $message }}
            @if ($duplicateBooking && auth()->check())
                <a href="{{ route('tiket-saya') }}" wire:navigate class="font-semibold underline">{{ __('Lihat di sini') }}</a>.
            @endif
        </p>
    @enderror

    @if ($this->maxQuantity < 1)
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Match ini sudah penuh.') }}</p>
    @elseif (! auth()->check() && ! $guestMode)
        {{-- Tamu memilih dulu: masuk (data terisi otomatis, tiket tersimpan di akun) atau lanjut tanpa login. --}}
        <div class="flex flex-col gap-3">
            <button type="button" wire:click="loginToBuy" class="{{ $primary }}">
                {{ __('Masuk untuk beli') }}
            </button>
            <p class="text-center text-xs text-zinc-500 dark:text-zinc-400">{{ __('Belum punya akun? Daftar dari halaman masuk.') }}</p>
            <button type="button" wire:click="startGuest" class="mx-auto text-center text-xs text-zinc-500 underline dark:text-zinc-400">
                {{ __('Beli tanpa login') }}
            </button>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @guest
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Beli tanpa login') }}</p>
                    <button type="button" wire:click="$set('guestMode', false)" class="text-xs text-zinc-500 underline">{{ __('Kembali') }}</button>
                </div>
            @endguest

            <label class="flex flex-col gap-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Nama lengkap') }}
                <input type="text" wire:model="guest_name" autocomplete="name" class="{{ $inputClass }}" />
            </label>
            @error('guest_name') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            <label class="flex flex-col gap-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Nomor WhatsApp') }}
                <input type="tel" wire:model="guest_phone" inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" class="{{ $inputClass }}" />
            </label>
            @error('guest_phone') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            @auth
                <button type="button" wire:click="buyAuthenticated" wire:loading.attr="disabled" class="{{ $primary }}">
                    {{ __('Beli') }}
                </button>
            @else
                <label class="flex flex-col gap-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Email') }}
                    <input type="email" wire:model="guest_email" autocomplete="email" class="{{ $inputClass }}" />
                    <span class="text-xs text-zinc-500">{{ __('Nota & e-ticket dikirim ke email ini.') }}</span>
                </label>
                @error('guest_email') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <label class="flex cursor-pointer items-start gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <input type="checkbox" wire:model="terms" class="peer sr-only" />
                    <span class="mt-0.5 flex size-[1.125rem] shrink-0 items-center justify-center rounded-[.3rem] border border-zinc-300 text-transparent peer-checked:border-red-600 peer-checked:bg-red-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-red-500 dark:border-navy-700 dark:bg-navy-950 dark:peer-checked:border-orange-600 dark:peer-checked:bg-orange-600">
                        <flux:icon name="check" variant="micro" />
                    </span>
                    <span>
                        {{ __('Saya menyetujui') }}
                        <a href="{{ route('legal.terms') }}" target="_blank" class="text-red-600 underline dark:text-orange-400">{{ __('Syarat & Ketentuan') }}</a>
                    </span>
                </label>
                @error('terms') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <button type="button" wire:click="confirmGuest" class="{{ $primary }}">
                    {{ __('Beli') }}
                </button>

                <flux:modal wire:model="showEmailConfirm" class="max-w-sm">
                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Pastikan email benar') }}</flux:heading>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Nota & e-ticket hanya dikirim ke:') }}</p>
                        <p class="rounded-lg bg-zinc-100 px-3 py-2 text-center font-semibold break-all text-zinc-900 dark:bg-navy-950 dark:text-white">{{ $guest_email }}</p>
                        <div class="flex flex-col gap-2">
                            <flux:button variant="primary" wire:click="buyAsGuest">{{ __('Ya, email sudah benar') }}</flux:button>
                            <flux:button variant="ghost" wire:click="$set('showEmailConfirm', false)">{{ __('Ubah email') }}</flux:button>
                        </div>
                    </div>
                </flux:modal>
            @endauth
        </div>
    @endif
</section>
