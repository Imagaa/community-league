<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_tidak_melihat_lonceng_notifikasi(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-test="notification-badge"', false);
    }

    public function test_badge_tidak_muncul_kalau_tidak_ada_notifikasi(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::notifications.bell')
            ->assertSee('Belum ada notifikasi.')
            ->assertDontSee('data-test="notification-badge"', false);
    }

    public function test_badge_menampilkan_jumlah_notifikasi_belum_dibaca(): void
    {
        $user = User::factory()->create();
        $this->putNotification($user, 'Judul A', 'Isi A');
        $this->putNotification($user, 'Judul B', 'Isi B');

        Livewire::actingAs($user)
            ->test('pages::notifications.bell')
            ->assertSet('unreadCount', 2)
            ->assertSee('Judul A')
            ->assertSee('Judul B');
    }

    public function test_recent_dibatasi_10_terbaru(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 12) as $i) {
            $this->putNotification($user, "Judul {$i}", "Isi {$i}");
        }

        Livewire::actingAs($user)
            ->test('pages::notifications.bell')
            ->assertCount('recent', 10);
    }

    public function test_markasread_menandai_dibaca_dan_mengurangi_unread_count(): void
    {
        $user = User::factory()->create();
        $notification = $this->putNotification($user, 'Judul', 'Isi');

        Livewire::actingAs($user)
            ->test('pages::notifications.bell')
            ->call('markAsRead', $notification->id)
            ->assertSet('unreadCount', 0);

        expect($notification->fresh()->read_at)->not->toBeNull();
    }

    public function test_markasread_navigasi_ke_url_kalau_ada(): void
    {
        $user = User::factory()->create();
        $notification = $this->putNotification($user, 'Judul', 'Isi', url: '/match');

        Livewire::actingAs($user)
            ->test('pages::notifications.bell')
            ->call('markAsRead', $notification->id)
            ->assertRedirect('/match');
    }

    public function test_user_tidak_bisa_menandai_notifikasi_milik_user_lain(): void
    {
        $pemilik = User::factory()->create();
        $penyerang = User::factory()->create();
        $notification = $this->putNotification($pemilik, 'Judul', 'Isi');

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($penyerang)
            ->test('pages::notifications.bell')
            ->call('markAsRead', $notification->id);
    }

    public function test_markallasread_menandai_semua_dibaca_tanpa_menyentuh_user_lain(): void
    {
        $user = User::factory()->create();
        $lainnya = User::factory()->create();
        $this->putNotification($user, 'A', 'A');
        $this->putNotification($user, 'B', 'B');
        $milikLainnya = $this->putNotification($lainnya, 'C', 'C');

        Livewire::actingAs($user)
            ->test('pages::notifications.bell')
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);

        expect($milikLainnya->fresh()->read_at)->toBeNull();
    }

    private function putNotification(User $user, string $title, string $body, ?string $url = null): DatabaseNotification
    {
        $user->notify(new class($title, $body, $url) extends Notification
        {
            public function __construct(
                private readonly string $title,
                private readonly string $body,
                private readonly ?string $url,
            ) {}

            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toDatabase(object $notifiable): array
            {
                return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
            }
        });

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->latest()->firstOrFail();

        return $notification;
    }
}
