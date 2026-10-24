@php
    $tabs = [
        ['route' => 'home',       'label' => 'Home',       'icon' => 'home'],
        ['route' => 'match',      'label' => 'Match',      'icon' => 'trophy'],
        ['route' => 'membership', 'label' => 'Membership', 'icon' => 'identification'],
        ['route' => 'feed',       'label' => 'Feed',       'icon' => 'squares-2x2'],
        ['route' => 'akun',       'label' => 'Akun',       'icon' => 'user'],
    ];
@endphp

<nav class="fixed inset-x-4 bottom-4 z-40 rounded-full bg-red-600 shadow-lg shadow-black/20 md:hidden dark:bg-navy-900">
    <ul class="mx-auto flex max-w-lg items-stretch justify-around px-1 py-1.5">
        @foreach ($tabs as $tab)
            <li class="flex-1">
                <a href="{{ Route::has($tab['route']) ? route($tab['route']) : '#' }}"
                   class="flex flex-col items-center gap-0.5 rounded-full py-2 text-[10px] {{ request()->routeIs($tab['route']) ? 'bg-white/15 text-white' : 'text-red-100 dark:text-white/60' }}"
                   @if (request()->routeIs($tab['route'])) aria-current="page" @endif>
                    <flux:icon :name="$tab['icon']" class="size-5" />
                    <span>{{ $tab['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
