<?php

use App\Models\GameMatch;
use App\Models\MatchReminder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public GameMatch $match;

    public bool $reminderSet = false;

    public function mount(GameMatch $match): void
    {
        $this->match = $match;
        $this->refreshReminderState();
    }

    public function toggleReminder(): void
    {
        abort_unless(Auth::check(), 403);

        if ($this->reminderSet) {
            MatchReminder::where('user_id', Auth::id())->where('match_id', $this->match->id)->delete();
        } else {
            MatchReminder::firstOrCreate(['user_id' => Auth::id(), 'match_id' => $this->match->id]);
        }

        $this->refreshReminderState();
    }

    private function refreshReminderState(): void
    {
        $this->reminderSet = Auth::check()
            && MatchReminder::where('user_id', Auth::id())->where('match_id', $this->match->id)->exists();
    }
}; ?>

<div>
    @auth
        <button
            type="button"
            wire:click="toggleReminder"
            class="mt-2 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $reminderSet ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400' : 'border-zinc-300 text-zinc-700 hover:bg-zinc-50 dark:border-navy-700 dark:text-zinc-300 dark:hover:bg-navy-800' }}"
        >
            <flux:icon name="{{ $reminderSet ? 'bell-alert' : 'bell' }}" class="size-3.5" />
            {{ $reminderSet ? __('Pengingat Aktif') : __('Ingatkan Saya') }}
        </button>
    @else
        <a href="{{ route('login') }}" wire:navigate class="mt-2 inline-block text-xs font-semibold text-red-600 underline dark:text-orange-400">
            {{ __('Login untuk dapat pengingat') }}
        </a>
    @endauth
</div>
