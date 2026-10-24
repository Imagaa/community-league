<?php

use App\Http\Middleware\EnsureAdmin;
use App\Models\PaymentSettings;
use App\Support\Money;
use App\Support\Qris;
use App\Support\TicketQrCode;
use App\Support\WhatsApp;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Setelan')] class extends Component {
    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    /** Bagian yang sedang diubah (qris/fee/sumopod/drive/whatsapp); null = semua mode lihat. */
    public ?string $editing = null;

    public string $notice = '';

    // Isian -- baru diisi saat bagiannya dibuka lewat edit(), tersimpan hanya lewat save*().
    public string $qris_text = '';
    public bool $sumopod_enabled = false;
    public string $sumopod_mode = 'sandbox';
    /** Kosong = key lama dipertahankan (nilai lama tidak pernah dikirim balik ke browser). */
    public string $sumopod_api_key = '';
    public string $sumopod_webhook_token = '';
    public string $google_drive_api_key = '';
    public string $admin_whatsapp = '';
    public string $admin_fee_percent = '1';
    public ?int $admin_fee_flat = 300;

    public function edit(string $section): void
    {
        $s = $this->settings;
        $this->resetErrorBag();
        $this->notice = '';
        $this->reset('qris_text', 'sumopod_api_key', 'sumopod_webhook_token', 'google_drive_api_key');

        match ($section) {
            'fee' => $this->fill([
                'admin_fee_percent' => (string) (float) $s->admin_fee_percent,
                'admin_fee_flat' => $s->admin_fee_flat,
            ]),
            'sumopod' => $this->fill([
                'sumopod_enabled' => $s->sumopod_enabled,
                'sumopod_mode' => $s->sumopod_mode ?? 'sandbox',
            ]),
            'whatsapp' => $this->admin_whatsapp = WhatsApp::local($s->admin_whatsapp),
            default => null,
        };

        $this->editing = $section;
    }

    public function cancelEdit(): void
    {
        $this->resetErrorBag();
        $this->reset('editing', 'qris_text', 'sumopod_api_key', 'sumopod_webhook_token', 'google_drive_api_key');
    }

    public function saveQris(string $payload = ''): void
    {
        $this->resetErrorBag();
        $payload = trim($payload !== '' ? $payload : $this->qris_text);

        if (! Qris::isValid($payload)) {
            $this->addError('qris', 'QR tidak terbaca sebagai QRIS yang valid. Coba gambar yang lebih jelas atau tempel teksnya.');

            return;
        }

        $this->settings->update(['qris_payload' => $payload, 'qris_merchant_name' => Qris::merchantName($payload)]);
        $this->done('QRIS tersimpan. Pembeli kini bisa membayar lewat QRIS.');
    }

    public function saveFee(): void
    {
        $this->admin_fee_percent = str_replace(',', '.', trim($this->admin_fee_percent));
        $this->validate([
            'admin_fee_percent' => ['required', 'numeric', 'min:0', 'max:10'],
            'admin_fee_flat' => ['required', 'integer', 'min:0', 'max:100000'],
        ], [], ['admin_fee_percent' => 'persen', 'admin_fee_flat' => 'biaya tetap']);

        $this->settings->update(['admin_fee_percent' => $this->admin_fee_percent, 'admin_fee_flat' => $this->admin_fee_flat]);
        $this->done('Biaya admin tersimpan. Berlaku untuk booking baru.');
    }

    public function saveSumopod(): void
    {
        $this->validate([
            'sumopod_mode' => ['required', Rule::in(['sandbox', 'live'])],
            'sumopod_api_key' => ['nullable', 'string', 'max:500'],
            'sumopod_webhook_token' => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->sumopod_enabled && blank($this->sumopod_api_key) && blank($this->settings->sumopodApiKey())) {
            $this->addError('sumopod_api_key', 'Isi API key Sumopod sebelum mengaktifkannya.');

            return;
        }

        $this->settings->update(array_filter([
            'sumopod_enabled' => $this->sumopod_enabled,
            'sumopod_mode' => $this->sumopod_mode,
            'sumopod_api_key' => trim($this->sumopod_api_key) ?: null,
            'sumopod_webhook_token' => trim($this->sumopod_webhook_token) ?: null,
        ], fn ($value) => $value !== null));

        $this->done('Setelan Sumopod tersimpan.');
    }

    public function saveDrive(): void
    {
        $this->validate(['google_drive_api_key' => ['required', 'string', 'max:200']], [], ['google_drive_api_key' => 'API key']);

        $this->settings->update(['google_drive_api_key' => trim($this->google_drive_api_key)]);
        $this->done('API key Google Drive tersimpan.');
    }

    public function saveWhatsapp(): void
    {
        $this->admin_whatsapp = WhatsApp::local($this->admin_whatsapp);
        $this->validate(['admin_whatsapp' => ['required', 'regex:'.WhatsApp::LOCAL_PATTERN]], [
            'admin_whatsapp.regex' => 'Nomor WhatsApp harus diawali 08 dan terdiri dari 10-14 angka.',
        ]);

        $this->settings->update(['admin_whatsapp' => WhatsApp::normalize($this->admin_whatsapp)]);
        $this->done('Nomor WhatsApp admin tersimpan.');
    }

    private function done(string $message): void
    {
        $this->cancelEdit();
        unset($this->settings, $this->qrisPreview);
        $this->notice = $message;
    }

    /** Contoh perhitungan langsung dari isian (belum disimpan). */
    #[Computed]
    public function feeExample(): int
    {
        $percent = str_replace(',', '.', $this->admin_fee_percent);

        return (new PaymentSettings([
            'admin_fee_percent' => is_numeric($percent) ? $percent : 0,
            'admin_fee_flat' => (int) $this->admin_fee_flat,
        ]))->adminFeeFor(50000);
    }

    #[Computed]
    public function settings(): PaymentSettings
    {
        return PaymentSettings::current();
    }

    #[Computed]
    public function qrisPreview(): ?string
    {
        return $this->settings->hasQris() ? TicketQrCode::dataUri((string) $this->settings->qris_payload) : null;
    }

    /** "••••e4f9" -- cukup untuk mengenali key mana yang terpasang tanpa membocorkannya. */
    public static function masked(?string $secret): string
    {
        return '••••'.substr((string) $secret, -4);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-5">
    <div>
        <h1 class="text-xl font-bold text-white">Setelan</h1>
        <p class="text-xs text-zinc-400">Pembayaran, biaya admin, integrasi, dan kontak admin.</p>
    </div>

    @if ($notice)
        <p class="rounded-xl border border-emerald-800 bg-emerald-950 px-4 py-3 text-emerald-300">{{ $notice }}</p>
    @endif

    {{-- QRIS statis: di-decode di browser (jsQR), server memvalidasi CRC sebelum menyimpan. --}}
    <x-admin.setting-card icon="qr-code" title="QRIS statis" section="qris" :editing="$editing === 'qris'">
        <x-slot:summary>
            @if ($this->qrisPreview)
                <div class="flex items-center gap-4">
                    <img src="{{ $this->qrisPreview }}" alt="QRIS tersimpan" class="size-24 rounded-lg bg-white p-1.5">
                    <div class="text-xs text-zinc-400">
                        <p class="text-sm font-semibold text-emerald-400">&check; {{ $this->settings->qris_merchant_name ?? 'QRIS tersimpan' }}</p>
                        <p>Pembeli menerima QRIS dinamis dari kode ini dengan nominal + kode unik terisi otomatis.</p>
                    </div>
                </div>
            @else
                <p class="rounded-lg bg-amber-950 px-3 py-2 text-xs text-amber-300">Belum ada QRIS. Pembeli belum bisa membayar lewat QRIS manual.</p>
            @endif
        </x-slot:summary>

        <div class="space-y-3"
             x-data="{
                 reading: false,
                 failed: false,
                 async read(file) {
                     if (! file) return;
                     this.reading = true; this.failed = false;
                     try {
                         const bitmap = await createImageBitmap(file);
                         const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
                         const canvas = document.createElement('canvas');
                         canvas.width = Math.round(bitmap.width * scale);
                         canvas.height = Math.round(bitmap.height * scale);
                         const ctx = canvas.getContext('2d');
                         ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                         const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                         const code = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' });
                         if (code?.data) { await $wire.saveQris(code.data); } else { this.failed = true; }
                     } catch (e) { this.failed = true; }
                     this.reading = false;
                 },
             }">
            <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
            <label class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border-2 border-dashed border-navy-700 p-5 text-center text-zinc-400 hover:border-orange-500">
                <flux:icon name="arrow-up-tray" class="size-7" />
                <span x-show="! reading">Unggah gambar QRIS statis baru</span>
                <span x-show="reading" x-cloak>Membaca QR...</span>
                <input type="file" accept="image/*" class="sr-only" @change="read($event.target.files[0]); $event.target.value = ''">
            </label>
            <p x-show="failed" x-cloak class="text-xs text-red-400">QR tidak ditemukan di gambar. Crop gambar supaya QR terlihat jelas, atau tempel teks QRIS di bawah.</p>
            @error('qris') <p class="text-xs text-red-400">{{ $message }}</p> @enderror

            <details class="text-xs text-zinc-400">
                <summary class="cursor-pointer">Atau tempel teks QRIS (hasil scan aplikasi pemindai QR)</summary>
                <div class="mt-2 space-y-2">
                    <textarea wire:model="qris_text" rows="3" class="admin-input font-mono text-xs" placeholder="000201010211..."></textarea>
                    <button type="button" wire:click="saveQris" class="rounded-lg bg-orange-600 px-3 py-1.5 font-bold text-white">Simpan teks QRIS</button>
                </div>
            </details>
            <button type="button" wire:click="cancelEdit" class="rounded-lg border border-navy-700 px-4 py-2 text-xs text-zinc-300 hover:bg-navy-800">Batal</button>
        </div>
    </x-admin.setting-card>

    <x-admin.setting-card icon="receipt-percent" title="Biaya admin" section="fee" :editing="$editing === 'fee'" save="saveFee"
                          description='Ditambahkan ke tagihan pembeli di semua metode bayar, dibulatkan ke atas ke Rp100. Pembeli hanya melihat nominalnya sebagai "Biaya admin".'>
        <x-slot:summary>
            <div>
                <x-admin.setting-row label="Persen dari harga">{{ str_replace('.', ',', (string) (float) $this->settings->admin_fee_percent) }}%</x-admin.setting-row>
                <x-admin.setting-row label="Biaya tetap">{{ Money::rupiah($this->settings->admin_fee_flat) }}</x-admin.setting-row>
                <x-admin.setting-row label="Contoh harga Rp 50.000">biaya admin {{ Money::rupiah($this->settings->adminFeeFor(50000)) }}</x-admin.setting-row>
            </div>
        </x-slot:summary>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field label="Persen dari harga" name="admin_fee_percent">
                <div class="relative">
                    <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="admin_fee_percent" class="admin-input pr-9">
                    <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 font-semibold text-zinc-500">%</span>
                </div>
            </x-admin.field>
            <x-admin.field label="Biaya tetap" name="admin_fee_flat">
                <x-admin.money-input wire:model.live.debounce.400ms="admin_fee_flat" placeholder="300" />
            </x-admin.field>
        </div>
        <p class="rounded-lg bg-navy-900 px-3 py-2 text-xs text-zinc-300">
            Contoh: harga Rp 50.000 &rarr; biaya admin <strong class="text-white">{{ Money::rupiah($this->feeExample) }}</strong>. Berlaku untuk booking baru.
        </p>
    </x-admin.setting-card>

    <x-admin.setting-card icon="bolt" title="Sumopod (bayar otomatis)" section="sumopod" :editing="$editing === 'sumopod'" save="saveSumopod">
        <x-slot:summary>
            <div>
                <x-admin.setting-row label="Bayar otomatis" :ok="$this->settings->sumopodAvailable()">
                    {{ $this->settings->sumopodAvailable() ? 'Aktif' : ($this->settings->sumopod_enabled ? 'Aktif, tapi API key belum diatur' : 'Nonaktif') }}
                </x-admin.setting-row>
                <x-admin.setting-row label="Mode">{{ $this->settings->sumopod_mode === 'live' ? 'Live (transaksi sungguhan)' : 'Sandbox (uji coba)' }}</x-admin.setting-row>
                <x-admin.setting-row label="API key" :ok="filled($this->settings->sumopod_api_key)">
                    {{ filled($this->settings->sumopod_api_key) ? '✓ Sudah diatur '.$this::masked($this->settings->sumopod_api_key) : 'Belum diatur' }}
                </x-admin.setting-row>
                <x-admin.setting-row label="Webhook token" :ok="filled($this->settings->sumopod_webhook_token)">
                    {{ filled($this->settings->sumopod_webhook_token) ? '✓ Sudah diatur '.$this::masked($this->settings->sumopod_webhook_token) : 'Belum diatur' }}
                </x-admin.setting-row>
            </div>
        </x-slot:summary>

        <x-admin.switch wire:model="sumopod_enabled" label="Tampilkan bayar otomatis ke pembeli" description="Aktifkan setelah KYC Sumopod disetujui dan API key diisi." />
        <div class="grid grid-cols-2 gap-2">
            <x-admin.tile :active="$sumopod_mode === 'sandbox'" icon="beaker" sub="Uji coba, uang tidak sungguhan" wire:click="$set('sumopod_mode', 'sandbox')">Sandbox</x-admin.tile>
            <x-admin.tile :active="$sumopod_mode === 'live'" icon="banknotes" sub="Transaksi sungguhan" wire:click="$set('sumopod_mode', 'live')">Live</x-admin.tile>
        </div>
        <x-admin.field label="API key baru" name="sumopod_api_key" :hint="filled($this->settings->sumopod_api_key) ? 'Kosongkan untuk tetap memakai key yang sekarang ('.$this::masked($this->settings->sumopod_api_key).').' : null">
            <input type="password" wire:model="sumopod_api_key" autocomplete="off" class="admin-input">
        </x-admin.field>
        <x-admin.field label="Webhook token baru" name="sumopod_webhook_token" :hint="filled($this->settings->sumopod_webhook_token) ? 'Kosongkan untuk tetap memakai token yang sekarang ('.$this::masked($this->settings->sumopod_webhook_token).').' : null">
            <input type="password" wire:model="sumopod_webhook_token" autocomplete="off" class="admin-input">
        </x-admin.field>
    </x-admin.setting-card>

    {{-- URL webhook selalu tampil (bukan setelan yang bisa diubah, cuma untuk disalin ke dashboard Sumopod). --}}
    <div class="-mt-3 space-y-1.5 rounded-b-2xl px-1" x-data="{ copied: false }">
        <p class="text-xs text-zinc-500">URL webhook untuk dashboard Sumopod (Live & Sandbox):</p>
        <div class="flex gap-2">
            <input type="text" readonly value="{{ \App\Support\PublicUrl::route('webhooks.sumopod') }}" class="admin-input py-2 font-mono text-xs">
            <button type="button" class="shrink-0 rounded-lg border border-navy-700 px-3 text-zinc-300 hover:bg-navy-800" aria-label="Salin"
                    @click="navigator.clipboard.writeText('{{ \App\Support\PublicUrl::route('webhooks.sumopod') }}'); copied = true; setTimeout(() => copied = false, 2000)">
                <flux:icon x-show="! copied" name="clipboard-document" class="size-5" />
                <flux:icon x-show="copied" x-cloak name="check" class="size-5 text-emerald-400" />
            </button>
        </div>
    </div>

    <x-admin.setting-card icon="photo" title="Google Drive (dokumentasi match)" section="drive" :editing="$editing === 'drive'" save="saveDrive">
        <x-slot:summary>
            <x-admin.setting-row label="API key" :ok="filled($this->settings->googleDriveApiKey())">
                {{ filled($this->settings->googleDriveApiKey()) ? '✓ Sudah diatur '.$this::masked($this->settings->googleDriveApiKey()) : 'Belum diatur -- galeri foto belum aktif' }}
            </x-admin.setting-row>
        </x-slot:summary>

        <p class="text-xs text-zinc-400">
            Buat di <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" class="text-orange-400 underline">Google Cloud Console</a>
            memakai akun Community League: aktifkan "Google Drive API", lalu Create credentials &rarr; API key (batasi ke Google Drive API).
        </p>
        <x-admin.field label="API key baru" name="google_drive_api_key">
            <input type="password" wire:model="google_drive_api_key" autocomplete="off" class="admin-input" placeholder="AIza...">
        </x-admin.field>
    </x-admin.setting-card>

    <x-admin.setting-card icon="chat-bubble-left-right" title="WhatsApp admin" section="whatsapp" :editing="$editing === 'whatsapp'" save="saveWhatsapp"
                          description="Tujuan tombol 'Mengalami kendala? Hubungi admin' di web dan email.">
        <x-slot:summary>
            <x-admin.setting-row label="Nomor">{{ WhatsApp::local($this->settings->admin_whatsapp) }}</x-admin.setting-row>
        </x-slot:summary>

        <x-admin.field label="Nomor baru" name="admin_whatsapp">
            <input type="tel" wire:model="admin_whatsapp" inputmode="tel" class="admin-input">
        </x-admin.field>
    </x-admin.setting-card>
</div>
