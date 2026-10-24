@props(['placeholder' => '0'])

{{--
    Isian rupiah: awalan "Rp" + pemisah ribuan (75.000) saat mengetik. Yang dikirim ke Livewire
    tetap angka bulat (75000), jadi validasi 'integer' di komponen tidak berubah.
    Pakai: <x-admin.money-input wire:model="price" placeholder="75.000" />
--}}
<div class="relative"
     x-data="{
         raw: @entangle($attributes->wire('model')),
         get shown() {
             return this.raw === null || this.raw === '' ? '' : Number(this.raw).toLocaleString('id-ID');
         },
         set shown(value) {
             const digits = String(value).replace(/\D/g, '').replace(/^0+(?=\d)/, '');
             this.raw = digits === '' ? null : parseInt(digits, 10);
             // Paksa tampilan ikut terformat walau angka yang tersimpan tidak berubah (mis. ketik huruf).
             this.$refs.input.value = this.shown;
         },
     }">
    <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 font-semibold text-zinc-500">Rp</span>
    <input type="text" inputmode="numeric" autocomplete="off" x-ref="input" x-model="shown"
           placeholder="{{ $placeholder }}" {{ $attributes->whereDoesntStartWith('wire:model')->class('admin-input pl-11') }}>
</div>
