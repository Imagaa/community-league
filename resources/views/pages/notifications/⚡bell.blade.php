<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Lonceng notifikasi di header.
 * `variant` cuma soal styling (ikon putih di header merah/navy mobile vs ikon merah/orange di
 * navbar desktop putih/navy) -- BUKAN prop perilaku, komponennya sama persis di kedua tempat.
 *
 * Belum ada satu pun event notifikasi yang benar-benar memanggil ->notify() -- disengaja, lihat
 * spec. Dropdown ini kosong ("Belum ada notifikasi.") sampai event nyata di-wire belakangan.
 */
new class extends Component {
    public string $variant = 'mobile';

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    #[Computed]
    public function recent(): \Illuminate\Support\Collection
    {
        return Auth::user()->notifications()->latest()->limit(10)->get();
    }

    /**
     * Tandai satu notifikasi dibaca, lalu navigasi ke `data.url`-nya kalau ada -- klik baris
     * notifikasi cuma satu aksi, bukan "tandai dibaca" dan "buka" terpisah. Selalu lewat relasi
     * Auth::user()->notifications(), bukan DatabaseNotification::find() langsung -- supaya id
     * milik user lain 404 (findOrFail), tidak bisa ditandai/dibaca user yang salah.
     */
    public function markAsRead(string $id): void
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($url = $notification->data['url'] ?? null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }
}; ?>

<div
    wire:poll.30s
    x-data="{ open: false }"
    x-on:visibilitychange.document="if (document.visibilityState === 'visible') { $wire.$refresh() }"
    class="relative"
>
    <button
        type="button"
        x-on:click="open = !open"
        class="relative flex size-9 shrink-0 items-center justify-center rounded-full {{ $variant === 'mobile' ? 'text-white hover:bg-white/10' : 'text-red-600 hover:bg-zinc-100 dark:text-orange-500 dark:hover:bg-navy-800' }}"
        aria-label="{{ __('Notifikasi') }}"
    >
        <flux:icon name="bell" class="size-5" />

        @if ($this->unreadCount > 0)
            {{--
                Badge HARUS beda warna per variant, bukan warna tetap -- kalau dipaksa satu warna
                (misal red-600 di kedua tempat), dia MENYATU total dengan latar mobile-header yang
                juga red-600 (dikonfirmasi lewat computed style sungguhan: keduanya persis sama,
                badge jadi tak kelihatan sama sekali). Mobile: putih di atas latar
                merah/navy-900 header. Desktop: merah/oranye di atas latar putih/navy-900 navbar
                (ini yang tetap sama seperti desain awal, cuma mobile yang perlu dibalik).
            --}}
            <span class="absolute right-1 top-1 flex size-4 items-center justify-center rounded-full text-[10px] font-bold {{ $variant === 'mobile' ? 'bg-white text-red-600' : 'bg-red-600 text-white dark:bg-orange-600' }}" data-test="notification-badge">
                {{ $this->unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-on:click.outside="open = false"
        x-cloak
        class="absolute right-0 z-20 mt-2 flex w-80 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white text-zinc-900 shadow-lg dark:border-navy-800 dark:bg-navy-900 dark:text-white"
    >
        <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-2.5 dark:border-navy-800">
            <span class="text-sm font-semibold">{{ __('Notifikasi') }}</span>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-semibold text-red-600 hover:underline dark:text-orange-400">
                    {{ __('Tandai semua dibaca') }}
                </button>
            @endif
        </div>

        <div class="flex max-h-96 flex-col divide-y divide-zinc-100 overflow-y-auto dark:divide-navy-800">
            @forelse ($this->recent as $notification)
                <button
                    type="button"
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="flex flex-col items-start gap-0.5 px-4 py-3 text-left text-sm hover:bg-zinc-50 dark:hover:bg-navy-800 {{ $notification->read_at === null ? 'bg-red-50 dark:bg-navy-800/60' : '' }}"
                >
                    <span class="font-semibold">{{ $notification->data['title'] ?? '' }}</span>
                    <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $notification->data['body'] ?? '' }}</span>
                    <span class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ $notification->created_at->diffForHumans() }}</span>
                </button>
            @empty
                <p class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('Belum ada notifikasi.') }}</p>
            @endforelse
        </div>
    </div>
</div>
