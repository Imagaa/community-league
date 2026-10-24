@props(['for' => 'password'])

{{--
    Alpine (x-data), BUKAN <script> mentah + document.querySelector -- pola yang sama sudah
    terbukti jalan benar lewat wire:navigate di passkey-verify.blade.php (komponen ini sendiri
    sebelumnya pakai <script> mentah yang TIDAK dijamin re-run setelah navigasi wire:navigate,
    kemungkinan besar itu sebab checklist "tanpa keterangan" yang dilaporkan user).
--}}
<div
    x-data="{
        value: '',
        get hasLength() { return this.value.length >= 8 },
        get hasMixedCase() { return /[a-z]/.test(this.value) && /[A-Z]/.test(this.value) },
        get hasNumber() { return /[0-9]/.test(this.value) },
        get hasSymbol() { return /[^A-Za-z0-9]/.test(this.value) },
        init() {
            const input = this.$el.closest('form')?.querySelector('input[name=&quot;{{ $for }}&quot;]');
            if (! input) return;
            this.value = input.value;
            input.addEventListener('input', () => { this.value = input.value; });
        },
    }"
    x-show="value.length > 0"
    x-cloak
    class="flex flex-col gap-1 text-xs"
>
    <div class="flex items-center gap-1.5" :class="hasLength ? 'text-red-600 dark:text-orange-400' : 'text-zinc-400 dark:text-zinc-500'">
        <flux:icon x-show="hasLength" name="check-circle" class="size-4" />
        <flux:icon x-show="!hasLength" name="x-circle" class="size-4" />
        <span>Minimal 8 karakter</span>
    </div>
    <div class="flex items-center gap-1.5" :class="hasMixedCase ? 'text-red-600 dark:text-orange-400' : 'text-zinc-400 dark:text-zinc-500'">
        <flux:icon x-show="hasMixedCase" name="check-circle" class="size-4" />
        <flux:icon x-show="!hasMixedCase" name="x-circle" class="size-4" />
        <span>Huruf besar dan huruf kecil</span>
    </div>
    <div class="flex items-center gap-1.5" :class="hasNumber ? 'text-red-600 dark:text-orange-400' : 'text-zinc-400 dark:text-zinc-500'">
        <flux:icon x-show="hasNumber" name="check-circle" class="size-4" />
        <flux:icon x-show="!hasNumber" name="x-circle" class="size-4" />
        <span>Mengandung angka</span>
    </div>
    <div class="flex items-center gap-1.5" :class="hasSymbol ? 'text-red-600 dark:text-orange-400' : 'text-zinc-400 dark:text-zinc-500'">
        <flux:icon x-show="hasSymbol" name="check-circle" class="size-4" />
        <flux:icon x-show="!hasSymbol" name="x-circle" class="size-4" />
        <span>Mengandung simbol (!@#$%^&* dst)</span>
    </div>
</div>
