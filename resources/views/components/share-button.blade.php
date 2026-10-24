@props(['url', 'title', 'text' => '', 'compact' => false])

{{--
    Di HP (layar sentuh) memakai lembar bagikan bawaan OS (navigator.share) -- langsung ke WA,
    IG, Telegram, dst. Di desktop / browser tanpa navigator.share, muncul kotak bagikan sendiri.
    Kartu pratinjau yang muncul saat link ditempel datang dari meta Open Graph halaman.
--}}
@php
    $message = trim($text.' '.$url);
    $targets = [
        ['label' => 'WhatsApp', 'href' => 'https://wa.me/?text='.rawurlencode($message), 'class' => 'bg-[#25D366]'],
        ['label' => 'Telegram', 'href' => 'https://t.me/share/url?url='.rawurlencode($url).'&text='.rawurlencode($text), 'class' => 'bg-[#229ED9]'],
        ['label' => 'X', 'href' => 'https://x.com/intent/post?url='.rawurlencode($url).'&text='.rawurlencode($text), 'class' => 'bg-black'],
        ['label' => 'Facebook', 'href' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url), 'class' => 'bg-[#1877F2]'],
    ];
@endphp
<div x-data="{
        open: false,
        copied: false,
        async share() {
            if (navigator.share && window.matchMedia('(pointer: coarse)').matches) {
                try { await navigator.share({ title: @js($title), text: @js($text), url: @js($url) }); } catch (e) {}
                return;
            }
            this.open = true;
        },
        async copy() {
            try { await navigator.clipboard.writeText(@js($url)); } catch (e) { this.$refs.link.select(); document.execCommand('copy'); }
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },
    }" {{ $attributes }}>
    @if ($compact)
        {{-- Ikon saja -- dipakai di baris aksi admin supaya tidak memakan lebar. --}}
        <button type="button" @click="share()" title="{{ __('Bagikan') }}" aria-label="{{ __('Bagikan') }}"
                class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white">
            <flux:icon name="share" class="size-5" />
        </button>
    @else
        <button type="button" @click="share()"
                class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-navy-700 dark:text-zinc-200 dark:hover:bg-navy-800">
            <flux:icon name="share" variant="micro" />
            {{ __('Bagikan') }}
        </button>
    @endif

    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity @keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4 sm:items-center" @click.self="open = false">
            <div x-show="open" x-transition role="dialog" aria-modal="true" aria-label="{{ __('Bagikan') }}"
                 class="w-full max-w-sm space-y-4 rounded-2xl bg-white p-5 shadow-xl dark:bg-navy-900">
                <div class="flex items-center justify-between">
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Bagikan match') }}</p>
                    <button type="button" @click="open = false" class="rounded-full p-1 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-navy-800" aria-label="{{ __('Tutup') }}">
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                <div class="grid grid-cols-4 gap-2 text-center">
                    @foreach ($targets as $target)
                        <a href="{{ $target['href'] }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 text-[11px] text-zinc-600 dark:text-zinc-300">
                            <span class="flex size-12 items-center justify-center rounded-full text-lg font-bold text-white {{ $target['class'] }}">{{ mb_substr($target['label'], 0, 1) }}</span>
                            {{ $target['label'] }}
                        </a>
                    @endforeach
                </div>

                <div class="flex gap-2">
                    <input x-ref="link" type="text" readonly value="{{ $url }}" @focus="$el.select()"
                           class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-2 text-xs text-zinc-700 dark:border-navy-700 dark:bg-navy-950 dark:text-zinc-200">
                    <button type="button" @click="copy()"
                            class="shrink-0 rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700"
                            x-text="copied ? @js(__('Tersalin')) : @js(__('Salin link'))"></button>
                </div>
            </div>
        </div>
    </template>
</div>
