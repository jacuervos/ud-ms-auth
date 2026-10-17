<?php

namespace Tests\Feature\ForgotPassword;

use App\Models\ForgotPassword;
use App\Models\Rol;
use App\Models\State;
use App\Models\User;
use App\Services\AzureEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class ChangePasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_admin_can_request_validate_and_change_password(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.recovery@example.com',
            'password' => Hash::make('oldpassword123'),
            'rol_id' => Rol::where('name', Rol::ADMIN)->value('id'),
            'state_id' => State::where('name', State::ENABLED)->value('id'),
        ]);

        $this->mock(AzureEmailService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')
                ->once()
                ->withArgs(function (string $to, string $subject, string $html) {
                    return $to === 'admin.recovery@example.com'
                        && str_contains($subject, 'Código para recuperar tu contraseña')
                        && str_contains($html, 'Recuperación de contraseña');
                })
                ->andReturn(['status' => 202, 'data' => [], 'operation_location' => null]);
        });

        $sendCodeResponse = $this->postJson('/api/send_code', [
            'email' => $admin->email,
        ]);

        $sendCodeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Código enviado, revisar correo.');

        $record = ForgotPassword::where('email', $admin->email)->first();
        $this->assertNotNull($record);

        $validateResponse = $this->postJson('/api/validate_code', [
            'code' => $record->code,
        ]);

        $validateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Código válido, ahora cambia la contraseña');

        $changeResponse = $this->postJson('/api/change_password', [
            'email' => $admin->email,
            'code' => $record->code,
            'password' => 'newAdminPass123',
            'password_confirmation' => 'newAdminPass123',
        ]);

        $changeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Contraseña cambiada ya puede iniciar sesión.');

        $this->assertTrue(Hash::check('newAdminPass123', $admin->fresh()->password));
        $this->assertDatabaseMissing('forgot_passwords', [
            'email' => $admin->email,
        ]);
    }

    public function test_user_can_request_validate_and_change_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user.recovery@example.com',
            'password' => Hash::make('oldpassword123'),
            'rol_id' => Rol::where('name', Rol::USER)->value('id'),
            'state_id' => State::where('name', State::ENABLED)->value('id'),
        ]);

        $this->mock(AzureEmailService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['status' => 202, 'data' => [], 'operation_location' => null]);
        });

        $sendCodeResponse = $this->postJson('/api/send_code', [
            'email' => $user->email,
        ]);

        $sendCodeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Código enviado, revisar correo.');

        $record = ForgotPassword::where('email', $user->email)->first();
        $this->assertNotNull($record);

        $validateResponse = $this->postJson('/api/validate_code', [
            'code' => $record->code,
        ]);

        $validateResponse->assertOk()
            ->assertJsonPath('success', true);

        $changeResponse = $this->postJson('/api/change_password', [
            'email' => $user->email,
            'code' => $record->code,
            'password' => 'newUserPass123',
            'password_confirmation' => 'newUserPass123',
        ]);

        $changeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Contraseña cambiada ya puede iniciar sesión.');

        $this->assertTrue(Hash::check('newUserPass123', $user->fresh()->password));
        $this->assertDatabaseMissing('forgot_passwords', [
            'email' => $user->email,
        ]);
    }

    public function test_collector_can_request_validate_and_change_password(): void
    {
        $collector = User::factory()->create([
            'email' => 'collector.recovery@example.com',
            'password' => Hash::make('oldpassword123'),
            'rol_id' => Rol::where('name', Rol::RECYCLER)->value('id'),
            'state_id' => State::where('name', State::ENABLED)->value('id'),
        ]);

        $this->mock(AzureEmailService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['status' => 202, 'data' => [], 'operation_location' => null]);
        });

        $sendCodeResponse = $this->postJson('/api/send_code', [
            'email' => $collector->email,
        ]);

        $sendCodeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Código enviado, revisar correo.');

        $record = ForgotPassword::where('email', $collector->email)->first();
        $this->assertNotNull($record);

        $validateResponse = $this->postJson('/api/validate_code', [
            'code' => $record->code,
        ]);

        $validateResponse->assertOk()
            ->assertJsonPath('success', true);

        $changeResponse = $this->postJson('/api/change_password', [
            'email' => $collector->email,
            'code' => $record->code,
            'password' => 'newCollectorPass123',
            'password_confirmation' => 'newCollectorPass123',
        ]);

        $changeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Contraseña cambiada ya puede iniciar sesión.');

        $this->assertTrue(Hash::check('newCollectorPass123', $collector->fresh()->password));
        $this->assertDatabaseMissing('forgot_passwords', [
            'email' => $collector->email,
        ]);
    }

    public function test_forgot_password_rejects_unknown_email_and_invalid_code(): void
    {
        $missingEmailResponse = $this->postJson('/api/send_code', [
            'email' => 'nonexistent@example.com',
        ]);

        $missingEmailResponse->assertStatus(404)
            ->assertJsonPath('message', 'No existe un usuario asociado a este correo.');

        $invalidCodeResponse = $this->postJson('/api/validate_code', [
            'code' => '000000',
        ]);

        $invalidCodeResponse->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Código no encontrado.');
    }
}

