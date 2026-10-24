@props(['label', 'name' => null, 'hint' => null])

<label class="block space-y-1.5">
    <span class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">{{ $label }}</span>
    {{ $slot }}
    @if ($hint)
        <span class="block text-xs text-zinc-500">{{ $hint }}</span>
    @endif
    @if ($name)
        @error($name) <span class="block text-xs text-red-400">{{ $message }}</span> @enderror
    @endif
</label>
