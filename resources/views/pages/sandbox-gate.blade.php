<x-layouts::auth :title="__('Akses Staging')" :show-back="false">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Akses Staging')" :description="__('Masukkan email dan kode akses yang diberikan admin untuk melanjutkan.')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('sandbox-gate.verify') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="email"
                :label="__('Email')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <flux:input
                name="pin"
                :label="__('Kode akses (6 digit)')"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                required
                autocomplete="one-time-code"
                placeholder="123456"
            />

            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Masuk') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
