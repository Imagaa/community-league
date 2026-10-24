<?php

namespace App\Support;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

/**
 * Di domain admin root URL sengaja TIDAK dipaksa ke domain publik (endpoint Livewire harus tetap
 * di host admin, lihat AppServiceProvider). Akibatnya route() untuk halaman publik yang dipanggil
 * dari request admin -- link e-ticket di email saat admin menyetujui, link "lihat di web" --
 * akan ikut ber-host admin. scope() memaksa root publik sementara untuk blok itu saja.
 */
class PublicUrl
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function scope(callable $callback): mixed
    {
        // Di domain publik root sudah dipaksa global -- jangan dilepas di finally.
        if (AppServiceProvider::shouldForceRootUrl()) {
            return $callback();
        }

        URL::forceRootUrl(config('app.url'));
        URL::forceScheme((string) parse_url((string) config('app.url'), PHP_URL_SCHEME));

        try {
            return $callback();
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }
    }

    /** @param  mixed  $parameters */
    public static function route(string $name, $parameters = []): string
    {
        return self::scope(fn () => route($name, $parameters));
    }
}
