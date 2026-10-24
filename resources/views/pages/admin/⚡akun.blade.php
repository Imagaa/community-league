<?php

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use App\Http\Middleware\EnsureAdmin;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] #[Title('Akun')] class extends Component {
    use WithPagination;

    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, User> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $term = '%'.trim($this->search).'%';

        return User::query()
            ->withCount('bookings')
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'ilike', $term)
                ->orWhere('email', 'ilike', $term)
                ->orWhere('phone', 'ilike', $term)))
            ->latest()
            ->paginate(25);
    }
}; ?>

<div class="space-y-4">
    <h1 class="text-xl font-bold text-white">Akun</h1>

    <div class="relative">
        <flux:icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-zinc-500" />
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Cari nama, email, atau nomor WA" class="admin-input pl-10">
    </div>

    <div class="divide-y divide-navy-800 overflow-hidden rounded-2xl border border-navy-800 bg-navy-900">
        @forelse ($this->users as $user)
            <a href="{{ route('admin.akun.show', $user) }}" wire:navigate wire:key="u-{{ $user->id }}" class="flex items-center gap-3 px-4 py-3 hover:bg-navy-800">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-navy-800 text-xs font-bold text-orange-400">{{ $user->initials() }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold text-white">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-zinc-400">{{ $user->email }}{{ $user->phone ? ' · '.$user->phone : '' }}</span>
                </span>
                <span class="shrink-0 text-xs text-zinc-500">{{ $user->bookings_count }} booking</span>
                <flux:icon name="chevron-right" class="size-4 shrink-0 text-zinc-600" />
            </a>
        @empty
            <p class="p-8 text-center text-zinc-500">Tidak ada akun yang cocok.</p>
        @endforelse
    </div>

    {{ $this->users->links() }}
</div>
