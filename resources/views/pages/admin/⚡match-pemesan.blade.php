<?php

use App\Http\Middleware\EnsureAdmin;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Pemesan')] class extends Component {
    /** Urutan grup tampil; status lain (ditolak/batal/kedaluwarsa) dikumpulkan di "Tidak aktif". */
    public const GROUPS = [
        'paid' => 'Lunas',
        'awaiting_verification' => 'Menunggu verifikasi',
        'pending_payment' => 'Menunggu bayar',
        'inactive' => 'Tidak aktif',
    ];

    public GameMatch $match;

    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    public function regenerateScanner(): void
    {
        $this->match->regenerateScannerToken();
        session()->flash('scanner', 'Link scanner baru dibuat. Link lama sudah tidak berlaku.');
    }

    public function mount(GameMatch $match): void
    {
        $this->match = $match->load('venue');
    }

    /** @return Collection<string, Collection<int, Booking>> */
    #[Computed]
    public function groups(): Collection
    {
        return $this->match->bookings()
            ->with('user')
            ->oldest()
            ->get()
            ->groupBy(fn (Booking $b) => array_key_exists($b->status, self::GROUPS) ? $b->status : 'inactive');
    }

    /** Daftar bernomor siap tempel ke grup WhatsApp. */
    #[Computed]
    public function paidList(): string
    {
        $tz = config('app.display_timezone');

        return $this->match->title.' -- '.$this->match->starts_at->timezone($tz)->translatedFormat('d M Y H.i')."\n"
            .($this->groups->get('paid') ?? collect())
                ->values()
                ->map(fn (Booking $b, int $i) => ($i + 1).'. '.$b->buyerName())
                ->implode("\n");
    }
}; ?>

<div class="space-y-5">
    <div class="flex items-start gap-3">
        <a href="{{ route('admin.match') }}" wire:navigate class="rounded-full bg-navy-900 p-2 text-zinc-400 hover:text-white" aria-label="Kembali">
            <flux:icon name="arrow-left" class="size-5" />
        </a>
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-xl font-bold text-white">{{ $match->title }}</h1>
            <p class="text-xs text-zinc-400">
                {{ $match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('D, d M Y · H.i') }} · {{ $match->venue->name }}
            </p>
        </div>
        <x-share-button compact :url="\App\Support\PublicUrl::route('match.show', $match)" :title="$match->title"
                        :text="$match->title.' · '.$match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('D, d M Y H.i').' WIB · '.$match->venue->name.'. Pesan slotmu:'" />
        <a href="{{ route('admin.match.edit', $match) }}" wire:navigate class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white" title="Ubah match">
            <flux:icon name="pencil-square" class="size-5" />
        </a>
    </div>

    @php
        $lunas = $this->groups->get('paid')?->sum('quantity') ?? 0;
        $hadir = $this->groups->get('paid')?->whereNotNull('checked_in_at')->sum('quantity') ?? 0;
        $menunggu = ($this->groups->get('awaiting_verification')?->sum('quantity') ?? 0) + ($this->groups->get('pending_payment')?->sum('quantity') ?? 0);
        $pendapatan = $this->groups->get('paid')?->reject(fn ($b) => $b->is_sandbox)->sum(fn ($b) => $b->paidAmount()) ?? 0;
    @endphp
    <div class="grid grid-cols-3 gap-2 text-center">
        <div class="rounded-xl border border-navy-800 bg-navy-900 p-3">
            <p class="text-xl font-bold text-emerald-400">{{ $lunas }}<span class="text-sm text-zinc-500">/{{ $match->capacity }}</span></p>
            <p class="text-[11px] text-zinc-400">Slot lunas · {{ $hadir }} hadir</p>
        </div>
        <div class="rounded-xl border border-navy-800 bg-navy-900 p-3">
            <p class="text-xl font-bold text-amber-400">{{ $menunggu }}</p>
            <p class="text-[11px] text-zinc-400">Menahan slot</p>
        </div>
        <div class="rounded-xl border border-navy-800 bg-navy-900 p-3">
            <p class="truncate text-base font-bold text-white sm:text-xl">{{ Money::rupiah($pendapatan) }}</p>
            <p class="text-[11px] text-zinc-400">Diterima (tanpa sandbox)</p>
        </div>
    </div>

    @if ($match->status !== 'cancelled')
        @php $scannerUrl = $match->scannerUrl(); @endphp
        <section id="scanner" class="space-y-2 rounded-2xl border border-navy-800 bg-navy-900 p-4" x-data="{ copied: false, regen: false }">
            <div class="flex items-center gap-2">
                <flux:icon name="qr-code" class="size-5 text-orange-400" />
                <h2 class="font-semibold text-white">Scanner check-in</h2>
            </div>
            <p class="text-xs text-zinc-400">Bagikan ke panitia. Siapa pun yang punya link ini bisa memindai e-ticket match ini tanpa login.</p>
            @if (session('scanner')) <p class="text-xs text-emerald-400">{{ session('scanner') }}</p> @endif
            <div class="flex gap-2">
                <input type="text" readonly value="{{ $scannerUrl }}" @focus="$el.select()" class="admin-input min-w-0 py-2 font-mono text-xs">
                <button type="button" class="shrink-0 rounded-lg border border-navy-700 px-3 text-zinc-300 hover:bg-navy-800" aria-label="Salin"
                        @click="navigator.clipboard.writeText(@js($scannerUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                    <flux:icon x-show="! copied" name="clipboard-document" class="size-5" />
                    <flux:icon x-show="copied" x-cloak name="check" class="size-5 text-emerald-400" />
                </button>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ \App\Support\WhatsApp::url('', 'Link scanner check-in '.$match->title.': '.$scannerUrl) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-[#25D366] px-3 py-2 text-xs font-bold text-white">Kirim lewat WhatsApp</a>
                <a href="{{ $scannerUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-navy-700 px-3 py-2 text-xs text-zinc-200 hover:bg-navy-800">
                    <flux:icon name="camera" variant="micro" /> Buka scanner
                </a>
                <button type="button" x-show="! regen" @click="regen = true" class="rounded-lg px-3 py-2 text-xs text-zinc-400 underline">Buat link baru</button>
                <span x-show="regen" x-cloak class="flex items-center gap-2 text-xs text-red-200">
                    Link lama berhenti berfungsi.
                    <button type="button" wire:click="regenerateScanner" class="rounded-lg bg-red-600 px-3 py-1.5 font-bold text-white">Ya, buat baru</button>
                    <button type="button" @click="regen = false" class="text-zinc-400 underline">Batal</button>
                </span>
            </div>
        </section>
    @endif

    @if ($lunas > 0)
        <div x-data="{ copied: false }">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-navy-700 px-3 py-2 text-xs text-zinc-300 hover:bg-navy-800"
                    @click="navigator.clipboard.writeText(@js($this->paidList)); copied = true; setTimeout(() => copied = false, 2000)">
                <flux:icon x-show="! copied" name="clipboard-document" variant="micro" />
                <flux:icon x-show="copied" x-cloak name="check" variant="micro" class="text-emerald-400" />
                <span x-text="copied ? 'Tersalin' : 'Salin daftar nama lunas'"></span>
            </button>
        </div>
    @endif

    @foreach ($this::GROUPS as $key => $label)
        @php $items = $this->groups->get($key); @endphp
        @if ($items?->isNotEmpty())
            <section class="space-y-2">
                <h2 class="text-sm font-semibold text-zinc-400">{{ $label }} ({{ $items->count() }})</h2>
                <div class="divide-y divide-navy-800 overflow-hidden rounded-2xl border border-navy-800 bg-navy-900">
                    @foreach ($items as $booking)
                        <div wire:key="b-{{ $booking->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-white">
                                    {{ $booking->buyerName() ?: 'Akun terhapus' }}
                                    <span class="ml-1 rounded-full bg-navy-800 px-1.5 py-0.5 text-[10px] font-normal text-zinc-400">{{ $booking->user_id ? 'akun' : 'tamu' }}</span>
                                    @if ($booking->is_sandbox)
                                        <span class="ml-1 rounded-full bg-amber-950 px-1.5 py-0.5 text-[10px] font-semibold text-amber-300">sandbox</span>
                                    @endif
                                </p>
                                <p class="truncate text-xs text-zinc-400">{{ $booking->buyerEmail() }}</p>
                                <p class="text-xs text-zinc-500">
                                    {{ $booking->booking_code }} · {{ Money::rupiah($booking->paidAmount()) }}
                                    @if ($booking->checked_in_at)
                                        · <span class="font-semibold text-emerald-400">&check; hadir {{ $booking->checked_in_at->timezone(config('app.display_timezone'))->translatedFormat('H.i') }}</span>
                                    @endif
                                    @if ($booking->paid_at)
                                        · lunas {{ $booking->paid_at->timezone(config('app.display_timezone'))->translatedFormat('d M H.i') }}
                                    @elseif ($key === 'inactive')
                                        · {{ $booking->status === 'rejected' ? 'ditolak: '.$booking->rejection_reason : ($booking->status === 'expired' ? 'kedaluwarsa' : 'batal') }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                @if ($booking->buyerPhone())
                                    <a href="{{ WhatsApp::url($booking->buyerPhone()) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs text-orange-400 hover:bg-navy-800">
                                        <flux:icon name="chat-bubble-left-right" variant="micro" /> {{ $booking->buyerPhone() }}
                                    </a>
                                @endif
                                @if ($booking->payment_proof_path)
                                    <a href="{{ route('admin.bukti', $booking) }}" target="_blank" class="rounded-lg p-1.5 text-zinc-400 hover:bg-navy-800 hover:text-white" title="Lihat bukti bayar">
                                        <flux:icon name="photo" class="size-4" />
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    @if ($this->groups->isEmpty())
        <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-navy-800 p-10 text-center text-zinc-500">
            <flux:icon name="users" class="size-10" />
            Belum ada pemesan untuk match ini.
        </div>
    @endif
</div>
