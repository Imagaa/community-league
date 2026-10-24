@props(['active' => false])

<button type="button" {{ $attributes->class([
    'shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
    'border-orange-600 bg-orange-600 text-white' => $active,
    'border-navy-800 bg-navy-900 text-zinc-400 hover:text-white' => ! $active,
]) }}>{{ $slot }}</button>
