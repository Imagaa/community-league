<x-layouts::auth.simple :title="$title ?? null" :show-back="$showBack ?? true" :top-align="$topAlign ?? false" :logo-landscape="$logoLandscape ?? false">
    {{ $slot }}
</x-layouts::auth.simple>
