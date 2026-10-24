@props(['icon', 'title', 'section', 'editing' => false, 'save' => null, 'description' => null])

{{--
    Satu bagian setelan: tampilkan nilai tersimpan (slot summary) + tombol "Ubah". Isian baru
    muncul setelah "Ubah" ditekan, dan nilai baru hanya tersimpan saat "Simpan" -- "Batal"
    membuang perubahan. Pakai: wire:click edit('{section}') / {save} / cancelEdit di komponen.
--}}
<section {{ $attributes->class('space-y-4 rounded-2xl border border-navy-800 bg-navy-900 p-4') }}>
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <flux:icon :name="$icon" class="size-5 text-orange-400" />
            <h2 class="font-semibold text-white">{{ $title }}</h2>
        </div>
        @unless ($editing)
            <button type="button" wire:click="edit('{{ $section }}')"
                    class="inline-flex items-center gap-1 rounded-lg border border-navy-700 px-3 py-1.5 text-xs font-semibold text-zinc-200 hover:bg-navy-800">
                <flux:icon name="pencil-square" variant="micro" /> Ubah
            </button>
        @endunless
    </div>

    @if ($description)
        <p class="text-xs text-zinc-400">{{ $description }}</p>
    @endif

    @if ($editing)
        <div class="space-y-4 rounded-xl border border-orange-600/40 bg-navy-950/50 p-4">
            {{ $slot }}
            @if ($save)
                <div class="flex gap-2">
                    <button type="button" wire:click="{{ $save }}" wire:loading.attr="disabled"
                            class="rounded-lg bg-orange-600 px-4 py-2 text-xs font-bold text-white hover:bg-orange-700 disabled:opacity-50">Simpan perubahan</button>
                    <button type="button" wire:click="cancelEdit"
                            class="rounded-lg border border-navy-700 px-4 py-2 text-xs text-zinc-300 hover:bg-navy-800">Batal</button>
                </div>
            @endif
        </div>
    @else
        {{ $summary }}
    @endif
</section>
