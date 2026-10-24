@auth
    @if (auth()->user()->membership?->isActive())
        <img
            src="{{ asset('images/verified-badge.webp') }}"
            alt="{{ __('Member Community League') }}"
            title="{{ __('Member Community League') }}"
            {{ $attributes->merge(['class' => 'inline-block shrink-0 align-middle object-contain']) }}
        />
    @endif
@endauth
