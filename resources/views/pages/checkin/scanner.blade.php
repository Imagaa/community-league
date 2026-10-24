<!DOCTYPE html>
<html lang="id" class="dark">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
        <meta name="robots" content="noindex, nofollow" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="theme-color" content="#000000" />
        <title>Scanner · {{ $match->title }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        @fonts
        @vite(['resources/css/app.css'])
        <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js" defer></script>
    </head>
    <body class="fixed inset-0 overflow-hidden bg-black text-white antialiased">
        <video id="video" playsinline muted autoplay class="absolute inset-0 size-full object-cover"></video>
        <canvas id="canvas" class="hidden"></canvas>

        {{-- Bingkai bidik --}}
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <div class="aspect-square w-[70vmin] rounded-3xl border-4 border-white/80 shadow-[0_0_0_100vmax_rgba(0,0,0,0.45)]"></div>
        </div>

        <header class="absolute inset-x-0 top-0 flex items-start justify-between gap-3 bg-gradient-to-b from-black/80 to-transparent p-4 pt-[max(1rem,env(safe-area-inset-top))]">
            <div class="min-w-0">
                <img src="{{ asset('images/secondary-logo-landscape.webp') }}" alt="Community League" class="mb-1 h-6 w-auto">
                <p class="truncate text-sm font-semibold">{{ $match->title }}</p>
                <p class="text-xs text-white/70">{{ $match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('D, d M · H.i') }} · {{ $match->venue->name }}</p>
            </div>
            <div class="shrink-0 rounded-xl bg-white/15 px-3 py-1.5 text-center backdrop-blur">
                <p class="text-[10px] tracking-wide text-white/70 uppercase">Hadir</p>
                <p class="text-lg font-bold"><span id="hadir">{{ $stats['hadir'] }}</span><span class="text-sm text-white/60">/<span id="total">{{ $stats['total'] }}</span></span></p>
            </div>
        </header>

        @include('partials.scanner-script', ['scanner' => [
            'scan' => route('checkin.scan', $match->scanner_token),
            'link' => route('checkin.link', $match->scanner_token),
            'renew' => route('checkin.renew', $match->scanner_token),
            'renewStatus' => route('checkin.renew.status', [$match->scanner_token, '__ID__']),
            'replace' => null,
            'preselect' => null,
        ]])
    </body>
</html>
