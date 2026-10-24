<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Page Layout
    |---------------------------------------------------------------------------
    | Default Livewire package value is 'layouts::app' (resources/views/layouts/
    | app.blade.php) -- sisa starter kit Laravel: sidebar desktop, dark mode
    | dipaksa, link GitHub/Docs starter kit. Halaman publik app ini (Home,
    | Match, Akun, dst) semua pakai layouts/public.blade.php (bottom-nav,
    | mobile-header, mobile-first). Override ke situ supaya halaman settings
    | (Profile/Security/Appearance, satu-satunya pemakai Route::livewire() di
    | app ini) konsisten dengan sisa app, bukan tampilan starter kit lama.
    */

    'component_layout' => 'layouts::public',

];
