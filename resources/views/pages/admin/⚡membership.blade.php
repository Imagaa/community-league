<?php

use App\Actions\Marketplace\HandleRedemption;
use App\Exceptions\RedemptionException;
use App\Http\Middleware\EnsureAdmin;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\RewardItem;
use App\Models\RewardRedemption;
use App\Support\MemberCardImage;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin')] #[Title('Membership')] class extends Component {
    use WithFileUploads;

    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    public const TABS = ['setelan' => 'Setelan', 'match' => 'Match', 'kartu' => 'Kartu', 'store' => 'Store', 'penukaran' => 'Penukaran'];

    /** Filter tab Kartu: "Perlu dicetak" = member aktif yang kartunya belum pernah diunduh untuk cetak. */
    public const CARD_FILTERS = ['cetak' => 'Perlu dicetak', 'aktif' => 'Aktif', 'semua' => 'Semua'];

    /** Filter tab Penukaran. */
    public const REDEMPTION_FILTERS = ['pending' => 'Menunggu', 'processing' => 'Diproses', 'fulfilled' => 'Diterima', 'rejected' => 'Ditolak'];

    /** Batas jeda akses awal member: 30 hari. */
    public const MAX_LEAD_MINUTES = 30 * 24 * 60;

    #[Url]
    public string $tab = 'setelan';

    #[Url(as: 'status')]
    public string $redemptionStatus = 'pending';

    #[Url(as: 'kartu')]
    public string $cardFilter = 'cetak';

    public string $cardSearch = '';

    public string $notice = '';

    // Setelan (mode lihat -> Ubah -> Simpan)
    public bool $editingSettings = false;
    public bool $enabled = false;
    public ?int $price = null;
    public ?int $duration_days = null;

    // Desain kartu (Setelan): latar unggahan + posisi kotak data
    public bool $editingCard = false;
    public $cardFront = null;
    public $cardBack = null;
    /** @var array<string, array<string, mixed>> */
    public array $layout = [];

    // Tab Match: satu match yang sedang diubah
    public ?int $editingMatch = null;
    public ?int $leadDays = 0;
    public ?int $leadHours = 0;
    public ?int $leadMinutes = 30;
    public ?int $points = 0;

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'setelan';
        $this->reset('editingMatch', 'editingSettings', 'notice');
        $this->resetErrorBag();
    }

    /**
     * Tab yang tampil: Match hanya saat membership aktif; Store & Penukaran selalu bisa dikelola.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function tabs(): array
    {
        return array_filter(self::TABS, fn ($label, $key) => $key !== 'match' || $this->settings->enabled, ARRAY_FILTER_USE_BOTH);
    }

    #[Computed]
    public function settings(): MembershipSettings
    {
        return MembershipSettings::current();
    }

    // ---- Setelan ----

    public function editSettings(): void
    {
        $this->fill([
            'enabled' => $this->settings->enabled,
            'price' => $this->settings->price,
            'duration_days' => $this->settings->duration_days,
            'editingSettings' => true,
            'notice' => '',
        ]);
    }

    public function saveSettings(): void
    {
        $this->validate([
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ], [], ['price' => 'harga', 'duration_days' => 'durasi']);

        $this->settings->update(['enabled' => $this->enabled, 'price' => $this->price, 'duration_days' => $this->duration_days]);
        unset($this->settings);
        $this->editingSettings = false;
        $this->notice = $this->enabled ? 'Membership aktif di web.' : 'Membership dimatikan -- web menampilkan "sedang dikembangkan".';
    }

    // ---- Desain kartu member ----

    public function editCard(): void
    {
        $this->layout = MemberCardImage::layout();
        $this->reset('cardFront', 'cardBack', 'notice');
        $this->resetErrorBag();
        $this->editingCard = true;
    }

    /** @param  array<string, array<string, mixed>>  $layout  posisi kotak dari editor (Alpine) */
    public function saveCard(array $layout): void
    {
        $this->layout = $layout;
        $cardRatio = function (string $attribute, mixed $file, \Closure $fail) {
            $size = $file instanceof UploadedFile ? @getimagesize((string) $file->getRealPath()) : false;
            if ($size === false || abs($size[0] / max(1, $size[1]) - 85.6 / 54) > 0.03) {
                $fail('Harus rasio kartu KTP 85,6 × 54 mm, mis. 1011 × 638 px atau 2022 × 1276 px.');
            }
        };
        $rules = [];
        foreach (['cardFront', 'cardBack'] as $upload) {
            $rules[$upload] = ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:5120', 'dimensions:min_width=1000,max_width=3600', $cardRatio];
        }
        foreach (MemberCardImage::FIELDS as $key => $default) {
            $rules["layout.{$key}.side"] = ['required', 'in:front,back'];
            foreach (['x', 'y', 'w', 'h'] as $prop) {
                $rules["layout.{$key}.{$prop}"] = ['required', 'numeric', 'min:0', 'max:100'];
            }
            if (isset($default['font'])) {
                $rules["layout.{$key}.color"] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
                $rules["layout.{$key}.font"] = ['required', 'in:'.implode(',', array_keys(MemberCardImage::FONTS))];
                $rules["layout.{$key}.align"] = ['required', 'in:left,center,right'];
            }
        }
        $this->validate($rules, [
            'cardFront.dimensions' => 'Lebar gambar 1000–3600 px.',
            'cardBack.dimensions' => 'Lebar gambar 1000–3600 px.',
        ], ['cardFront' => 'desain depan', 'cardBack' => 'desain belakang']);

        $layout = [];
        foreach (MemberCardImage::FIELDS as $key => $default) {
            $layout[$key] = array_intersect_key($this->layout[$key], $default);
        }
        $data = ['card_layout' => $layout];
        foreach (['cardFront' => 'card_front_path', 'cardBack' => 'card_back_path'] as $upload => $column) {
            if ($this->{$upload} instanceof UploadedFile) {
                if ($this->settings->{$column}) {
                    Storage::disk('public')->delete($this->settings->{$column});
                }
                $data[$column] = $this->{$upload}->store('member-cards', 'public');
            }
        }
        $this->settings->update($data);
        unset($this->settings);
        $this->reset('cardFront', 'cardBack', 'editingCard');
        $this->notice = 'Desain kartu disimpan. Kartu yang diunduh berikutnya memakai desain ini.';
    }

    public function resetCardDesign(): void
    {
        foreach (['card_front_path', 'card_back_path'] as $column) {
            if ($this->settings->{$column}) {
                Storage::disk('public')->delete($this->settings->{$column});
            }
        }
        $this->settings->update(['card_front_path' => null, 'card_back_path' => null, 'card_layout' => null]);
        unset($this->settings);
        $this->reset('cardFront', 'cardBack', 'editingCard');
        $this->notice = 'Desain kartu dikembalikan ke desain bawaan.';
    }

    /** Latar editor: file yang baru dipilih (kalau bisa dipratinjau), atau desain tersimpan. */
    public function cardTemplateUrl(string $side): string
    {
        $upload = $side === 'front' ? $this->cardFront : $this->cardBack;
        if ($upload instanceof TemporaryUploadedFile && $upload->isPreviewable()) {
            return $upload->temporaryUrl();
        }

        return route('admin.membership.card-template', [$side, 'v' => $this->settings->updated_at?->timestamp]);
    }

    // ---- Match: akses awal member + poin ----

    /** @return Collection<int, GameMatch> match yang belum selesai & tidak batal */
    #[Computed]
    public function matches(): Collection
    {
        return GameMatch::query()->with('venue')
            ->where('status', '!=', 'cancelled')
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->get();
    }

    public function editMatch(int $id): void
    {
        $match = GameMatch::findOrFail($id);
        $lead = $match->member_lead_minutes;
        $this->resetErrorBag();
        $this->fill([
            'editingMatch' => $id,
            'leadDays' => intdiv($lead, 1440),
            'leadHours' => intdiv($lead % 1440, 60),
            'leadMinutes' => $lead % 60,
            'points' => $match->points_reward,
            'notice' => '',
        ]);
    }

    public function saveMatch(): void
    {
        $this->validate([
            'leadDays' => ['required', 'integer', 'min:0', 'max:30'],
            'leadHours' => ['required', 'integer', 'min:0', 'max:23'],
            'leadMinutes' => ['required', 'integer', 'min:0', 'max:59'],
            'points' => ['required', 'integer', 'min:0', 'max:100000'],
        ], [], ['leadDays' => 'hari', 'leadHours' => 'jam', 'leadMinutes' => 'menit', 'points' => 'poin']);

        $minutes = min(self::MAX_LEAD_MINUTES, $this->leadDays * 1440 + $this->leadHours * 60 + $this->leadMinutes);
        $match = GameMatch::findOrFail($this->editingMatch);

        DB::transaction(function () use ($match, $minutes) {
            $match->update(['points_reward' => $this->points]);
            $match->setMemberLead($minutes);
        });

        $this->editingMatch = null;
        $this->notice = "{$match->title}: akses awal member ".self::leadLabel($minutes).", {$this->points} poin.";
        unset($this->matches);
    }

    public static function leadLabel(int $minutes): string
    {
        if ($minutes === 0) {
            return 'tidak ada';
        }

        return trim(implode(' ', array_filter([
            intdiv($minutes, 1440) ? intdiv($minutes, 1440).' hari' : null,
            intdiv($minutes % 1440, 60) ? intdiv($minutes % 1440, 60).' jam' : null,
            $minutes % 60 ? ($minutes % 60).' menit' : null,
        ]))).' lebih awal';
    }

    // ---- Kartu member ----

    /** @return Collection<int, Membership> */
    #[Computed]
    public function members(): Collection
    {
        $term = '%'.trim($this->cardSearch).'%';

        return Membership::query()->with('user')
            ->when($this->cardFilter !== 'semua', fn ($q) => $q->where('expires_at', '>', now()))
            ->when($this->cardFilter === 'cetak', fn ($q) => $q->whereNull('card_printed_at'))
            ->when(trim($this->cardSearch) !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term)))
            ->orderByRaw('card_printed_at NULLS FIRST')->orderByDesc('expires_at')
            ->limit(100)
            ->get();
    }

    /** Kartu hilang/rusak: QR kartu lama langsung tidak berlaku. */
    public function replaceCard(int $id): void
    {
        $membership = Membership::with('user')->findOrFail($id);
        $membership->replaceCard();
        $this->notice = "Kartu {$membership->user->name} diganti. Kartu lama tidak berlaku lagi -- unduh & cetak kartu baru.";
        unset($this->members);
    }

    // ---- Store: produk penukaran poin ----

    /** @return Collection<int, RewardItem> */
    #[Computed]
    public function items(): Collection
    {
        return RewardItem::query()->withCount('redemptions')->orderBy('sort_order')->orderBy('id')->get();
    }

    public function moveItem(int $id, int $direction): void
    {
        $ids = $this->items->pluck('id')->all();
        $from = array_search($id, $ids, true);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);

        if ($from === false || $to < 0 || $to >= count($ids)) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $itemId) {
                RewardItem::whereKey($itemId)->update(['sort_order' => $position + 1]);
            }
        });
        unset($this->items);
    }

    public function toggleItem(int $id): void
    {
        $item = RewardItem::findOrFail($id);
        $item->update(['active' => ! $item->active]);
        $this->notice = "{$item->name} ".($item->active ? 'ditampilkan di store.' : 'disembunyikan dari store.');
        unset($this->items);
    }

    /** Hanya produk yang belum pernah ditukar -- riwayat penukaran member tidak boleh hilang. */
    public function deleteItem(int $id): void
    {
        $item = RewardItem::withCount('redemptions')->findOrFail($id);

        if ($item->redemptions_count > 0) {
            $this->notice = "{$item->name} sudah pernah ditukar, jadi tidak bisa dihapus. Sembunyikan saja dari store.";

            return;
        }

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
        $item->delete();
        $this->notice = "{$item->name} dihapus.";
        unset($this->items);
    }

    // ---- Penukaran poin ----

    /** @return Collection<int, RewardRedemption> */
    #[Computed]
    public function redemptions(): Collection
    {
        $status = array_key_exists($this->redemptionStatus, self::REDEMPTION_FILTERS) ? $this->redemptionStatus : 'pending';

        return RewardRedemption::query()->with('user', 'rewardItem')
            ->where('status', $status)
            ->orderBy($status === 'pending' ? 'redeemed_at' : 'updated_at', $status === 'pending' ? 'asc' : 'desc')
            ->limit(100)
            ->get();
    }

    /** @return array<string, int> */
    #[Computed]
    public function redemptionCounts(): array
    {
        return RewardRedemption::query()->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')
            ->pluck('jumlah', 'status')->map(fn ($n) => (int) $n)->all();
    }

    public function processRedemption(int $id, HandleRedemption $handle): void
    {
        $this->decideRedemption(fn (RewardRedemption $r) => $handle->process($r), $id, 'Penukaran ditandai diproses. Member mendapat notifikasi.');
    }

    public function fulfillRedemption(int $id, HandleRedemption $handle): void
    {
        $this->decideRedemption(fn (RewardRedemption $r) => $handle->fulfill($r, auth()->user()), $id, 'Penukaran ditandai sudah diterima member.');
    }

    public function rejectRedemption(int $id, string $reason, HandleRedemption $handle): void
    {
        $this->decideRedemption(fn (RewardRedemption $r) => $handle->reject($r, $reason), $id, 'Penukaran ditolak. Poin & stok dikembalikan.');
    }

    private function decideRedemption(callable $action, int $id, string $message): void
    {
        $this->resetErrorBag();

        try {
            $action(RewardRedemption::findOrFail($id));
            $this->notice = $message;
        } catch (RedemptionException $e) {
            $this->addError('redemption', $e->getMessage());
        }

        unset($this->redemptions, $this->redemptionCounts, $this->items);
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <h1 class="text-xl font-bold text-white">Membership</h1>
        <p class="text-xs text-zinc-400">
            {{ $this->settings->enabled ? 'Aktif di web.' : 'Nonaktif -- web menampilkan "sedang dikembangkan". Store tetap bisa disiapkan; tampil ke member setelah diaktifkan.' }}
        </p>
    </div>

    @if ($notice)
        <p class="rounded-xl border border-emerald-800 bg-emerald-950 px-4 py-3 text-emerald-300">{{ $notice }}</p>
    @endif

    <div class="flex gap-2 overflow-x-auto">
        @foreach ($this->tabs as $key => $label)
            <x-admin.tab :active="$tab === $key" wire:click="setTab('{{ $key }}')">
                {{ $label }}@if ($key === 'penukaran' && ($this->redemptionCounts['pending'] ?? 0) > 0) ({{ $this->redemptionCounts['pending'] }})@endif
            </x-admin.tab>
        @endforeach
    </div>

    @if ($tab === 'match' && $this->settings->enabled)
        <p class="text-xs text-zinc-400">
            Member bisa membeli lebih awal dari publik. Default 30 menit; dihitung mundur dari waktu publik bisa membeli
            (dari "Publish terjadwal", atau dari saat "Publish sekarang"). Poin masuk ke member saat check-in di venue.
        </p>
        <div class="space-y-2">
            @forelse ($this->matches as $m)
                <article wire:key="mm-{{ $m->id }}" class="rounded-2xl border border-navy-800 bg-navy-900 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-white">
                                {{ $m->title }}
                                @if ($m->status === 'draft') <span class="ml-1 rounded-full bg-navy-800 px-2 py-0.5 text-[10px] text-zinc-300">Draft</span> @endif
                            </p>
                            <p class="text-xs text-zinc-400">{{ $m->starts_at->timezone(config('app.display_timezone'))->translatedFormat('D, d M Y · H.i') }} · {{ $m->venue->name }}</p>
                        </div>
                        @if ($editingMatch !== $m->id)
                            <button type="button" wire:click="editMatch({{ $m->id }})"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-navy-700 px-3 py-1.5 text-xs font-semibold text-zinc-200 hover:bg-navy-800">
                                <flux:icon name="pencil-square" variant="micro" /> Ubah
                            </button>
                        @endif
                    </div>

                    @if ($editingMatch === $m->id)
                        <div class="mt-3 space-y-3 rounded-xl border border-orange-600/40 bg-navy-950/50 p-3">
                            <div>
                                <p class="mb-1.5 text-xs font-semibold tracking-wide text-zinc-400 uppercase">Member bisa beli lebih awal</p>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach (['leadDays' => 'hari', 'leadHours' => 'jam', 'leadMinutes' => 'menit'] as $field => $unit)
                                        <div class="relative">
                                            <input type="number" inputmode="numeric" min="0" wire:model="{{ $field }}" class="admin-input pr-14">
                                            <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-zinc-500">{{ $unit }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                @foreach (['leadDays', 'leadHours', 'leadMinutes'] as $field)
                                    @error($field) <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                                @endforeach
                            </div>
                            <x-admin.field label="Poin untuk member yang check-in" name="points">
                                <input type="number" inputmode="numeric" min="0" wire:model="points" class="admin-input">
                            </x-admin.field>
                            <div class="flex gap-2">
                                <button type="button" wire:click="saveMatch" class="rounded-lg bg-orange-600 px-4 py-2 text-xs font-bold text-white hover:bg-orange-700">Simpan perubahan</button>
                                <button type="button" wire:click="$set('editingMatch', null)" class="rounded-lg border border-navy-700 px-4 py-2 text-xs text-zinc-300 hover:bg-navy-800">Batal</button>
                            </div>
                        </div>
                    @else
                        @php $tz = config('app.display_timezone'); @endphp
                        <div class="mt-2">
                            <x-admin.setting-row label="Akses awal member">{{ $this::leadLabel($m->member_lead_minutes) }}</x-admin.setting-row>
                            <x-admin.setting-row label="Poin">{{ $m->points_reward }} poin</x-admin.setting-row>
                            @if ($m->status === 'published')
                                <x-admin.setting-row label="Member / publik bisa beli">
                                    {{ $m->member_opens_at->timezone($tz)->translatedFormat('d M H.i') }} / {{ $m->public_opens_at->timezone($tz)->translatedFormat('d M H.i') }} WIB
                                </x-admin.setting-row>
                            @else
                                <x-admin.setting-row label="Jadwal buka">dihitung saat match diterbitkan</x-admin.setting-row>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-navy-800 p-8 text-center text-sm text-zinc-500">Belum ada match yang akan datang.</p>
            @endforelse
        </div>
    @elseif ($tab === 'kartu')
        <p class="text-xs text-zinc-400">
            Kartu cetak ukuran KTP (85,6 × 54 mm, PNG 300 dpi) tanpa masa berlaku -- status dicek lewat QR, jadi kartu tetap dipakai
            setelah member memperpanjang. Mengunduh sisi depan menandai kartu sudah dicetak. Kartu hilang? "Ganti kartu" memblokir QR lama.
        </p>
        <div class="flex gap-2 overflow-x-auto">
            @foreach ($this::CARD_FILTERS as $key => $label)
                <x-admin.tab :active="$cardFilter === $key" wire:click="$set('cardFilter', '{{ $key }}')">{{ $label }}</x-admin.tab>
            @endforeach
        </div>
        <div class="relative">
            <flux:icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-zinc-500" />
            <input type="search" wire:model.live.debounce.400ms="cardSearch" placeholder="Cari nama atau email member" class="admin-input pl-10">
        </div>
        <div class="space-y-2">
            @forelse ($this->members as $mb)
                @php $tz = config('app.display_timezone'); @endphp
                <article wire:key="mb-{{ $mb->id }}" class="rounded-2xl border border-navy-800 bg-navy-900 p-4" x-data="{ confirming: false }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-white">{{ $mb->user->name }} <span class="ml-1 font-mono text-xs text-orange-400">{{ $mb->memberNumber() }}</span></p>
                            <p class="truncate text-xs text-zinc-400">{{ $mb->user->email }}</p>
                            <p class="mt-1 text-xs">
                                @if ($mb->isActive())
                                    <span class="text-emerald-400">Aktif s/d {{ $mb->expires_at->timezone($tz)->translatedFormat('d M Y') }}</span>
                                @else
                                    <span class="text-zinc-500">Tidak aktif sejak {{ $mb->expires_at->timezone($tz)->translatedFormat('d M Y') }}</span>
                                @endif
                                <span class="text-zinc-600">·</span>
                                @if ($mb->card_printed_at)
                                    <span class="text-zinc-400">Dicetak {{ $mb->card_printed_at->timezone($tz)->translatedFormat('d M Y') }}</span>
                                    <span class="text-zinc-600">·</span>
                                    @if ($mb->cardActive())
                                        <span class="text-emerald-400">Kartu aktif</span>
                                    @else
                                        <span class="font-semibold text-orange-400">Kartu belum aktif</span>
                                    @endif
                                @else
                                    <span class="font-semibold text-orange-400">Belum dicetak</span>
                                @endif
                                @if ($mb->nfc_uid) <span class="text-zinc-600">·</span> <span class="text-zinc-400">NFC terhubung</span> @endif
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-navy-800 pt-3">
                        <a href="{{ route('admin.membership.card', [$mb, 'depan']) }}" @click="setTimeout(() => $wire.$refresh(), 1500)" class="inline-flex items-center gap-1.5 rounded-lg bg-orange-600 px-3 py-2 text-xs font-bold text-white hover:bg-orange-700">
                            <flux:icon name="arrow-down-tray" variant="micro" /> Depan
                        </a>
                        <a href="{{ route('admin.membership.card', [$mb, 'belakang']) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-navy-700 px-3 py-2 text-xs font-semibold text-zinc-200 hover:bg-navy-800">
                            <flux:icon name="arrow-down-tray" variant="micro" /> Belakang
                        </a>
                        @if ($mb->card_printed_at && ! $mb->cardActive())
                            <a href="{{ route('admin.card-scanner', ['member' => $mb->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-700 px-3 py-2 text-xs font-semibold text-emerald-300 hover:bg-emerald-950">
                                <flux:icon name="qr-code" variant="micro" /> Aktifkan kartu
                            </a>
                        @endif
                        <span class="flex-1"></span>
                        <button type="button" @click="confirming = true" x-show="! confirming" class="rounded-lg px-2 py-1.5 text-xs text-zinc-400 hover:bg-navy-800 hover:text-white">Ganti kartu</button>
                    </div>
                    <div x-show="confirming" x-cloak class="mt-2 flex flex-wrap items-center gap-2 rounded-xl border border-orange-900 bg-orange-950/40 p-3 text-xs text-orange-200">
                        <span class="flex-1">QR kartu lama akan langsung tidak berlaku. Lanjutkan?</span>
                        <button type="button" wire:click="replaceCard({{ $mb->id }})" @click="confirming = false" class="rounded-lg bg-orange-600 px-3 py-1.5 font-bold text-white">Ya, ganti</button>
                        <button type="button" @click="confirming = false" class="rounded-lg border border-navy-700 px-3 py-1.5 text-zinc-300">Batal</button>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-navy-800 p-8 text-center text-sm text-zinc-500">
                    {{ $cardFilter === 'cetak' ? 'Semua kartu member aktif sudah dicetak.' : 'Belum ada member.' }}
                </p>
            @endforelse
        </div>
    @elseif ($tab === 'store')
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs text-zinc-400">Produk yang bisa ditukar member dengan poin.</p>
            <a href="{{ route('admin.membership.store.create') }}" wire:navigate class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-orange-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-orange-700">
                <flux:icon name="plus" variant="micro" /> Produk baru
            </a>
        </div>
        <div class="space-y-2">
            @forelse ($this->items as $item)
                <article wire:key="ri-{{ $item->id }}" class="rounded-2xl border border-navy-800 bg-navy-900 p-3" x-data="{ confirming: false }">
                    <div class="flex gap-3">
                        <div class="relative size-16 shrink-0 overflow-hidden rounded-lg bg-navy-800">
                            @if ($item->image_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->temporaryUrl($item->image_path, now()->addDay()) }}" alt="" loading="lazy" @class(['size-full object-cover', 'opacity-40' => ! $item->active])>
                            @else
                                <flux:icon name="gift" class="absolute inset-0 m-auto size-6 text-zinc-600" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-white">{{ $item->name }}</p>
                            <p class="text-xs text-zinc-400">
                                <span class="font-semibold text-orange-400">{{ number_format($item->point_cost, 0, ',', '.') }} poin</span>
                                · stok {{ $item->stock === null ? 'tak terbatas' : $item->stock }}
                                · {{ $item->redemptions_count }}x ditukar
                            </p>
                            @unless ($item->active)
                                <span class="mt-1 inline-block rounded-full bg-navy-800 px-2 py-0.5 text-[10px] text-zinc-400">Disembunyikan</span>
                            @endunless
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1 border-t border-navy-800 pt-2">
                        <button type="button" wire:click="moveItem({{ $item->id }}, -1)" @disabled($loop->first) class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white disabled:opacity-30" title="Naikkan"><flux:icon name="arrow-up" class="size-4" /></button>
                        <button type="button" wire:click="moveItem({{ $item->id }}, 1)" @disabled($loop->last) class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white disabled:opacity-30" title="Turunkan"><flux:icon name="arrow-down" class="size-4" /></button>
                        <button type="button" wire:click="toggleItem({{ $item->id }})" class="ml-1 rounded-lg px-2 py-1.5 text-xs text-zinc-400 hover:bg-navy-800 hover:text-white">{{ $item->active ? 'Sembunyikan' : 'Tampilkan' }}</button>
                        <span class="flex-1"></span>
                        <a href="{{ route('admin.membership.store.edit', $item) }}" wire:navigate class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white" title="Ubah"><flux:icon name="pencil-square" class="size-4" /></a>
                        @if ($item->redemptions_count === 0)
                            <button type="button" @click="confirming = true" class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-red-400" title="Hapus"><flux:icon name="trash" class="size-4" /></button>
                        @endif
                    </div>
                    <div x-show="confirming" x-cloak class="mt-2 flex flex-wrap items-center gap-2 rounded-xl border border-red-900 bg-red-950/50 p-3 text-xs text-red-200">
                        <span class="flex-1">Hapus produk ini beserta gambarnya?</span>
                        <button type="button" wire:click="deleteItem({{ $item->id }})" class="rounded-lg bg-red-600 px-3 py-1.5 font-bold text-white">Ya, hapus</button>
                        <button type="button" @click="confirming = false" class="rounded-lg border border-navy-700 px-3 py-1.5 text-zinc-300">Batal</button>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-navy-800 p-8 text-center text-sm text-zinc-500">Belum ada produk di store.</p>
            @endforelse
        </div>
    @elseif ($tab === 'penukaran')
        <div class="flex gap-2 overflow-x-auto">
            @foreach ($this::REDEMPTION_FILTERS as $key => $label)
                <x-admin.tab :active="$redemptionStatus === $key" wire:click="$set('redemptionStatus', '{{ $key }}')">{{ $label }} ({{ $this->redemptionCounts[$key] ?? 0 }})</x-admin.tab>
            @endforeach
        </div>
        @error('redemption') <p class="rounded-xl border border-red-900 bg-red-950 px-4 py-3 text-red-300">{{ $message }}</p> @enderror
        <div class="space-y-2">
            @forelse ($this->redemptions as $r)
                <article wire:key="rd-{{ $r->id }}" class="rounded-2xl border border-navy-800 bg-navy-900 p-4" x-data="{ rejecting: false, reason: '' }">
                    <p class="truncate font-semibold text-white">{{ $r->rewardItem->name }}</p>
                    <p class="text-xs text-zinc-400">
                        {{ $r->user?->name ?? 'Akun terhapus' }} · {{ $r->user?->email }}
                        @if ($r->user?->phone) · <a href="{{ \App\Support\WhatsApp::url($r->user->phone) }}" target="_blank" rel="noopener" class="text-orange-400">{{ $r->user->phone }}</a> @endif
                    </p>
                    <p class="text-xs text-zinc-500">
                        {{ number_format($r->points_spent, 0, ',', '.') }} poin · ditukar {{ $r->redeemed_at->timezone(config('app.display_timezone'))->translatedFormat('d M Y H.i') }}
                        @if ($r->fulfilled_at) · diterima {{ $r->fulfilled_at->timezone(config('app.display_timezone'))->translatedFormat('d M H.i') }} @endif
                    </p>
                    @if ($r->admin_note) <p class="mt-1 text-xs text-zinc-300">Catatan: {{ $r->admin_note }}</p> @endif

                    @if (in_array($r->status, ['pending', 'processing'], true))
                        <div class="mt-3 flex flex-wrap gap-2 border-t border-navy-800 pt-3" x-show="! rejecting">
                            @if ($r->status === 'pending')
                                <button type="button" wire:click="processRedemption({{ $r->id }})" class="rounded-lg border border-navy-700 px-3 py-2 text-xs font-semibold text-zinc-200 hover:bg-navy-800">Proses</button>
                            @endif
                            <button type="button" wire:click="fulfillRedemption({{ $r->id }})" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Tandai sudah diterima</button>
                            <button type="button" @click="rejecting = true" class="rounded-lg border border-orange-800 px-3 py-2 text-xs font-bold text-orange-400 hover:bg-orange-950">Tolak</button>
                        </div>
                        <div class="mt-3 space-y-2 border-t border-navy-800 pt-3" x-show="rejecting" x-cloak>
                            <textarea x-model="reason" rows="2" placeholder="Alasan penolakan (dikirim ke member; poin & stok dikembalikan)" class="admin-input text-xs"></textarea>
                            <div class="flex gap-2">
                                <button type="button" :disabled="reason.trim() === ''" @click="$wire.rejectRedemption({{ $r->id }}, reason)" class="rounded-lg bg-orange-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-40">Tolak & kembalikan poin</button>
                                <button type="button" @click="rejecting = false" class="rounded-lg border border-navy-700 px-3 py-2 text-xs text-zinc-300">Batal</button>
                            </div>
                        </div>
                    @endif
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-navy-800 p-8 text-center text-sm text-zinc-500">Tidak ada penukaran di status ini.</p>
            @endforelse
        </div>
    @else
        <section class="space-y-4 rounded-2xl border border-navy-800 bg-navy-900 p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <flux:icon name="star" class="size-5 text-orange-400" />
                    <h2 class="font-semibold text-white">Setelan membership</h2>
                </div>
                @unless ($editingSettings)
                    <button type="button" wire:click="editSettings" class="inline-flex items-center gap-1 rounded-lg border border-navy-700 px-3 py-1.5 text-xs font-semibold text-zinc-200 hover:bg-navy-800">
                        <flux:icon name="pencil-square" variant="micro" /> Ubah
                    </button>
                @endunless
            </div>

            @if ($editingSettings)
                <div class="space-y-4 rounded-xl border border-orange-600/40 bg-navy-950/50 p-4">
                    <x-admin.switch wire:model="enabled" label="Membership aktif"
                                    description="Saat off: halaman membership menampilkan 'Fitur sedang dikembangkan', semua teks/fitur poin disembunyikan, dan tidak ada akses awal member." />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.field label="Harga" name="price">
                            <x-admin.money-input wire:model="price" placeholder="100.000" />
                        </x-admin.field>
                        <x-admin.field label="Durasi (hari)" name="duration_days">
                            <input type="number" inputmode="numeric" min="1" wire:model="duration_days" class="admin-input">
                        </x-admin.field>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="saveSettings" class="rounded-lg bg-orange-600 px-4 py-2 text-xs font-bold text-white hover:bg-orange-700">Simpan perubahan</button>
                        <button type="button" wire:click="$set('editingSettings', false)" class="rounded-lg border border-navy-700 px-4 py-2 text-xs text-zinc-300 hover:bg-navy-800">Batal</button>
                    </div>
                </div>
            @else
                <div>
                    <x-admin.setting-row label="Status" :ok="$this->settings->enabled">{{ $this->settings->enabled ? 'Aktif' : 'Nonaktif' }}</x-admin.setting-row>
                    <x-admin.setting-row label="Harga">{{ Money::rupiah($this->settings->price) }}</x-admin.setting-row>
                    <x-admin.setting-row label="Durasi">{{ $this->settings->duration_days }} hari</x-admin.setting-row>
                </div>
            @endif
        </section>

        {{-- Desain kartu member: latar PNG/JPG + kotak data yang bisa digeser. --}}
        <section class="space-y-4 rounded-2xl border border-navy-800 bg-navy-900 p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <flux:icon name="identification" class="size-5 text-orange-400" />
                    <h2 class="font-semibold text-white">Desain kartu member</h2>
                </div>
                @unless ($editingCard)
                    <button type="button" wire:click="editCard" class="inline-flex items-center gap-1 rounded-lg border border-navy-700 px-3 py-1.5 text-xs font-semibold text-zinc-200 hover:bg-navy-800">
                        <flux:icon name="pencil-square" variant="micro" /> Ubah
                    </button>
                @endunless
            </div>

            @if ($editingCard)
                @php
                    $sides = ['front' => 'Depan', 'back' => 'Belakang'];
                    $on = 'bg-orange-600 text-white';
                    $off = 'border border-navy-700 text-zinc-300 hover:bg-navy-800';
                @endphp
                <div
                    x-data="{
                        layout: @js($layout),
                        labels: @js(MemberCardImage::LABELS),
                        drag: null,
                        start(e, key, mode, el) {
                            this.drag = { key, mode, rect: el.getBoundingClientRect(), x: e.clientX, y: e.clientY, from: { ...this.layout[key] } };
                        },
                        move(e) {
                            if (! this.drag) return;
                            const d = this.drag, f = this.layout[d.key], r = (v) => Math.round(v * 10) / 10;
                            const dx = (e.clientX - d.x) / d.rect.width * 100, dy = (e.clientY - d.y) / d.rect.height * 100;
                            if (d.mode === 'move') {
                                f.x = r(Math.min(100 - f.w, Math.max(0, d.from.x + dx)));
                                f.y = r(Math.min(100 - f.h, Math.max(0, d.from.y + dy)));
                            } else {
                                f.w = r(Math.min(100 - f.x, Math.max(2, d.from.w + dx)));
                                f.h = r(Math.min(100 - f.y, Math.max(2, d.from.h + dy)));
                            }
                        },
                    }"
                    @pointermove.window="move($event)"
                    @pointerup.window="drag = null"
                    class="space-y-4 rounded-xl border border-orange-600/40 bg-navy-950/50 p-4"
                >
                    <p class="text-xs text-zinc-400">
                        Unggah latar desain (PNG/JPG tanpa data, rasio kartu KTP 85,6 × 54 mm, mis. 1011 × 638 px), lalu geser kotak
                        ke tempat data ditulis. Tarik titik oranye di pojok kanan bawah untuk mengubah ukuran. Tinggi kotak = tinggi huruf;
                        nama panjang otomatis mengecil/disingkat. QR selalu persegi di tengah kotak.
                    </p>

                    @foreach ($sides as $side => $sideLabel)
                        @php $upload = $side === 'front' ? 'cardFront' : 'cardBack'; @endphp
                        <div wire:key="card-editor-{{ $side }}">
                            <div class="mb-1.5 flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">Sisi {{ $sideLabel }}</p>
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-navy-700 px-3 py-1.5 text-xs font-semibold text-zinc-200 hover:bg-navy-800">
                                    <flux:icon name="arrow-up-tray" variant="micro" />
                                    <span wire:loading.remove wire:target="{{ $upload }}">Ganti desain {{ strtolower($sideLabel) }}</span>
                                    <span wire:loading wire:target="{{ $upload }}">Mengunggah...</span>
                                    <input type="file" wire:model="{{ $upload }}" accept="image/png,image/jpeg" class="sr-only">
                                </label>
                            </div>
                            <div x-ref="{{ $side }}" class="relative overflow-hidden rounded-xl bg-navy-800 select-none" style="aspect-ratio: 85.6 / 54">
                                <img src="{{ $this->cardTemplateUrl($side) }}" alt="Desain {{ strtolower($sideLabel) }}" draggable="false" class="absolute inset-0 size-full object-fill">
                                <template x-for="(f, key) in layout" :key="key">
                                    <div
                                        x-show="f.side === '{{ $side }}'"
                                        :style="`left:${f.x}%;top:${f.y}%;width:${f.w}%;height:${f.h}%`"
                                        @pointerdown.prevent="start($event, key, 'move', $refs.{{ $side }})"
                                        class="absolute cursor-move touch-none border-2 border-dashed border-orange-400 bg-orange-500/25"
                                    >
                                        <span class="pointer-events-none absolute top-0 left-1 truncate text-[10px] leading-4 font-bold text-white drop-shadow" x-text="labels[key]"></span>
                                        <span @pointerdown.prevent.stop="start($event, key, 'resize', $refs.{{ $side }})" class="absolute -right-2 -bottom-2 size-4 cursor-nwse-resize touch-none rounded-full border-2 border-white bg-orange-500"></span>
                                    </div>
                                </template>
                            </div>
                            @error($upload) <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endforeach

                    <div class="space-y-2">
                        <template x-for="(f, key) in layout" :key="key">
                            <div class="space-y-2 rounded-xl border border-navy-800 bg-navy-900 p-3">
                                <p class="text-sm font-semibold text-white" x-text="labels[key]"></p>
                                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                    @foreach ($sides as $side => $sideLabel)
                                        <button type="button" @click="f.side = '{{ $side }}'" :class="f.side === '{{ $side }}' ? '{{ $on }}' : '{{ $off }}'" class="rounded-lg px-2.5 py-1.5 font-semibold">{{ $sideLabel }}</button>
                                    @endforeach
                                    <template x-if="key !== 'qr'">
                                        <div class="contents">
                                            <span class="mx-1 h-5 w-px bg-navy-700"></span>
                                            @foreach (['sans' => 'Teks', 'display' => 'Brand'] as $font => $fontLabel)
                                                <button type="button" @click="f.font = '{{ $font }}'" :class="f.font === '{{ $font }}' ? '{{ $on }}' : '{{ $off }}'" class="rounded-lg px-2.5 py-1.5 font-semibold">{{ $fontLabel }}</button>
                                            @endforeach
                                            <span class="mx-1 h-5 w-px bg-navy-700"></span>
                                            @foreach (['left' => 'bars-3-bottom-left', 'center' => 'bars-3', 'right' => 'bars-3-bottom-right'] as $align => $icon)
                                                <button type="button" @click="f.align = '{{ $align }}'" :class="f.align === '{{ $align }}' ? '{{ $on }}' : '{{ $off }}'" class="rounded-lg p-1.5" title="Rata {{ ['left' => 'kiri', 'center' => 'tengah', 'right' => 'kanan'][$align] }}">
                                                    <flux:icon :name="$icon" variant="micro" />
                                                </button>
                                            @endforeach
                                            <span class="mx-1 h-5 w-px bg-navy-700"></span>
                                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-navy-700 px-2 py-1 text-zinc-300 hover:bg-navy-800">
                                                <span class="size-4 rounded border border-white/30" :style="`background:${f.color}`"></span>
                                                <span class="font-mono" x-text="f.color"></span>
                                                <input type="color" x-model="f.color" class="sr-only">
                                            </label>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                    @foreach (array_keys(MemberCardImage::FIELDS) as $key)
                        @error("layout.{$key}.*") <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                    @endforeach

                    <div class="flex flex-wrap gap-2" x-data="{ confirmReset: false }">
                        <button type="button" @click="$wire.saveCard(JSON.parse(JSON.stringify(layout)))" wire:loading.attr="disabled" class="rounded-lg bg-orange-600 px-4 py-2 text-xs font-bold text-white hover:bg-orange-700 disabled:opacity-50">Simpan desain</button>
                        <button type="button" wire:click="$set('editingCard', false)" class="rounded-lg border border-navy-700 px-4 py-2 text-xs text-zinc-300 hover:bg-navy-800">Batal</button>
                        <span class="flex-1"></span>
                        <button type="button" x-show="! confirmReset" @click="confirmReset = true" class="rounded-lg px-3 py-2 text-xs text-zinc-400 hover:bg-navy-800 hover:text-white">Kembalikan desain bawaan</button>
                        <span x-show="confirmReset" x-cloak class="flex items-center gap-2 text-xs text-orange-200">
                            Hapus desain unggahan & posisi kotak?
                            <button type="button" wire:click="resetCardDesign" class="rounded-lg bg-orange-600 px-3 py-1.5 font-bold text-white">Ya</button>
                            <button type="button" @click="confirmReset = false" class="rounded-lg border border-navy-700 px-3 py-1.5 text-zinc-300">Batal</button>
                        </span>
                    </div>
                </div>
            @else
                <p class="text-xs text-zinc-400">
                    {{ $this->settings->card_front_path || $this->settings->card_back_path ? 'Memakai desain unggahan.' : 'Memakai desain bawaan.' }}
                    Pratinjau dengan data contoh:
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (['front' => 'Depan', 'back' => 'Belakang'] as $side => $sideLabel)
                        <img src="{{ route('admin.membership.card-sample', [$side, 'v' => $this->settings->updated_at?->timestamp]) }}" alt="Pratinjau kartu {{ strtolower($sideLabel) }}" loading="lazy" class="aspect-[85.6/54] w-full rounded-xl bg-navy-800 object-cover">
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
