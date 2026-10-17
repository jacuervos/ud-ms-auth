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

    public function test_login_returns_token_and_role_for_enabled_non_admin_user(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'rol_id' => Rol::where('name', Rol::USER)->value('id'),
            'state_id' => State::where('name', State::ENABLED)->value('id'),
        ]);

        JWTAuth::shouldReceive('attempt')
            ->twice()
            ->with([
                'email' => 'user@example.com',
                'password' => 'password123',
            ])
            ->andReturn('jwt-token');

        JWTAuth::shouldReceive('claims')
            ->once()
            ->with(['rol' => Rol::USER])
            ->andReturnSelf();

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.access_token', 'jwt-token');
        $response->assertJsonPath('data.rol', Rol::USER);
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

    public function test_login_returns_token_and_role_for_enabled_recycler_user(): void
    {
        $user = User::factory()->create([
            'email' => 'recycler@example.com',
            'password' => Hash::make('password123'),
            'rol_id' => Rol::where('name', Rol::RECYCLER)->value('id'),
            'state_id' => State::where('name', State::ENABLED)->value('id'),
        ]);

        JWTAuth::shouldReceive('attempt')
            ->twice()
            ->with([
                'email' => 'recycler@example.com',
                'password' => 'password123',
            ])
            ->andReturn('jwt-token');

        JWTAuth::shouldReceive('claims')
            ->once()
            ->with(['rol' => Rol::RECYCLER])
            ->andReturnSelf();

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $response = $this->postJson('/api/login', [
            'email' => 'recycler@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.access_token', 'jwt-token');
        $response->assertJsonPath('data.rol', Rol::RECYCLER);
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

    public function test_login_updates_firebase_token_and_handles_legacy_non_bcrypt_password(): void
    {
        $user = User::factory()->create([
            'email' => 'firebase@example.com',
            'password' => Hash::make('password123'),
            'rol_id' => Rol::where('name', Rol::USER)->value('id'),
            'state_id' => State::where('name', State::ENABLED)->value('id'),
        ]);

        JWTAuth::shouldReceive('attempt')
            ->twice()
            ->andReturn('jwt-token');
        JWTAuth::shouldReceive('claims')
            ->once()
            ->andReturnSelf();
        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $res = $this->postJson('/api/login', [
            'email' => 'firebase@example.com',
            'password' => 'password123',
            'firebase_token' => 'fcm-device-token-123',
        ]);
        $res->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'firebase_token' => 'fcm-device-token-123',
        ]);

        \Illuminate\Support\Facades\DB::table('forgot_passwords')->insert([
            'email' => 'legacy@example.com',
            'code' => 111111,
        ]);

        JWTAuth::shouldReceive('attempt')
            ->twice()
            ->andThrow(new \Exception('This password does not use the Bcrypt algorithm.'));

        $legacyRes = $this->postJson('/api/login', [
            'email' => 'legacy@example.com',
            'password' => 'RECYCLEUD2026',
        ]);
        $legacyRes->assertStatus(401);
        $legacyRes->assertJsonPath('message', 'Actualizar contraseña');

        $invalidLegacyRes = $this->postJson('/api/login', [
            'email' => 'legacy@example.com',
            'password' => 'wrong-legacy',
        ]);
        $invalidLegacyRes->assertStatus(401);
        $invalidLegacyRes->assertJsonPath('message', 'No pudimos validar el correo, contacte a soporte');
    }
}