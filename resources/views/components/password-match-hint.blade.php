@props(['for' => 'password', 'confirm' => 'password_confirmation'])

{{-- Pola sama seperti password-strength-meter.blade.php: Alpine x-data + init() yang cari input
     lewat closest('form'), bukan <script> mentah -- supaya jalan konsisten lewat wire:navigate. --}}
<div
    x-data="{
        value: '',
        confirmValue: '',
        confirmTouched: false,
        get matches() { return this.value === this.confirmValue },
        init() {
            const form = this.$el.closest('form');
            const input = form?.querySelector('input[name=&quot;{{ $for }}&quot;]');
            const confirmInput = form?.querySelector('input[name=&quot;{{ $confirm }}&quot;]');
            if (! input || ! confirmInput) return;
            this.value = input.value;
            this.confirmValue = confirmInput.value;
            input.addEventListener('input', () => { this.value = input.value; });
            confirmInput.addEventListener('input', () => {
                this.confirmValue = confirmInput.value;
                this.confirmTouched = true;
            });
        },
    }"
    x-show="confirmTouched && confirmValue.length > 0"
    x-cloak
    class="flex items-center gap-1.5 text-xs"
    :class="matches ? 'text-red-600 dark:text-orange-400' : 'text-red-500 dark:text-red-400'"
>
    <flux:icon x-show="matches" name="check-circle" class="size-4" />
    <flux:icon x-show="!matches" name="x-circle" class="size-4" />
    <span x-text="matches ? 'Password cocok' : 'Password tidak cocok'"></span>
</div>
