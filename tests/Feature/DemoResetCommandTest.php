<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * demo:reset は中で migrate:fresh を動かすため、
 * テストをトランザクションで包む RefreshDatabase ではなく DatabaseMigrations を使う
 */
class DemoResetCommandTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
    }

    public function test_reset_command_seeds_demo_data(): void
    {
        $this->artisan('demo:reset --if-empty')->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'demo@example.com']);

        // もう入っていれば、--if-empty では何もしない
        User::where('email', 'demo@example.com')->update(['name' => '変更済み']);
        $this->artisan('demo:reset --if-empty')->assertSuccessful();
        $this->assertDatabaseHas('users', ['name' => '変更済み']);
    }

    public function test_reset_command_does_nothing_outside_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        $user = User::create(['name' => '本番の教室', 'email' => 'real@example.com', 'password' => 'password']);

        $this->artisan('demo:reset')->assertSuccessful();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
