<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
    }

    public function test_login_page_shows_demo_account_and_hides_register(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('デモ用アカウントでログイン')
            ->assertSee('demo@example.com')
            ->assertDontSee('無料で登録');
    }

    public function test_register_and_password_reset_are_closed(): void
    {
        $this->get('/register')->assertRedirect('/login');
        $this->post('/register', [
            'name' => 'x', 'email' => 'x@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect('/login');
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
        $this->get('/forgot-password')->assertRedirect('/login');
    }

    public function test_demo_user_cannot_change_email_or_password(): void
    {
        $user = User::create(['name' => 'デモ教室', 'email' => 'demo@example.com', 'password' => 'password']);

        $this->actingAs($user)->put('/settings', [
            'name' => '名前は変えられる',
            'email' => 'hacked@example.com',
            'current_password' => 'password',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertRedirect('/settings');

        $user->refresh();
        $this->assertSame('名前は変えられる', $user->name);
        $this->assertSame('demo@example.com', $user->email);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_banner_is_shown_after_login(): void
    {
        $user = User::create(['name' => 'デモ教室', 'email' => 'demo@example.com', 'password' => 'password']);
        $this->actingAs($user)->get('/')->assertOk()->assertSee('デモ版です');
    }
}
