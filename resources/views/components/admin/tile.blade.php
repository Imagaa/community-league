@props(['active' => false, 'icon' => null, 'sub' => null])

{{-- Pilihan sebagai tile yang langsung diklik (bukan dropdown) -- keputusan desain admin. --}}
<button type="button" aria-pressed="{{ $active ? 'true' : 'false' }}" {{ $attributes->class([
    'flex min-h-16 items-center gap-3 rounded-xl border p-3 text-left transition',
    'border-orange-600 bg-orange-600/15 text-white ring-1 ring-orange-600' => $active,
    'border-navy-800 bg-navy-900 text-zinc-300 hover:border-navy-700' => ! $active,
]) }}>
    @if ($icon)
        <flux:icon :name="$icon" @class(['size-6 shrink-0', 'text-orange-400' => $active, 'text-zinc-500' => ! $active]) />
    @endif
    <span class="min-w-0">
        <span class="block truncate font-semibold">{{ $slot }}</span>
        @if ($sub)
            <span class="block truncate text-xs text-zinc-400">{{ $sub }}</span>
        @endif
    </span>
</button>
