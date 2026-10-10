<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_cashier_can_login_with_provided_credentials(): void
    {
        $response = $this->postJson('/login', [
            'username' => 'cashier@admin',
            'password' => 'behemoth',
            'expected_role' => 'cashier',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'redirect' => route('pos.index'),
            ]);

        $this->assertAuthenticated();
    }

    public function test_kitchen_chef_can_login_with_provided_credentials(): void
    {
        $response = $this->postJson('/login', [
            'username' => 'chef@admin',
            'password' => 'behemoth',
            'expected_role' => 'kitchen',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'redirect' => route('kds.index'),
            ]);

        $this->assertAuthenticated();
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/login', [
            'username' => 'cashier@admin',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertGuest();
    }

    public function test_cashier_rejected_when_accessing_kitchen_portal_with_expected_role(): void
    {
        $response = $this->postJson('/login', [
            'username' => 'cashier@admin',
            'password' => 'behemoth',
            'expected_role' => 'kitchen',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_retrieve_profile_and_roles(): void
    {
        $user = User::where('username', 'cashier@admin')->first();

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'authenticated' => true,
                'user' => [
                    'username' => 'cashier@admin',
                ],
            ]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::where('username', 'cashier@admin')->first();

        $response = $this->actingAs($user)->postJson('/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertGuest();
    }
}
