<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $this->get('/register')->assertStatus(404);
    }

    public function test_registration_endpoint_is_disabled(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(404);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_login_page_has_no_registration_link(): void
    {
        $this->seedRoles();

        $response = $this->get('/login');

        $response->assertStatus(200);
        $this->assertStringNotContainsString('route(\'register\')', $response->getContent());
        $this->assertStringNotContainsString('Daftar', $response->getContent());
    }

    private function seedRoles(): void
    {
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }
}
