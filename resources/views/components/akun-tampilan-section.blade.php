{{-- Dipakai di akun-guest.blade.php (tamu) dan akun/⚡notifikasi.blade.php (login) -- $flux.appearance
     murni client-side (Alpine + localStorage), tidak butuh akun sama sekali. Disembunyikan di
     md: ke atas -- toggle yang sama sudah dipindah ke <x-top-navbar> khusus desktop, jadi versi
     di sini cuma perlu tampil di mobile (yang tidak punya header dengan toggle). --}}
<section class="rounded-2xl border border-zinc-200 bg-white p-4 md:hidden dark:border-navy-800 dark:bg-navy-900">
    <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Tampilan') }}</h2>
    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
        <flux:radio value="light" icon="sun">{{ __('Terang') }}</flux:radio>
        <flux:radio value="dark" icon="moon">{{ __('Gelap') }}</flux:radio>
        <flux:radio value="system" icon="computer-desktop">{{ __('Sistem') }}</flux:radio>
    </flux:radio.group>
</section>
