@if (config('services.sandbox_gate.enabled'))
    {{-- Satu penanda visual dipakai di semua layout (public/auth/legal) + panel admin -- kalau
         nanti butuh diperbarui, cukup di sini. Sticky (bukan fixed) supaya tidak perlu hitung
         offset manual untuk konten di bawahnya, cukup geser top-navbar desktop (lihat komentar
         di top-navbar.blade.php) yang juga sticky top-0. Tinggi dikunci h-8 -- top-navbar
         bergantung pada angka ini untuk offset-nya, ubah bareng kalau tinggi banner ini diubah. --}}
    <div class="sticky top-0 z-50 flex h-8 items-center justify-center gap-1.5 bg-amber-400 px-3 text-center text-[11px] font-bold uppercase tracking-wide text-amber-950 dark:bg-amber-500">
        <flux:icon name="exclamation-triangle" class="size-3.5 shrink-0" />
        {{ __('Mode Sandbox — Data Tidak Nyata, Bukan Produksi') }}
    </div>
@endif
