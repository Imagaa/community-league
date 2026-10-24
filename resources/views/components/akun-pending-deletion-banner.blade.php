@auth
    @if (auth()->user()->scheduled_deletion_at)
        <div class="mb-3 flex flex-col gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 sm:flex-row sm:items-center sm:justify-between dark:border-red-900 dark:bg-red-950 dark:text-red-300">
            <span>
                {{ __('Akun ini terjadwal dihapus pada :date.', ['date' => auth()->user()->scheduled_deletion_at->timezone(config('app.display_timezone'))->translatedFormat('d M Y')]) }}
            </span>
            <form method="POST" action="{{ route('akun.batalkan-hapus') }}" class="shrink-0">
                @csrf
                <button type="submit" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white">
                    {{ __('Batalkan Penghapusan') }}
                </button>
            </form>
        </div>
    @endif
@endauth
