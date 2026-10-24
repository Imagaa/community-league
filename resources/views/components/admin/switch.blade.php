@props(['label', 'description' => null])

{{-- Saklar custom (bukan checkbox bawaan). Pakai: <x-admin.switch wire:model.live="x" label="..." /> --}}
<label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-navy-800 bg-navy-900 p-4">
    <span>
        <span class="block font-semibold text-white">{{ $label }}</span>
        @if ($description)
            <span class="block text-xs text-zinc-400">{{ $description }}</span>
        @endif
    </span>
    <input type="checkbox" {{ $attributes }} class="peer sr-only">
    <span class="relative h-7 w-12 shrink-0 rounded-full bg-navy-700 transition peer-checked:bg-orange-600 peer-focus-visible:ring-2 peer-focus-visible:ring-orange-500 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
</label>
