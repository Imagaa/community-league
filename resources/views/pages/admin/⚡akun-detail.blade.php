<?php

use App\Models\Booking;
use App\Models\User;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Http\Middleware\EnsureAdmin;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Detail Akun')] class extends Component {
    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    public const STATUS_LABELS = [
        'pending_payment' => 'Menunggu bayar',
        'awaiting_verification' => 'Menunggu verifikasi',
        'paid' => 'Lunas',
        'rejected' => 'Ditolak',
        'cancelled' => 'Batal',
        'expired' => 'Kedaluwarsa',
    ];

    public User $user;

    public string $name = '';
    public string $phone = '';
    public string $notice = '';

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->phone = (string) $user->phone;
    }

    /** @return Collection<int, Booking> */
    #[Computed]
    public function bookings(): Collection
    {
        return $this->user->bookings()->with('match')->latest()->get();
    }

    public function save(): void
    {
        $this->phone = WhatsApp::local($this->phone);
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:'.WhatsApp::LOCAL_PATTERN],
        ], ['phone.regex' => 'Nomor WhatsApp harus diawali 08 dan terdiri dari 10-14 angka.']);

        $this->user->update(['name' => trim($this->name), 'phone' => $this->phone ?: null]);
        $this->notice = 'Perubahan tersimpan.';
    }

    public function delete()
    {
        abort_if($this->user->is(Auth::user()), 403, 'Tidak bisa menghapus akun sendiri.');

        $name = $this->user->name;
        // Booking tetap ada (user_id jadi null lewat FK nullOnDelete) supaya slot & catatan bayar tidak hilang.
        $this->user->delete();

        session()->flash('status', "Akun {$name} dihapus.");

        return $this->redirectRoute('admin.akun', navigate: true);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-5" x-data="{ confirming: false }">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.akun') }}" wire:navigate class="rounded-full bg-navy-900 p-2 text-zinc-400 hover:text-white" aria-label="Kembali">
            <flux:icon name="arrow-left" class="size-5" />
        </a>
        <div class="min-w-0">
            <h1 class="truncate text-xl font-bold text-white">{{ $user->name }}</h1>
            <p class="truncate text-xs text-zinc-400">{{ $user->email }} · daftar {{ $user->created_at?->timezone(config('app.display_timezone'))->translatedFormat('d M Y') }}</p>
        </div>
    </div>

    @if ($notice)
        <p class="rounded-xl border border-emerald-800 bg-emerald-950 px-4 py-3 text-emerald-300">{{ $notice }}</p>
    @endif

    <form wire:submit="save" class="space-y-4 rounded-2xl border border-navy-800 bg-navy-900 p-4">
        <x-admin.field label="Nama" name="name">
            <input type="text" wire:model="name" class="admin-input">
        </x-admin.field>
        <x-admin.field label="Nomor WhatsApp" name="phone">
            <input type="tel" wire:model="phone" inputmode="tel" class="admin-input" placeholder="08xxxxxxxxxx">
        </x-admin.field>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-xs font-bold text-white hover:bg-orange-700">Simpan</button>
            @if ($user->phone)
                <a href="{{ WhatsApp::url($user->phone) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-navy-700 px-4 py-2 text-xs text-zinc-300 hover:bg-navy-800">
                    <flux:icon name="chat-bubble-left-right" variant="micro" /> WhatsApp
                </a>
            @endif
        </div>
    </form>

    <section class="space-y-2">
        <h2 class="text-sm font-semibold text-zinc-400">Riwayat booking ({{ $this->bookings->count() }})</h2>
        @forelse ($this->bookings as $booking)
            <div class="flex items-center justify-between gap-3 rounded-xl border border-navy-800 bg-navy-900 px-4 py-3">
                <div class="min-w-0">
                    <p class="truncate font-medium text-white">{{ $booking->match->title }}</p>
                    <p class="text-xs text-zinc-400">{{ $booking->booking_code }} · {{ $booking->created_at?->timezone(config('app.display_timezone'))->translatedFormat('d M Y H.i') }} · {{ Money::rupiah($booking->paidAmount()) }}</p>
                </div>
                <span @class([
                    'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                    'bg-emerald-950 text-emerald-300' => $booking->status === 'paid',
                    'bg-amber-950 text-amber-300' => in_array($booking->status, ['pending_payment', 'awaiting_verification'], true),
                    'bg-navy-800 text-zinc-400' => in_array($booking->status, ['rejected', 'cancelled', 'expired'], true),
                ])>{{ $this::STATUS_LABELS[$booking->status] ?? $booking->status }}</span>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-navy-800 p-6 text-center text-zinc-500">Belum pernah booking.</p>
        @endforelse
    </section>

    @unless ($user->is(auth()->user()))
        <section class="rounded-2xl border border-red-900/60 p-4">
            <button type="button" x-show="! confirming" @click="confirming = true" class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-400 hover:underline">
                <flux:icon name="trash" variant="micro" /> Hapus akun ini
            </button>
            <div x-show="confirming" x-cloak class="space-y-3 text-xs text-red-200">
                <p>Hapus permanen akun <strong>{{ $user->email }}</strong>? Poin & membership ikut hangus. Riwayat booking tetap tersimpan tanpa nama akun. Tindakan ini tidak bisa dibatalkan.</p>
                <div class="flex gap-2">
                    <button type="button" wire:click="delete" class="rounded-lg bg-red-600 px-3 py-1.5 font-bold text-white">Ya, hapus permanen</button>
                    <button type="button" @click="confirming = false" class="rounded-lg border border-navy-700 px-3 py-1.5 text-zinc-300">Batal</button>
                </div>
            </div>
        </section>
    @endunless
</div>
