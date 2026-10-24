@if (config('services.sandbox_gate.enabled'))
    {{-- Pita tambahan di kartu-kartu yang berpotensi jadi "bukti" screenshot terpisah dari
         halaman (tiket di-download lewat html2canvas -- lihat bookings/show.blade.php --
         yang cuma nangkap isi #ticket-card, tidak ikut nangkap <x-sandbox-banner> di body).
         Parent WAJIB `relative overflow-hidden` supaya pita diagonal ini ke-clip rapi di
         sudut kartu, bukan menjorok keluar. --}}
    <div class="pointer-events-none absolute -right-10 top-4 z-20 w-40 rotate-45 bg-red-600 py-1 text-center text-[10px] font-bold uppercase tracking-wider text-white shadow-sm dark:bg-orange-600">
        {{ __('Sandbox') }}
    </div>
@endif
