@props(['label', 'ok' => null])

{{-- Baris "label: nilai tersimpan" di ringkasan setelan. ok=true/false memberi tanda hijau/kuning. --}}
<div class="flex items-start justify-between gap-3 border-b border-navy-800 py-2 text-sm last:border-0">
    <span class="text-zinc-400">{{ $label }}</span>
    <span @class([
        'text-right font-medium',
        'text-emerald-400' => $ok === true,
        'text-amber-400' => $ok === false,
        'text-white' => $ok === null,
    ])>{{ $slot }}</span>
</div>
