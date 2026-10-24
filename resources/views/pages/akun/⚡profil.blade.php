<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profil')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public ?string $nickname = null;
    public ?string $phone = null;
    public ?string $birthdate = null;
    public ?string $shirt_size = null;
    public ?string $shoe_size = null;
    public ?string $address = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->nickname = $user->nickname;
        $this->phone = $user->phone;
        $this->birthdate = $user->birthdate?->toDateString();
        $this->shirt_size = $user->shirt_size;
        $this->shoe_size = $user->shoe_size !== null ? (string) $user->shoe_size : null;
        $this->address = $user->address;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => $this->nameRules(),
            'nickname' => ['nullable', 'string', 'max:255'],
            'phone' => $this->phoneOptionalRules(),
            'birthdate' => ['nullable', 'date', 'before:today'],
            'shirt_size' => ['nullable', 'string', 'in:S,M,L,XL,XXL'],
            'shoe_size' => ['nullable', 'integer', 'between:36,45'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        Auth::user()->update($validated);

        Flux::toast(variant: 'success', text: __('Profil diperbarui.'));
    }
}; ?>

<div class="flex flex-col gap-4">
    <x-akun-page-header :title="__('Profil')" />

    <form wire:submit="save" class="flex flex-col gap-4">
        <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
            <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Data Diri') }}</h2>
            <div class="flex flex-col gap-4">
                <flux:input wire:model="name" :label="__('Nama Lengkap')" required autocomplete="name" />
                <flux:input wire:model="nickname" :label="__('Nama Panggilan')" />
                <flux:input wire:model="phone" :label="__('Nomor Telfon')" type="tel" />
                <flux:input wire:model="birthdate" :label="__('Tanggal Lahir')" type="date" />
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
            <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Ukuran') }}</h2>
            <div class="flex flex-col gap-4">
                <div>
                    <flux:label>{{ __('Ukuran Kaos') }}</flux:label>
                    <x-filter-select
                        name="shirt_size"
                        :options="['S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL', 'XXL' => 'XXL']"
                        :selected="$shirt_size"
                        placeholder="{{ __('Pilih ukuran') }}"
                        :wire-model="'shirt_size'"
                    />
                </div>
                <div>
                    <flux:label>{{ __('Ukuran Sepatu (EU)') }}</flux:label>
                    <x-filter-select
                        name="shoe_size"
                        :options="collect(range(36, 45))->mapWithKeys(fn ($size) => [$size => (string) $size])"
                        :selected="$shoe_size"
                        placeholder="{{ __('Pilih ukuran') }}"
                        :wire-model="'shoe_size'"
                    />
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
            <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Alamat') }}</h2>
            <flux:textarea wire:model="address" :label="__('Alamat')" rows="3" />
        </section>

        <flux:button variant="primary" type="submit" class="w-full">{{ __('Simpan') }}</flux:button>
    </form>
</div>
