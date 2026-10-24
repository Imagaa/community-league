@props(['match', 'buttonLabel' => null])

@php
    $status = $match->computedStatus();
    $remaining = $match->remainingSlots();
    $full = $match->isFull();
    $isFootball = $match->type === 'football';

    $statusBadge = match (true) {
        $status === 'finished' => ['label' => __('Selesai'), 'class' => 'bg-slate-500 text-white'],
        $status === 'ongoing' => ['label' => __('Berlangsung'), 'class' => 'bg-amber-500 text-white'],
        $status === 'published' => ['label' => __('Segera Dibuka'), 'class' => 'bg-blue-600 text-white'],
        $status === 'open' && $full => ['label' => __('Penuh'), 'class' => 'bg-red-600 text-white dark:bg-orange-600'],
        default => null,
    };
@endphp

<li class="flex h-full flex-col gap-2 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm lg:gap-2.5 lg:p-6 dark:border-navy-800 dark:bg-navy-900 {{ $status === 'finished' ? 'opacity-60' : '' }}">
    <div class="flex items-start justify-between gap-2">
        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase text-white {{ $isFootball ? 'bg-zinc-900 dark:bg-white/10' : 'bg-teal-600 dark:bg-teal-500' }}">
            {{ $isFootball ? __('Sepak Bola') : __('Mini Soccer') }}
        </span>
        <span class="text-[11px] text-zinc-500 dark:text-zinc-400">
            {{ $match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('D, d M Y - H:i') }} WIB
        </span>
    </div>

    <a href="{{ route('match.show', $match) }}" class="block font-semibold text-zinc-900 dark:text-white" wire:navigate>
        {{ $match->title }}
    </a>
    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $match->venue->name }}, {{ $match->venue->city->name }}</p>

    @if ($status === 'open' && ! $full)
        <p class="text-[11px] {{ $remaining <= 3 ? 'font-semibold text-red-600 dark:text-orange-400' : 'text-zinc-500 dark:text-zinc-400' }}">
            {{ $remaining <= 3 ? __(':n slot tersisa! Buruan', ['n' => $remaining]) : __(':n slot tersisa', ['n' => $remaining]) }}
        </p>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        <span class="font-bold text-red-600 dark:text-orange-400">{{ \App\Support\Money::rupiah($match->price_regular) }}</span>
        @if ($match->points_reward > 0 && \App\Models\MembershipSettings::enabled())
            <span class="rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-bold text-zinc-900">
                +{{ $match->points_reward }} Pts
            </span>
        @endif
        @if ($statusBadge)
            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusBadge['class'] }}">
                {{ $statusBadge['label'] }}
            </span>
        @endif
        @if ($status === 'published' && $match->hasEarlyMemberWindow())
            <span class="inline-flex items-center gap-1 rounded-full bg-purple-600 px-2 py-0.5 text-[10px] font-bold text-white dark:bg-purple-500">
                <flux:icon name="star" class="size-2.5" />
                {{ __('Member Akses Awal') }}
            </span>
        @endif
    </div>

    <a href="{{ route('match.show', $match) }}" wire:navigate
       class="mt-auto block rounded-lg bg-red-600 py-2 text-center text-sm font-semibold text-white hover:bg-red-700 lg:py-2.5 dark:bg-orange-600 dark:hover:bg-orange-700">
        {{ $buttonLabel ?? __('Lihat Detail') }}
    </a>
</li>
