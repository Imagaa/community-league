<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use NotificationChannels\WebPush\Events\NotificationFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->logFailedWebPushDeliveries();
        $this->markSandboxMail();
    }

    /**
     * Satu listener nutup SEMUA mail keluar (notifikasi + bawaan framework) tanpa ubah satu pun
     * class Notification -- prefix subjek + header kasat-server, watermark yang KELIHATAN di isi
     * email diatur terpisah lewat vendor/mail (lihat resources/views/vendor/mail/html/header.blade.php).
     * Nonaktif otomatis di produksi/lokal/CI, lihat komentar di config('services.sandbox_gate').
     */
    protected function markSandboxMail(): void
    {
        if (! config('services.sandbox_gate.enabled')) {
            return;
        }

        Event::listen(function (MessageSending $event): void {
            $event->message->getHeaders()->addTextHeader('X-Sandbox', 'true');

            if (! str_starts_with((string) $event->message->getSubject(), '[SANDBOX]')) {
                $event->message->subject('[SANDBOX] '.$event->message->getSubject());
            }
        });
    }

    /**
     * WebPushChannel diam-diam nelan kegagalan kirim per-subscription (tidak throw, cuma fire
     * event) -- tanpa listener ini kita tidak pernah tahu kenapa push gagal sampai ke HP
     * (expired subscription, VAPID salah, dst). Lihat memori sesi soal debugging push notification.
     */
    protected function logFailedWebPushDeliveries(): void
    {
        Event::listen(function (NotificationFailed $event): void {
            Log::warning('Web push gagal terkirim', [
                'endpoint' => $event->report->getEndpoint(),
                'reason' => $event->report->getReason(),
                'status_code' => $event->report->getResponse()?->getStatusCode(),
                'subscription_expired' => $event->report->isSubscriptionExpired(),
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Panel admin dibind ke domain sendiri (ADMIN_DOMAIN, lihat AdminPanelProvider::domain()).
        // Tanpa force root ini, route()/url() yang dipanggil DARI request admin (mis. aksi
        // Filament yang kirim notifikasi ke user) generate URL absolut pakai host request SAAT
        // ITU (admin.communityleague.id), bukan domain publik -- link di notifikasi jadi salah
        // domain sama sekali (bukan cuma salah halaman).
        //
        // TAPI: force ini HANYA boleh jalan waktu request SEDANG di domain publik. Kalau request
        // sedang di domain admin sendiri, forceRootUrl tetap menimpa endpoint internal Livewire
        // (/livewire-xxx/update dst) yang TIDAK didaftarkan dengan ->domain() eksplisit (beda
        // dengan route resource Filament yang memang domain-scoped) -- akibatnya endpoint itu
        // ikut dipaksa ke domain publik juga, request AJAX-nya jadi cross-origin dan gagal diam-
        // diam. Efeknya: form apa pun di panel admin (termasuk halaman login) terlihat tidak
        // melakukan apa-apa saat disubmit. Baru ketahuan waktu re-login gagal total.
        if (self::shouldForceRootUrl()) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme((string) parse_url((string) config('app.url'), PHP_URL_SCHEME));
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Diekstrak jadi method terpisah (bukan inline di configureDefaults) supaya bisa dites
     * langsung -- Laravel test harness cuma boot Application (jadi cuma jalanin boot() provider
     * ini) SEKALI per test method SEBELUM $this->get(...) dipanggil, jadi request() yang
     * terikat saat boot() asli jalan bukan request tujuan test. Extract ini memisahkan LOGIKA
     * keputusannya dari efek sampingnya supaya tetap bisa diuji tanpa terganjal itu.
     */
    public static function shouldForceRootUrl(): bool
    {
        return request()->getHost() !== config('app.admin_domain');
    }
}
