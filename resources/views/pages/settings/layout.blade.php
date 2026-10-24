<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        {{--
            Navlist ini sekarang kosong -- Profil, Keamanan, dan Tampilan semua sudah pindah ke
            /akun/*. Satu-satunya
            pemakai layout ini yang tersisa adalah settings/⚡security.blade.php, sengaja
            dipertahankan orphan (tidak dirujuk route manapun) karena masih berisi kode
            2FA/Passkeys yang di-unlink, bukan dihapus, dari spec.
        --}}
        <flux:navlist aria-label="{{ __('Pengaturan') }}"></flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
