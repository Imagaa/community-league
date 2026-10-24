<div
    x-data="{
        installable: false,
        isIos: /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
        showIosModal: false,
        init() {
            window.addEventListener('pwa-installable', () => { this.installable = true; });
        },
        install() {
            if (this.isIos) { this.showIosModal = true; return; }
            if (!window.deferredInstallPrompt) { return; }
            window.deferredInstallPrompt.prompt();
            window.deferredInstallPrompt = null;
            this.installable = false;
        },
    }"
    x-show="installable || isIos"
>
    <button
        type="button"
        x-on:click="install"
        class="w-full rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-navy-700 dark:text-zinc-300 dark:hover:bg-navy-800"
    >
        {{ __('Tambahkan Pintasan ke Layar Utama') }}
    </button>

    <div
        x-show="showIosModal"
        x-on:click.outside="showIosModal = false"
        x-cloak
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
    >
        <div class="w-full max-w-sm rounded-2xl bg-white p-5 dark:bg-navy-900">
            <p class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Tambahkan ke Layar Utama') }}</p>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Ketuk ikon Share di Safari, lalu pilih "Add to Home Screen".') }}
            </p>
            <button type="button" x-on:click="showIosModal = false" class="mt-4 w-full rounded-lg bg-zinc-100 px-4 py-2 text-sm font-semibold text-zinc-700 dark:bg-navy-800 dark:text-zinc-300">
                {{ __('Tutup') }}
            </button>
        </div>
    </div>
</div>
