{{--
    Scanner bersama (scanner match & Scanner Kartu admin): kamera QR + tap NFC (Chrome Android),
    panel hasil + tombol aksi, mode "hubungkan kartu fisik", dan layar tunggu QRIS perpanjang.
    Halaman menyediakan <video id="video">, <canvas id="canvas">, opsional #hadir/#total, dan jsQR.
    $scanner = ['scan' => url, 'link' => url, 'renew' => ?url, 'renewStatus' => ?url dengan __ID__,
                'replace' => ?url, 'preselect' => ?['id' => int, 'name' => string]]
--}}
<p id="message" class="absolute inset-x-0 top-1/2 hidden -translate-y-1/2 px-8 text-center text-sm"></p>

<div id="linkbar" class="absolute inset-x-3 top-28 hidden items-center gap-3 rounded-2xl bg-orange-600 p-3 text-sm font-semibold shadow-xl">
    <span id="linkbar-text" class="flex-1"></span>
    <button id="linkbar-cancel" type="button" class="rounded-lg bg-white/20 px-3 py-1.5">Batal</button>
</div>

<div id="result" class="absolute inset-x-3 bottom-28 hidden rounded-3xl p-5 text-center shadow-2xl">
    <p id="result-title" class="text-2xl font-bold"></p>
    <p id="result-name" class="mt-1 text-lg font-semibold"></p>
    <p id="result-detail" class="mt-1 text-sm opacity-90"></p>
    <p id="result-member" class="mt-2 text-xs opacity-90"></p>
    <ul id="result-notes" class="mt-2 space-y-1 text-sm font-semibold"></ul>
    <div id="result-actions" class="mt-3 flex flex-col gap-2"></div>
</div>

<div id="renew" class="absolute inset-0 hidden flex-col items-center justify-center gap-4 bg-black/90 p-6 text-center">
    <p class="text-sm text-white/70">Perpanjang membership</p>
    <p id="renew-name" class="text-xl font-bold"></p>
    <p id="renew-total" class="text-3xl font-bold text-orange-400"></p>
    <a id="renew-open" href="#" target="_blank" rel="noopener" class="rounded-2xl bg-orange-600 px-8 py-4 text-lg font-bold">Buka QRIS</a>
    <p class="max-w-xs text-xs text-white/60">QRIS tampil di tab baru -- minta member memindainya dengan e-wallet/m-banking, lalu kembali ke tab ini.</p>
    <p id="renew-status" class="text-sm font-semibold text-white/90">Menunggu pembayaran…</p>
    <button id="renew-close" type="button" class="rounded-xl bg-white/15 px-5 py-2 text-sm">Tutup</button>
</div>

<footer class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-5 p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
    <button id="switch" type="button" aria-label="Ganti kamera depan/belakang"
            class="flex size-16 items-center justify-center rounded-full bg-white/20 backdrop-blur active:scale-95">
        <flux:icon name="arrow-path" class="size-8" />
    </button>
    <button id="nfc" type="button" class="hidden h-16 items-center gap-2 rounded-full bg-white/20 px-6 font-semibold backdrop-blur active:scale-95">
        <flux:icon name="signal" class="size-6" />
        <span id="nfc-label">Tap NFC</span>
    </button>
</footer>

<script>
    (() => {
        const cfg = @js($scanner);
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const $ = (id) => document.getElementById(id);
        const video = $('video');
        const canvas = $('canvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        const box = $('result');
        const nfcSupported = 'NDEFReader' in window;
        const colors = { ok: 'bg-emerald-600', already: 'bg-amber-500', info: 'bg-sky-700', no_ticket: 'bg-red-600', other_match: 'bg-red-600', not_paid: 'bg-red-600', invalid: 'bg-red-600', error: 'bg-zinc-700' };
        const labels = { renew: 'Perpanjang sekarang (QRIS)', link: 'Hubungkan kartu fisik', replace: 'Ganti kartu (hilang)' };

        let facing = 'environment';
        let stream = null;
        let busy = false;
        let lastCode = null;
        let lastAt = 0;
        let detector = null;
        let hideTimer = null;
        let linking = null;
        let poll = null;
        let nfcOn = false;

        function say(text) {
            $('message').textContent = text;
            $('message').classList.toggle('hidden', ! text);
        }

        async function start() {
            stream?.getTracks().forEach((t) => t.stop());
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: facing } }, audio: false });
                video.srcObject = stream;
                video.style.transform = facing === 'user' ? 'scaleX(-1)' : '';
                await video.play();
                say('');
            } catch (e) {
                say('Kamera tidak bisa dibuka. Izinkan akses kamera di browser, lalu muat ulang halaman ini.');
            }
        }

        async function readCode() {
            if (video.readyState < 2) return null;
            if (detector) {
                const codes = await detector.detect(video).catch(() => []);
                return codes[0]?.rawValue ?? null;
            }
            if (! window.jsQR) return null;
            const w = video.videoWidth, h = video.videoHeight;
            if (! w || ! h) return null;
            const scale = Math.min(1, 640 / Math.max(w, h));
            canvas.width = Math.round(w * scale);
            canvas.height = Math.round(h * scale);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            return window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' })?.data ?? null;
        }

        function beep(ok) {
            try {
                const audio = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audio.createOscillator();
                osc.frequency.value = ok ? 880 : 220;
                osc.connect(audio.destination);
                osc.start();
                osc.stop(audio.currentTime + (ok ? 0.12 : 0.3));
            } catch (e) {}
            navigator.vibrate?.(ok ? 80 : [80, 60, 80]);
        }

        async function post(url, body) {
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(body),
                });
                if (res.ok) return await res.json();
                const title = { 429: 'Terlalu cepat', 403: 'Scan kartu member dulu', 419: 'Sesi habis, muat ulang halaman' }[res.status] ?? 'Gagal memeriksa';
                return { result: 'error', ok: false, title, detail: 'Coba lagi.', message: title };
            } catch (e) {
                return { result: 'error', ok: false, title: 'Tidak ada koneksi', detail: 'Periksa internet, lalu coba lagi.', message: 'Tidak ada koneksi.' };
            }
        }

        function button(label, cls, onClick) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'rounded-xl px-4 py-3 text-sm font-bold ' + cls;
            b.textContent = label;
            b.addEventListener('click', () => onClick(b));
            return b;
        }

        function memberLine(m) {
            if (! m) return '';
            return [m.number, m.active ? 'Aktif s/d ' + m.expires : 'Habis ' + m.expires, 'Kartu ' + (m.card_active ? 'aktif' : 'belum aktif'), m.nfc ? 'NFC terhubung' : null].filter(Boolean).join(' · ');
        }

        function setLinking(member) {
            linking = member;
            $('linkbar').classList.toggle('hidden', ! member);
            $('linkbar').classList.toggle('flex', !! member);
            if (member) $('linkbar-text').textContent = 'Hubungkan kartu fisik ' + member.name + ': scan QR kartunya' + (nfcSupported ? ' atau tap NFC.' : '.');
        }

        function hide() {
            clearTimeout(hideTimer);
            box.classList.add('hidden');
            busy = false;
        }

        function show(data) {
            clearTimeout(hideTimer);
            box.className = 'absolute inset-x-3 bottom-28 rounded-3xl p-5 text-center shadow-2xl ' + (colors[data.result] ?? colors.error);
            $('result-title').textContent = data.title ?? 'Gagal';
            $('result-name').textContent = data.name ? data.name + (data.code ? ' · ' + data.code : '') : '';
            $('result-detail').textContent = data.detail ?? '';
            $('result-member').textContent = memberLine(data.member);
            $('result-notes').replaceChildren(...(data.notes ?? []).map((n) => Object.assign(document.createElement('li'), { textContent: n })));
            const actions = (data.actions ?? []).filter((a) => cfg[a] && data.member);
            $('result-actions').replaceChildren(
                ...actions.map((a) => button(labels[a], 'bg-white text-zinc-900', (b) => act(a, data.member, b))),
                ...(actions.length ? [button('Tutup', 'bg-white/20', hide)] : []),
            );
            if (data.stats && $('hadir')) {
                $('hadir').textContent = data.stats.hadir;
                $('total').textContent = data.stats.total;
            }
            beep(['ok', 'info'].includes(data.result));
            if (! actions.length) hideTimer = setTimeout(hide, 2600);
        }

        async function act(action, member, btn) {
            if (action === 'link') {
                hide();
                setLinking(member);
            } else if (action === 'renew') {
                await renew(member);
            } else if (action === 'replace') {
                // Konfirmasi dua ketukan -- kartu lama langsung tidak berlaku.
                if (! btn.dataset.armed) {
                    btn.dataset.armed = '1';
                    btn.textContent = 'Yakin? Kartu lama diblokir -- tekan lagi';
                    return;
                }
                const r = await post(cfg.replace, { membership_id: member.id });
                show({ result: r.ok ? 'info' : 'error', title: r.message ?? r.title, name: member.name, member: r.member });
            }
        }

        async function renew(member) {
            hide();
            busy = true;
            const data = await post(cfg.renew, { membership_id: member.id });
            if (! data.link) {
                show(data);
                return;
            }
            $('renew-name').textContent = data.name;
            $('renew-total').textContent = data.total;
            $('renew-open').href = data.link;
            $('renew-status').textContent = 'Menunggu pembayaran…';
            $('renew').classList.replace('hidden', 'flex');
            const url = cfg.renewStatus.replace('__ID__', data.purchase_id);
            clearInterval(poll);
            poll = setInterval(async () => {
                const s = await fetch(url, { headers: { Accept: 'application/json' } }).then((r) => (r.ok ? r.json() : null)).catch(() => null);
                if (s?.paid) {
                    clearInterval(poll);
                    $('renew-status').textContent = 'Lunas ✓ Membership & kartu aktif s/d ' + s.expires;
                    beep(true);
                } else if (s?.status === 'expired') {
                    clearInterval(poll);
                    $('renew-status').textContent = 'Tagihan kedaluwarsa. Tutup lalu coba lagi.';
                }
            }, 3000);
        }

        async function handle(payload) {
            busy = true;
            if (linking) {
                const r = await post(cfg.link, { membership_id: linking.id, ...payload });
                show({ result: r.ok ? 'ok' : 'invalid', title: r.ok ? 'Kartu terhubung' : 'Gagal menghubungkan', name: linking.name, detail: r.message ?? r.detail ?? '', member: r.member });
                if (r.ok) setLinking(null);
                return;
            }
            show(await post(cfg.scan, payload));
        }

        function fresh(key) {
            // Kode/kartu yang sama masih di depan kamera atau HP -- jangan kirim ulang terus-menerus.
            if (key === lastCode && Date.now() - lastAt < 5000) return false;
            lastCode = key;
            lastAt = Date.now();
            return true;
        }

        async function tick() {
            if (! busy) {
                const code = await readCode();
                if (code && fresh(code)) await handle({ code });
            }
            setTimeout(tick, 200);
        }

        async function startNfc() {
            if (nfcOn) return;
            try {
                const reader = new NDEFReader();
                await reader.scan();
                nfcOn = true;
                $('nfc-label').textContent = 'NFC siap';
                reader.addEventListener('reading', (e) => {
                    if (! busy && e.serialNumber && fresh('nfc:' + e.serialNumber)) handle({ nfc: e.serialNumber });
                });
                reader.addEventListener('readingerror', () => {
                    say('Kartu NFC tidak terbaca, tempelkan lagi.');
                    setTimeout(() => say(''), 2500);
                });
            } catch (e) {
                say('NFC tidak bisa dipakai. Nyalakan NFC & izinkan di Chrome Android.');
                setTimeout(() => say(''), 3500);
            }
        }

        $('switch').addEventListener('click', () => {
            facing = facing === 'environment' ? 'user' : 'environment';
            start();
        });
        $('linkbar-cancel').addEventListener('click', () => setLinking(null));
        $('renew-close').addEventListener('click', () => {
            clearInterval(poll);
            $('renew').classList.replace('flex', 'hidden');
            busy = false;
        });
        if (nfcSupported) {
            $('nfc').classList.replace('hidden', 'flex');
            $('nfc').addEventListener('click', startNfc);
        }
        if (cfg.preselect) setLinking(cfg.preselect);

        (async () => {
            if (! navigator.mediaDevices?.getUserMedia) {
                say('Browser ini tidak mendukung kamera. Buka link ini di Chrome atau Safari terbaru.');
                return;
            }
            if ('BarcodeDetector' in window) {
                const formats = await BarcodeDetector.getSupportedFormats().catch(() => []);
                if (formats.includes('qr_code')) detector = new BarcodeDetector({ formats: ['qr_code'] });
            }
            await start();
            tick();
        })();
    })();
</script>
