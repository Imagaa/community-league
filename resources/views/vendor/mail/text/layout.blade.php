@if (config('services.sandbox_gate.enabled'))
=== {{ __('MODE SANDBOX — DATA TIDAK NYATA, BUKAN PRODUKSI') }} ===

@endif
{!! strip_tags($header ?? '') !!}

{!! strip_tags($slot) !!}
@isset($subcopy)

{!! strip_tags($subcopy) !!}
@endisset

{!! strip_tags($footer ?? '') !!}
