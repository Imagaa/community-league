{{-- QR kartu digital: kode berganti tiap 30 detik (screenshot cepat basi), diambil ulang dari server. --}}
<div
    x-data="{
        qr: null,
        async load() {
            if (! document.body.contains($el)) return;
            const res = await fetch(@js(route('membership.card.code')), { headers: { Accept: 'application/json' } }).catch(() => null);
            const data = res?.ok ? await res.json() : null;
            if (data) this.qr = data.qr;
            setTimeout(() => this.load(), ((data?.refresh_in ?? 10) * 1000) + 300);
        },
    }"
    x-init="load()"
    {{ $attributes->class('flex items-center gap-4 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900') }}
>
    <div class="flex size-28 shrink-0 items-center justify-center rounded-xl bg-white p-1.5 ring-1 ring-zinc-200 dark:ring-0">
        <img x-show="qr" :src="qr" alt="{{ __('QR kartu member') }}" class="size-full [image-rendering:pixelated]">
        <flux:icon x-show="! qr" name="qr-code" class="size-10 animate-pulse text-zinc-300" />
    </div>
    <div class="min-w-0 text-sm">
        <p class="font-semibold text-zinc-900 dark:text-white">{{ __('QR kartu digital') }}</p>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Tunjukkan ke panitia untuk check-in & poin. Kode berganti otomatis, jadi tangkapan layar tidak bisa dipakai.') }}</p>
    </div>
</div>
