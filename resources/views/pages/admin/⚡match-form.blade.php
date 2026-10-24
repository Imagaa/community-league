<?php

use App\Models\City;
use App\Models\GameMatch;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Http\Middleware\EnsureAdmin;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Match')] class extends Component {
    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    public const TYPES = [
        'minisoccer' => ['label' => 'Mini Soccer', 'icon' => 'user-group'],
        'football' => ['label' => 'Sepak Bola', 'icon' => 'trophy'],
    ];

    public ?GameMatch $match = null;

    public string $title = '';
    public string $type = 'minisoccer';
    /** 'new' = buat venue baru dari isian di bawah. */
    public string $venue = '';
    public string $venue_name = '';
    public string $venue_address = '';
    public string $venue_maps_url = '';
    /** 'new' = buat kota baru dari $city_name. */
    public string $city = '';
    public string $city_name = '';
    public string $starts_at = '';
    public string $ends_at = '';
    public ?int $capacity = null;
    public ?int $price = null;

    public function mount(?GameMatch $match = null): void
    {
        if (! $match?->exists) {
            $this->venue = (string) (Venue::where('is_active', true)->latest()->value('id') ?? 'new');
            $this->city = (string) (City::where('is_active', true)->value('id') ?? 'new');

            return;
        }

        $this->match = $match;
        $tz = config('app.display_timezone');
        $this->fill([
            'title' => $match->title,
            'type' => $match->type,
            'venue' => (string) $match->venue_id,
            'city' => (string) (City::value('id') ?? 'new'),
            'starts_at' => $match->starts_at->timezone($tz)->format('Y-m-d\TH:i'),
            'ends_at' => $match->ends_at->timezone($tz)->format('Y-m-d\TH:i'),
            'capacity' => $match->capacity,
            'price' => $match->price_regular,
        ]);
    }

    /** @return Collection<int, Venue> */
    #[Computed]
    public function venues(): Collection
    {
        return Venue::with('city')->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, City> */
    #[Computed]
    public function cities(): Collection
    {
        return City::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function takenSlots(): int
    {
        return $this->match
            ? (int) $this->match->bookings()->whereIn('status', GameMatch::ACTIVE_BOOKING_STATUSES)->sum('quantity')
            : 0;
    }

    public function save()
    {
        $newVenue = $this->venue === 'new';
        $newCity = $newVenue && $this->city === 'new';

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'venue' => ['required', $newVenue ? 'in:new' : Rule::exists('venues', 'id')],
            'venue_name' => [Rule::requiredIf($newVenue), 'nullable', 'string', 'max:255'],
            'venue_address' => [Rule::requiredIf($newVenue), 'nullable', 'string', 'max:255'],
            'venue_maps_url' => ['nullable', 'url:https', 'max:500'],
            'city' => [Rule::requiredIf($newVenue), 'nullable', $newCity ? 'in:new' : Rule::exists('cities', 'id')],
            'city_name' => [Rule::requiredIf($newCity), 'nullable', 'string', 'max:100'],
            'starts_at' => array_filter(['required', 'date', $this->match ? null : 'after:now']),
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:'.max(1, $this->takenSlots), 'max:1000'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [
            'capacity.min' => 'Slot tidak boleh kurang dari yang sudah terisi (:min).',
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
            'starts_at.after' => 'Waktu mulai harus di masa depan.',
        ], [
            'title' => 'nama match', 'venue_name' => 'nama venue', 'venue_address' => 'alamat',
            'venue_maps_url' => 'link Google Maps', 'city_name' => 'nama kota',
            'starts_at' => 'waktu mulai', 'ends_at' => 'waktu selesai', 'capacity' => 'slot', 'price' => 'harga',
        ]);

        $tz = config('app.display_timezone');

        $match = DB::transaction(function () use ($newVenue, $newCity, $tz) {
            $venueId = (int) $this->venue;

            if ($newVenue) {
                $cityId = $newCity ? City::create(['name' => trim($this->city_name)])->id : (int) $this->city;
                $venueId = Venue::create([
                    'city_id' => $cityId,
                    'name' => trim($this->venue_name),
                    'address' => trim($this->venue_address),
                    'maps_url' => $this->venue_maps_url ?: null,
                ])->id;
            }

            $data = [
                'title' => trim($this->title),
                'type' => $this->type,
                'venue_id' => $venueId,
                'starts_at' => Carbon::parse($this->starts_at, $tz)->utc(),
                'ends_at' => Carbon::parse($this->ends_at, $tz)->utc(),
                'capacity' => $this->capacity,
                'price_regular' => $this->price,
                // Membership & poin belum dipakai -- harga member = harga biasa, tanpa poin.
                'price_member' => $this->price,
            ];

            if ($this->match) {
                $this->match->update($data);

                return $this->match;
            }

            // Match baru disimpan sebagai draft (belum tampil di web) -- diterbitkan dari daftar
            // match lewat "Publish sekarang" / "Publish terjadwal" (GameMatch::publishNow/publishAt).
            $now = now();

            return GameMatch::create($data + [
                'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
                'field_name' => 'Utama',
                'status' => 'draft',
                'public_opens_at' => $now,
                'member_opens_at' => $now,
                'points_reward' => 0,
                'last_completed_wizard_step' => 4,
                'hide_when_finished' => false,
            ]);
        });

        session()->flash('status', $match->status === 'draft'
            ? "Match \"{$match->title}\" tersimpan sebagai draft. Terbitkan dari daftar match."
            : "Match \"{$match->title}\" tersimpan.");

        return $this->redirectRoute('admin.match', navigate: true);
    }
}; ?>

<form wire:submit="save" class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.match') }}" wire:navigate class="rounded-full bg-navy-900 p-2 text-zinc-400 hover:text-white" aria-label="Kembali">
            <flux:icon name="arrow-left" class="size-5" />
        </a>
        <h1 class="text-xl font-bold text-white">{{ $match ? 'Ubah Match' : 'Match Baru' }}</h1>
    </div>

    <x-admin.field label="Nama match" name="title">
        <input type="text" wire:model="title" class="admin-input" placeholder="Mini Soccer Jumat Malam">
    </x-admin.field>

    <div class="space-y-1.5">
        <p class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">Jenis</p>
        <div class="grid grid-cols-2 gap-2">
            @foreach ($this::TYPES as $key => $t)
                <x-admin.tile :active="$type === $key" :icon="$t['icon']" wire:click="$set('type', '{{ $key }}')">{{ $t['label'] }}</x-admin.tile>
            @endforeach
        </div>
    </div>

    <div class="space-y-1.5">
        <p class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">Venue</p>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($this->venues as $v)
                <x-admin.tile wire:key="v-{{ $v->id }}" :active="$venue === (string) $v->id" icon="map-pin" :sub="$v->city->name" wire:click="$set('venue', '{{ $v->id }}')">{{ $v->name }}</x-admin.tile>
            @endforeach
            <x-admin.tile :active="$venue === 'new'" icon="plus" sub="Nama, alamat, link Maps" wire:click="$set('venue', 'new')">Venue baru</x-admin.tile>
        </div>
        @error('venue') <span class="block text-xs text-red-400">{{ $message }}</span> @enderror
    </div>

    @if ($venue === 'new')
        <div class="space-y-4 rounded-2xl border border-navy-800 bg-navy-900/60 p-4">
            <x-admin.field label="Nama venue" name="venue_name">
                <input type="text" wire:model="venue_name" class="admin-input" placeholder="GOR Manahan">
            </x-admin.field>
            <x-admin.field label="Alamat" name="venue_address">
                <input type="text" wire:model="venue_address" class="admin-input">
            </x-admin.field>
            <x-admin.field label="Link Google Maps" name="venue_maps_url" hint="Opsional, tampil sebagai tombol di e-ticket.">
                <input type="url" wire:model="venue_maps_url" class="admin-input" placeholder="https://maps.app.goo.gl/...">
            </x-admin.field>
            <div class="space-y-1.5">
                <p class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">Kota</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($this->cities as $c)
                        <x-admin.tile wire:key="c-{{ $c->id }}" :active="$city === (string) $c->id" icon="building-office-2" wire:click="$set('city', '{{ $c->id }}')">{{ $c->name }}</x-admin.tile>
                    @endforeach
                    <x-admin.tile :active="$city === 'new'" icon="plus" wire:click="$set('city', 'new')">Kota baru</x-admin.tile>
                </div>
                @error('city') <span class="block text-xs text-red-400">{{ $message }}</span> @enderror
            </div>
            @if ($city === 'new')
                <x-admin.field label="Nama kota" name="city_name">
                    <input type="text" wire:model="city_name" class="admin-input" placeholder="Surakarta">
                </x-admin.field>
            @endif
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-admin.field label="Mulai (WIB)" name="starts_at">
            <input type="datetime-local" wire:model="starts_at" class="admin-input [color-scheme:dark]">
        </x-admin.field>
        <x-admin.field label="Selesai (WIB)" name="ends_at">
            <input type="datetime-local" wire:model="ends_at" class="admin-input [color-scheme:dark]">
        </x-admin.field>
        <x-admin.field label="Slot" name="capacity" :hint="$match ? $this->takenSlots.' slot sudah terisi.' : null">
            <input type="number" inputmode="numeric" min="1" wire:model="capacity" class="admin-input" placeholder="14">
        </x-admin.field>
        <x-admin.field label="Harga per slot" name="price">
            <x-admin.money-input wire:model="price" placeholder="75.000" />
        </x-admin.field>
    </div>

    <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-orange-600 py-3 font-bold text-white hover:bg-orange-700 disabled:opacity-50">
        {{ $match ? 'Simpan perubahan' : 'Simpan' }}
    </button>
</form>
