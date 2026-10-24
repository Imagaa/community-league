<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

@php
    $appName = config('app.name', 'Laravel');
    $pageTitle = filled($title ?? null) ? $title.' - '.$appName : $appName;
@endphp

<title>
    {{-- Prefix judul tab -- bukti tambahan kalau yang bocor cuma screenshot address bar/tab,
         tanpa isi halaman ikut ke-capture. --}}
    {{ config('services.sandbox_gate.enabled') ? '[SANDBOX] '.$pageTitle : $pageTitle }}
</title>

@php
    // Kartu pratinjau link (WhatsApp/Telegram/X/Facebook). Halaman boleh mengirim
    // :meta="['title' => .., 'description' => .., 'image' => .., 'image_width' => .., 'image_height' => ..]".
    $meta = ($meta ?? []) + [
        'title' => $pageTitle,
        'description' => __('Cari dan pesan slot match mini soccer & sepak bola komunitas di Community League.'),
        'image' => asset('images/icon-512.png'),
    ];
    $largeImage = isset($meta['image_width']);
@endphp
<meta name="description" content="{{ $meta['description'] }}">
<meta property="og:site_name" content="{{ $appName }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="id_ID">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="{{ $meta['title'] }}">
<meta property="og:description" content="{{ $meta['description'] }}">
<meta property="og:image" content="{{ $meta['image'] }}">
@if ($largeImage)
    <meta property="og:image:width" content="{{ $meta['image_width'] }}">
    <meta property="og:image:height" content="{{ $meta['image_height'] }}">
@endif
<meta name="twitter:card" content="{{ $largeImage ? 'summary_large_image' : 'summary' }}">

@if (config('services.sandbox_gate.enabled'))
    {{-- Favicon kotak oranye polos beda dari favicon.ico asli -- data URI SVG, tidak perlu file
         baru/build step. Cuma dipakai kalau sandbox_gate menyala (staging), lihat komentar
         di config/services.php. --}}
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='16' fill='%23f59e0b'/><text x='50' y='68' font-size='58' font-weight='bold' text-anchor='middle' fill='%23451a03'>S</text></svg>">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
@endif
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#ED1F24">
<meta name="color-scheme" content="light dark">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }

    window.deferredInstallPrompt = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.deferredInstallPrompt = event;
        window.dispatchEvent(new CustomEvent('pwa-installable'));
    });
</script>
