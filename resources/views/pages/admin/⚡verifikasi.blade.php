<?php

use App\Actions\Booking\VerifyPayment;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use App\Http\Middleware\EnsureAdmin;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] #[Title('Verifikasi')] class extends Component {
    use WithPagination;

    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    public const TABS = [
        'menunggu' => 'awaiting_verification',
        'disetujui' => 'paid',
        'ditolak' => 'rejected',
    ];

    #[Url]
    public string $tab = 'menunggu';

    public string $notice = '';

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'menunggu';
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, Booking> */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        $status = self::TABS[$this->tab] ?? 'awaiting_verification';

        return Booking::query()
            ->with('match.venue', 'user')
            ->where('status', $status)
            // Disetujui/ditolak: hanya yang lewat QRIS manual (punya bukti), bukan pembayaran Sumopod.
            ->whereNotNull('proof_uploaded_at')
            ->orderBy($status === 'awaiting_verification' ? 'proof_uploaded_at' : 'verified_at', $status === 'awaiting_verification' ? 'asc' : 'desc')
            ->paginate(20);
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        return ['menunggu' => Booking::where('status', 'awaiting_verification')->count()];
    }

    public function approve(int $id, VerifyPayment $verify): void
    {
        $this->decide(fn (Booking $b) => $verify->approve($b, Auth::user()), $id, 'Pembayaran disetujui, e-ticket terkirim.');
    }

    public function reject(int $id, string $reason, VerifyPayment $verify): void
    {
        $this->decide(fn (Booking $b) => $verify->reject($b, Auth::user(), $reason), $id, 'Pembayaran ditolak, slot dilepas.');
    }

    private function decide(callable $action, int $id, string $message): void
    {
        $this->resetErrorBag();
        $this->notice = '';

        try {
            $action(Booking::findOrFail($id));
            $this->notice = $message;
        } catch (BookingException $e) {
            $this->addError('verifikasi', $e->getMessage());
        }

        unset($this->bookings, $this->counts);
    }
}; ?>

<div class="space-y-4" x-data="{ zoom: null }">
    <h1 class="text-xl font-bold text-white">Verifikasi Pembayaran</h1>

    @if ($notice)
        <p class="rounded-xl border border-emerald-800 bg-emerald-950 px-4 py-3 text-emerald-300">{{ $notice }}</p>
    @endif
    @error('verifikasi')
        <p class="rounded-xl border border-red-900 bg-red-950 px-4 py-3 text-red-300">{{ $message }}</p>
    @enderror

    <div class="flex gap-2 overflow-x-auto">
        <x-admin.tab :active="$tab === 'menunggu'" wire:click="setTab('menunggu')">Menunggu ({{ $this->counts['menunggu'] }})</x-admin.tab>
        <x-admin.tab :active="$tab === 'disetujui'" wire:click="setTab('disetujui')">Disetujui</x-admin.tab>
        <x-admin.tab :active="$tab === 'ditolak'" wire:click="setTab('ditolak')">Ditolak</x-admin.tab>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        @forelse ($this->bookings as $booking)
            <article wire:key="b-{{ $booking->id }}" class="flex gap-3 rounded-2xl border border-navy-800 bg-navy-900 p-3"
                     x-data="{ confirming: false, rejecting: false, reason: '' }">
                <button type="button" class="h-28 w-20 shrink-0 overflow-hidden rounded-lg bg-navy-800"
                        @click="zoom = '{{ route('admin.bukti', $booking) }}'" aria-label="Perbesar bukti">
                    <img src="{{ route('admin.bukti', $booking) }}" alt="Bukti bayar {{ $booking->booking_code }}" loading="lazy" class="size-full object-cover">
                </button>

                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex items-start justify-between gap-2">
                        <p class="truncate font-semibold text-white">{{ $booking->buyerName() }}</p>
                        <span class="shrink-0 rounded-full bg-navy-800 px-2 py-0.5 text-[10px] text-zinc-400">{{ $booking->user_id ? 'akun' : 'tamu' }}</span>
                    </div>
                    <p class="truncate text-xs text-zinc-400">
                        {{ $booking->match->title }} · {{ $booking->match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('d M H.i') }}
                    </p>
                    <p class="text-xs text-zinc-400">
                        <span class="text-base font-bold text-emerald-400">{{ Money::rupiah($booking->amountToPay()) }}</span>
                        · {{ $booking->booking_code }} · {{ $booking->proof_uploaded_at?->diffForHumans(short: true) }}
                    </p>
                    @if ($booking->buyerPhone())
                        <a href="{{ WhatsApp::url($booking->buyerPhone()) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs text-orange-400 hover:underline">
                            <flux:icon name="chat-bubble-left-right" variant="micro" /> {{ $booking->buyerPhone() }}
                        </a>
                    @endif

                    @if ($booking->status === 'awaiting_verification')
                        <div class="flex gap-2 pt-1" x-show="! confirming && ! rejecting">
                            <button type="button" @click="confirming = true" class="flex-1 rounded-lg bg-emerald-600 py-2 text-xs font-bold text-white hover:bg-emerald-700">Setujui</button>
                            <button type="button" @click="rejecting = true" class="flex-1 rounded-lg border border-orange-800 py-2 text-xs font-bold text-orange-400 hover:bg-orange-950">Tolak</button>
                        </div>
                        <div class="flex gap-2 pt-1" x-show="confirming" x-cloak>
                            <button type="button" wire:click="approve({{ $booking->id }})" wire:loading.attr="disabled" class="flex-1 rounded-lg bg-emerald-600 py-2 text-xs font-bold text-white disabled:opacity-50">Ya, setujui &amp; kirim e-ticket</button>
                            <button type="button" @click="confirming = false" class="rounded-lg border border-navy-700 px-3 text-xs text-zinc-400">Batal</button>
                        </div>
                        <div class="space-y-2 pt-1" x-show="rejecting" x-cloak>
                            <div class="flex flex-wrap gap-1">
                                @foreach (['Nominal tidak sesuai', 'Bukti tidak jelas', 'Dana belum masuk'] as $quick)
                                    <button type="button" @click="reason = '{{ $quick }}'" class="rounded-full border border-navy-700 px-2 py-0.5 text-[11px] text-zinc-300 hover:border-orange-600">{{ $quick }}</button>
                                @endforeach
                            </div>
                            <textarea x-model="reason" rows="2" placeholder="Alasan penolakan (dikirim ke pembeli)"
                                      class="w-full rounded-lg border border-navy-700 bg-navy-950 px-2 py-1.5 text-xs text-white focus:border-orange-500 focus:outline-none"></textarea>
                            <div class="flex gap-2">
                                <button type="button" :disabled="reason.trim() === ''" @click="$wire.reject({{ $booking->id }}, reason)" class="flex-1 rounded-lg bg-orange-600 py-2 text-xs font-bold text-white disabled:opacity-40">Tolak pembayaran</button>
                                <button type="button" @click="rejecting = false" class="rounded-lg border border-navy-700 px-3 text-xs text-zinc-400">Batal</button>
                            </div>
                        </div>
                    @elseif ($booking->status === 'rejected')
                        <p class="text-xs text-orange-300">Ditolak: {{ $booking->rejection_reason }}</p>
                    @else
                        <p class="text-xs text-emerald-400">Disetujui {{ $booking->verified_at?->timezone(config('app.display_timezone'))->translatedFormat('d M H.i') }}</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="col-span-full flex flex-col items-center gap-2 rounded-2xl border border-dashed border-navy-800 p-10 text-center text-zinc-500">
                <flux:icon name="check-badge" class="size-10" />
                Tidak ada pembayaran di tab ini.
            </div>
        @endforelse
    </div>

    {{ $this->bookings->links() }}

    <div x-show="zoom" x-cloak x-transition.opacity @click="zoom = null" @keydown.escape.window="zoom = null"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4">
        <img :src="zoom" alt="Bukti bayar" class="max-h-full max-w-full rounded-lg">
        <button type="button" class="absolute top-4 right-4 rounded-full bg-white/10 p-2 text-white" aria-label="Tutup">
            <flux:icon name="x-mark" class="size-6" />
        </button>
    </div>
</div>
