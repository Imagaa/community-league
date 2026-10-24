<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertOk();
            $response->assertSee('Password cocok');
            $response->assertSee('Password tidak cocok');

            return true;
        });
    }

    public function test_reset_password_screen_does_not_error_when_email_is_an_array(): void
    {
        $response = $this->get(route('password.reset', 'faketoken').'?email[]=x');

        $response->assertOk();
    }

    public function test_forgot_password_response_identik_untuk_email_terdaftar_dan_tidak(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $responseTerdaftar = $this->post(route('password.request'), ['email' => $user->email]);
        $responseTidakTerdaftar = $this->post(route('password.request'), ['email' => 'tidak-ada@example.com']);

        $responseTerdaftar->assertSessionHasNoErrors();
        $responseTidakTerdaftar->assertSessionHasNoErrors();

        $responseTerdaftar->assertSessionHas('status');
        $responseTidakTerdaftar->assertSessionHas('status');

        $this->assertSame(
            $responseTerdaftar->getSession()->get('status'),
            $responseTidakTerdaftar->getSession()->get('status'),
        );
    }

    public function test_forgot_password_hanya_kirim_email_untuk_yang_benar_benar_terdaftar(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);
        $this->post(route('password.request'), ['email' => 'tidak-ada@example.com']);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Rahasia-Panjang-Baru123!',
                'password_confirmation' => 'Rahasia-Panjang-Baru123!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login', absolute: false));

            return true;
        });
    }
}
