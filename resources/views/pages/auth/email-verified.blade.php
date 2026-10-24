<x-layouts::auth :title="__('Email terverifikasi')">
    <div class="flex flex-col items-center gap-6 text-center">
        <div class="flex size-14 items-center justify-center rounded-full bg-red-100 dark:bg-orange-950">
            <flux:icon name="check-circle" class="size-8 text-red-600 dark:text-orange-500" />
        </div>

        <x-auth-header :title="__('Akun Anda berhasil diverifikasi')" :description="__('Terima kasih, email Anda sudah terverifikasi. Sekarang Anda bisa mulai booking match.')" />

        <flux:button :href="route('home')" variant="primary" class="w-full" wire:navigate>
            {{ __('Mulai cari match untukmu') }}
        </flux:button>
    </div>
</x-layouts::auth>
