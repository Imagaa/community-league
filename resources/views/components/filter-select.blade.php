@props(['name', 'options', 'selected' => null, 'placeholder' => null, 'autoSubmit' => false, 'compact' => false, 'wireModel' => null])

@php
    // request()->query('type[]=abc') bisa datang sebagai array -- (string) $selected akan
    // memicu "Array to string conversion". Sama seperti pola is_string(...) di input tanggal.
    $selected = is_string($selected) || is_numeric($selected) ? (string) $selected : '';

    $triggerClass = $compact
        ? 'flex items-center gap-1 rounded-full border-0 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 shadow-sm dark:bg-navy-800 dark:text-zinc-200'
        : 'flex h-10 w-full items-center justify-between rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 text-base text-zinc-700 shadow-xs sm:text-sm dark:border-navy-700 dark:bg-navy-950 dark:text-zinc-200';
@endphp

{{--
    Bukan <flux:select> (native <select> di baliknya) -- daftar pilihan yang muncul saat
    dibuka dirender OS/browser, beda tampilan antara Android & iPhone. Ini custom penuh
    lewat flux:dropdown + flux:menu (popover JS, bukan elemen native), jadi tampilannya
    seragam di semua platform. Pola Alpine sama seperti password-strength-meter.blade.php.
--}}
<div
    x-data="{
        value: {{ Js::from($selected) }},
        label: {{ Js::from($placeholder) }},
        options: {{ Js::from(collect($options)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values()) }},
        init() {
            const match = this.options.find(o => o.value === this.value);
            this.label = match ? match.label : {{ Js::from($placeholder) }};
        },
        select(opt) {
            this.value = opt.value;
            this.label = opt.label;
            @if ($wireModel)
                $wire.set({{ Js::from($wireModel) }}, opt.value);
            @endif
            @if ($autoSubmit)
                this.$nextTick(() => this.$el.closest('form')?.submit());
            @endif
        },
    }"
    {{ $attributes->class($compact ? 'relative inline-block' : 'relative') }}
>
    <input type="hidden" name="{{ $name }}" :value="value">

    <flux:dropdown class="{{ $compact ? 'inline-block' : 'block w-full' }}">
        <button type="button" class="{{ $triggerClass }}">
            <span x-text="label" class="truncate"></span>
            <flux:icon name="chevron-down" class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
        </button>

        <flux:menu class="w-64 max-h-64 overflow-y-auto">
            @if ($placeholder)
                <flux:menu.item x-on:click="select({ value: '', label: {{ Js::from($placeholder) }} })">
                    <span class="flex-1">{{ $placeholder }}</span>
                    <flux:icon name="check" x-show="value === ''" class="size-4 text-red-600 dark:text-orange-500" />
                </flux:menu.item>
            @endif

            @foreach ($options as $value => $label)
                <flux:menu.item x-on:click="select({ value: {{ Js::from((string) $value) }}, label: {{ Js::from($label) }} })">
                    <span class="flex-1">{{ $label }}</span>
                    <flux:icon name="check" x-show="value === {{ Js::from((string) $value) }}" class="size-4 text-red-600 dark:text-orange-500" />
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>
</div>
