<?php

use App\Actions\Match\PublishDocumentation;
use App\Models\GameMatch;
use App\Support\Money;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use App\Http\Middleware\EnsureAdmin;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] #[Title('Match')] class extends Component {
    use WithPagination;

    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    #[Url]
    public string $tab = 'aktif';

    public string $notice = '';

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, GameMatch::ADMIN_TABS) ? $tab : 'aktif';
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, GameMatch> */
    #[Computed]
    public function matches(): LengthAwarePaginator
    {
        // Yang akan datang urut terdekat dulu; selesai/batal urut terbaru dulu.
        $terbaru = in_array($this->tab, ['selesai', 'batal'], true);

        return GameMatch::query()
            ->with('venue')
            ->withTakenSlots()
            ->withCount('bookings')
            ->adminTab($this->tab)
            ->orderBy('starts_at', $terbaru ? 'desc' : 'asc')
            ->paginate(20);
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        return collect(GameMatch::ADMIN_TABS)
            ->map(fn ($label, string $tab) => GameMatch::query()->adminTab($tab)->count())
            ->all();
    }

    /** Simpan link folder Drive dokumentasi (tab Selesai) -- lihat PublishDocumentation. */
    public function saveDrive(int $id, string $url, PublishDocumentation $publish): void
    {
        $match = GameMatch::findOrFail($id);
        $this->resetErrorBag();

        try {
            $result = $publish->handle($match, $url);
        } catch (InvalidArgumentException $e) {
            $this->addError('drive.'.$id, $e->getMessage());

            return;
        }

        $this->notice = "Dokumentasi {$match->title} tersimpan: {$result['photos']} foto di web"
            .($result['emailed'] > 0 ? ", email terima kasih terkirim ke {$result['emailed']} pembeli" : '').'.';

        if ($result['warning']) {
            $this->addError('drive.'.$id, $result['warning']);
        }

        unset($this->matches);
    }

    /**
     * Hapus permanen -- hanya untuk match yang sudah dibatalkan. Booking-nya ikut terhapus
     * (FK cascade), begitu juga file bukti bayarnya.
     */
    public function delete(int $id): void
    {
        $match = GameMatch::findOrFail($id);

        if ($match->status !== 'cancelled') {
            $this->notice = 'Hanya match yang sudah dibatalkan yang bisa dihapus.';

            return;
        }

        $match->bookings()->whereNotNull('payment_proof_path')->pluck('payment_proof_path')
            ->each(fn (string $path) => Storage::disk('proofs')->delete($path));

        $match->delete();
        $this->notice = "{$match->title} dihapus permanen.";

        unset($this->matches, $this->counts);
    }

    public function publishNow(int $id): void
    {
        $match = GameMatch::findOrFail($id);
        abort_unless($match->status === 'draft', 422);

        $match->publishNow();
        $this->notice = $match->public_opens_at->isFuture()
            ? "{$match->title} terbit. Member bisa beli sekarang, publik mulai ".$match->public_opens_at->timezone(config('app.display_timezone'))->translatedFormat('H.i').' WIB.'
            : "{$match->title} terbit dan bisa dibeli sekarang.";
        unset($this->matches, $this->counts);
    }

    /** $at dari input datetime-local, dalam WIB. */
    public function publishAt(int $id, string $at): void
    {
        $match = GameMatch::findOrFail($id);
        abort_unless($match->status === 'draft', 422);
        $this->resetErrorBag();

        try {
            $when = \Illuminate\Support\Carbon::parse($at, config('app.display_timezone'))->utc();
        } catch (\Throwable) {
            $this->addError('publish.'.$id, 'Waktu tidak valid.');

            return;
        }

        if ($when->isPast() || $when->gte($match->starts_at)) {
            $this->addError('publish.'.$id, 'Waktu terbit harus di masa depan dan sebelum match dimulai.');

            return;
        }

        $match->publishAt($when);
        $this->notice = "{$match->title} dijadwalkan: publik bisa beli ".$when->timezone(config('app.display_timezone'))->translatedFormat('d M H.i').' WIB.';
        unset($this->matches, $this->counts);
    }

    public function cancel(int $id): void
    {
        $match = GameMatch::findOrFail($id);

        if ($match->status !== 'cancelled') {
            $match->update(['status' => 'cancelled']);
            $match->notifyBookingsOfCancellation();
            $this->notice = "{$match->title} dibatalkan.";
        }

        unset($this->matches, $this->counts);
    }

}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-bold text-white">Match</h1>
        <a href="{{ route('admin.match.create') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg bg-orange-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-orange-700">
            <flux:icon name="plus" variant="micro" /> Match baru
        </a>
    </div>

    @if ($notice)
        <p class="rounded-xl border border-emerald-800 bg-emerald-950 px-4 py-3 text-emerald-300">{{ $notice }}</p>
    @endif

    <div class="flex gap-2 overflow-x-auto">
        @foreach (\App\Models\GameMatch::ADMIN_TABS as $key => $label)
            <x-admin.tab :active="$tab === $key" wire:click="setTab('{{ $key }}')">{{ $label }} ({{ $this->counts[$key] }})</x-admin.tab>
        @endforeach
    </div>

    <div class="space-y-2">
        @forelse ($this->matches as $match)
            <article wire:key="m-{{ $match->id }}" class="rounded-2xl border border-navy-800 bg-navy-900 p-4" x-data="{ confirming: false, deleting: false }">
                <a href="{{ route('admin.match.pemesan', $match) }}" wire:navigate class="block min-w-0 hover:opacity-90">
                    <p class="font-semibold text-white">
                        {{ $match->title }}
                        @if ($tab !== 'aktif')
                            <span @class([
                                'ml-1 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                'bg-red-950 text-red-300' => $tab === 'batal',
                                'bg-orange-950 text-orange-300' => $tab === 'penuh',
                                'bg-navy-800 text-zinc-300' => $tab === 'selesai',
                            ])>{{ \App\Models\GameMatch::ADMIN_TABS[$tab] }}</span>
                        @endif
                    </p>
                    <p class="mt-0.5 text-xs text-zinc-400">
                        {{ $match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('D, d M Y · H.i') }}
                        · {{ $match->venue->name }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-400">
                        <span class="font-semibold text-white">{{ (int) $match->taken_slots }}/{{ $match->capacity }}</span> slot terisi
                        · {{ Money::rupiah($match->price_regular) }}
                    </p>
                </a>

                @php $tz = config('app.display_timezone'); @endphp
                @if ($match->status === 'draft')
                    <div class="mt-3 space-y-2 rounded-xl border border-dashed border-navy-700 p-3" x-data="{ scheduling: false, at: '' }">
                        <p class="text-xs text-zinc-400"><span class="rounded-full bg-navy-800 px-2 py-0.5 font-semibold text-zinc-200">Draft</span> belum tampil di web.</p>
                        <div class="flex flex-wrap gap-2" x-show="! scheduling">
                            <button type="button" wire:click="publishNow({{ $match->id }})" class="rounded-lg bg-orange-600 px-3 py-2 text-xs font-bold text-white hover:bg-orange-700">Publish sekarang</button>
                            <button type="button" @click="scheduling = true" class="rounded-lg border border-navy-700 px-3 py-2 text-xs font-semibold text-zinc-200 hover:bg-navy-800">Publish terjadwal</button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2" x-show="scheduling" x-cloak>
                            <input type="datetime-local" x-model="at" class="admin-input w-auto py-2 text-xs [color-scheme:dark]">
                            <button type="button" :disabled="! at" @click="$wire.publishAt({{ $match->id }}, at)" class="rounded-lg bg-orange-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-40">Jadwalkan</button>
                            <button type="button" @click="scheduling = false" class="rounded-lg border border-navy-700 px-3 py-2 text-xs text-zinc-300">Batal</button>
                            <p class="w-full text-[11px] text-zinc-500">Waktu publik bisa membeli (WIB). Saat membership aktif, member bisa membeli lebih awal sesuai pengaturan di menu Membership.</p>
                        </div>
                        @error('publish.'.$match->id) <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                @elseif ($match->status === 'published' && $match->public_opens_at->isFuture())
                    <p class="mt-2 text-xs text-amber-300">
                        &#9201; Publik bisa beli {{ $match->public_opens_at->timezone($tz)->translatedFormat('d M H.i') }} WIB
                        @if ($match->member_opens_at->lt($match->public_opens_at) && \App\Models\MembershipSettings::enabled())
                            · member mulai {{ $match->member_opens_at->timezone($tz)->translatedFormat('d M H.i') }}
                        @endif
                    </p>
                @endif

                @if ($tab === 'selesai')
                    {{-- Dokumentasi: link folder Drive -> galeri foto di web + email terima kasih ke pembeli. --}}
                    <div class="mt-3 space-y-1.5" x-data="{ url: @js((string) $match->drive_folder_url), saving: false }">
                        <div class="flex gap-2">
                            <input type="url" x-model="url" placeholder="Tempel link folder Google Drive dokumentasi..." class="admin-input min-w-0 flex-1 py-2 text-xs">
                            <button type="button" :disabled="saving || url.trim() === ''"
                                    @click="saving = true; $wire.saveDrive({{ $match->id }}, url).finally(() => saving = false)"
                                    class="shrink-0 rounded-lg bg-orange-600 px-3 text-xs font-bold text-white hover:bg-orange-700 disabled:opacity-40">
                                <span x-show="! saving">{{ $match->documentation_sent_at ? 'Perbarui' : 'Simpan & kirim email' }}</span>
                                <span x-show="saving" x-cloak>Memproses...</span>
                            </button>
                        </div>
                        @error('drive.'.$match->id) <p class="text-xs text-amber-400">{{ $message }}</p> @enderror
                        @if ($match->documentation_sent_at)
                            <p class="text-xs text-emerald-400">
                                &check; Email terima kasih terkirim {{ $match->documentation_sent_at->timezone(config('app.display_timezone'))->translatedFormat('d M H.i') }}
                                · {{ count($match->drive_photos ?? []) }} foto tampil di web
                            </p>
                        @else
                            <p class="text-xs text-zinc-500">Folder Drive harus diset "Siapa saja yang memiliki link". Email hanya dikirim sekali ke pembeli lunas.</p>
                        @endif
                    </div>
                @endif

                {{-- Aksi di baris sendiri supaya teks detail di atas tidak terdesak di layar HP. --}}
                <div class="mt-3 flex items-center gap-1 border-t border-navy-800 pt-2">
                    <x-share-button compact :url="\App\Support\PublicUrl::route('match.show', $match)" :title="$match->title"
                                    :text="$match->title.' · '.$match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('D, d M Y H.i').' WIB · '.$match->venue->name.'. Pesan slotmu:'" />
                    <a href="{{ route('admin.match.pemesan', $match) }}" wire:navigate class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white" title="Pemesan">
                        <flux:icon name="users" class="size-5" />
                    </a>
                    @if ($match->status === 'published')
                        <a href="{{ route('admin.match.pemesan', $match) }}#scanner" wire:navigate class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white" title="Scanner check-in">
                            <flux:icon name="qr-code" class="size-5" />
                        </a>
                    @endif
                    <a href="{{ \App\Support\PublicUrl::route('match.show', $match) }}" target="_blank" class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white" title="Lihat di web">
                        <flux:icon name="arrow-top-right-on-square" class="size-5" />
                    </a>
                    <a href="{{ route('admin.match.edit', $match) }}" wire:navigate class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white" title="Ubah">
                        <flux:icon name="pencil-square" class="size-5" />
                    </a>
                    <span class="flex-1"></span>
                    @if ($match->status === 'cancelled')
                        <button type="button" @click="deleting = true" class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-red-400" title="Hapus permanen">
                            <flux:icon name="trash" class="size-5" />
                        </button>
                    @elseif (! $match->ends_at->isPast())
                        <button type="button" @click="confirming = true" class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-red-400" title="Batalkan">
                            <flux:icon name="x-circle" class="size-5" />
                        </button>
                    @endif
                </div>

                <div x-show="confirming" x-cloak class="mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-red-900 bg-red-950/50 p-3 text-xs text-red-200">
                    <span class="flex-1">Batalkan match ini? Pembeli ber-akun mendapat notifikasi. Pengembalian dana diurus manual.</span>
                    <button type="button" wire:click="cancel({{ $match->id }})" class="rounded-lg bg-red-600 px-3 py-1.5 font-bold text-white">Ya, batalkan</button>
                    <button type="button" @click="confirming = false" class="rounded-lg border border-navy-700 px-3 py-1.5 text-zinc-300">Tidak</button>
                </div>

                @if ($match->status === 'cancelled')
                    <div x-show="deleting" x-cloak class="mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-red-900 bg-red-950/50 p-3 text-xs text-red-200">
                        <span class="flex-1">
                            Hapus permanen dari daftar dan database?
                            @if ($match->bookings_count > 0)
                                <strong>{{ $match->bookings_count }} booking match ini beserta bukti bayarnya ikut terhapus.</strong>
                            @endif
                            Tidak bisa dibatalkan.
                        </span>
                        <button type="button" wire:click="delete({{ $match->id }})" class="rounded-lg bg-red-600 px-3 py-1.5 font-bold text-white">Ya, hapus</button>
                        <button type="button" @click="deleting = false" class="rounded-lg border border-navy-700 px-3 py-1.5 text-zinc-300">Batal</button>
                    </div>
                @endif
            </article>
        @empty
            <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-navy-800 p-10 text-center text-zinc-500">
                <flux:icon name="trophy" class="size-10" />
                Belum ada match di tab {{ \App\Models\GameMatch::ADMIN_TABS[$tab] }}.
            </div>
        @endforelse
    </div>

    {{ $this->matches->links() }}
</div>
