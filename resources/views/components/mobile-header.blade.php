<header class="rounded-b-3xl bg-red-600 px-4 py-4 text-white md:hidden dark:bg-navy-900">
    <div class="flex items-center justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2">
            {{-- Logo baru transparan tanpa kartu putih bawaan -- di atas header merah/navy warna
                 logo (merah di light, oranye di dark) nyaris hilang tanpa latar kontras. Light
                 mode butuh bg-white (merah di atas merah = invisible); dark mode sudah cukup
                 kontras (oranye di atas navy) jadi latarnya dibuat transparan. --}}
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white p-1 dark:bg-transparent">
                <x-app-logo-icon class="h-full w-full object-contain" />
            </span>
            <div class="min-w-0 leading-tight">
                <p class="font-display truncate text-lg font-extrabold uppercase tracking-wide">{{ config('app.name') }}</p>
                <p class="truncate text-[11px] font-medium uppercase tracking-widest text-red-100 dark:text-white/60">
                    {{ __('Football & Mini Soccer') }}
                </p>
            </div>
        </div>

        @auth
            <livewire:pages::notifications.bell variant="mobile" />
        @endauth
    </div>
</header>
