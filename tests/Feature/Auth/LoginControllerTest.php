<?php

namespace Tests\Feature\Auth;

use App\Models\Rol;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_login_returns_token_and_role_for_enabled_user(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'rol_id' => Rol::where('name', Rol::ADMIN)->value('id'),
            'state_id' => State::where('name', State::ENABLED)->value('id'),
        ]);

        JWTAuth::shouldReceive('attempt')
            ->twice()
            ->with([
                'email' => 'admin@example.com',
                'password' => 'password123',
            ])
            ->andReturn('jwt-token');

        JWTAuth::shouldReceive('claims')
            ->once()
            ->with(['rol' => Rol::ADMIN])
            ->andReturnSelf();

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.access_token', 'jwt-token');
        $response->assertJsonPath('data.rol', Rol::ADMIN);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        JWTAuth::shouldReceive('attempt')
            ->once()
            ->andReturn(false);

        $response = $this->postJson('/api/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Unauthorized');
        $response->assertJsonPath('data.error', 'Credenciales inválidas');
    }

    public function test_login_rejects_disabled_user(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('password123'),
            'rol_id' => Rol::where('name', Rol::ADMIN)->value('id'),
            'state_id' => State::where('name', State::DISABLED)->value('id'),
        ]);

        JWTAuth::shouldReceive('attempt')
            ->once()
            ->andReturn('jwt-token');

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $response = $this->postJson('/api/login', [
            'email' => 'disabled@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Usuario no habilitado');
    }
}