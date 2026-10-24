<div
    id="cookie-consent"
    class="fixed inset-x-0 bottom-0 z-50 hidden border-t border-zinc-200 bg-white p-4 text-sm text-zinc-700 shadow-lg dark:border-navy-800 dark:bg-navy-900 dark:text-zinc-300"
>
    <div class="mx-auto flex max-w-3xl flex-col items-center justify-between gap-3 sm:flex-row">
        <p>
            {{ __('Kami menggunakan cookie yang diperlukan agar situs ini berfungsi. Dengan melanjutkan, Anda menyetujui') }}
            <a href="{{ route('legal.privacy') }}" class="underline">{{ __('Kebijakan Privasi') }}</a> {{ __('kami.') }}
        </p>
        <button
            type="button"
            onclick="document.cookie='cookie_consent=accepted;max-age=31536000;path=/;samesite=lax';document.getElementById('cookie-consent').classList.add('hidden')"
            class="shrink-0 rounded-lg bg-zinc-900 px-4 py-2 font-medium text-white dark:bg-orange-600"
        >
            {{ __('Terima Semua') }}
        </button>
    </div>
</div>

<script>
    if (! document.cookie.includes('cookie_consent=')) {
        document.getElementById('cookie-consent')?.classList.remove('hidden');
    }
</script>
