<?php

namespace Tests\Feature\Auth;

use App\Models\Rol;
use App\Models\State;
use App\Models\TypeIdentification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegisterUserControllerTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_USER_ENDPOINT = '/api/register_user';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        config()->set('filesystems.disks.azure.url', 'https://example.test');
        config()->set('filesystems.disks.azure.container', 'container');
        config()->set('services.level_service.url', 'https://level-service.test');
        Storage::fake('azure');
        Http::fake();
    }

    public function test_register_user_creates_user_and_calls_level_service(): void
    {
        $response = $this->postJson(self::REGISTER_USER_ENDPOINT, [
            'name' => 'Ana User',
            'phone' => '3001234567',
            'identification' => '111222333',
            'type_identification' => TypeIdentification::query()->value('id'),
            'email' => 'ana.user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'images' => UploadedFile::fake()->image('profile.png'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Se ha creado el usuario correctamente');

        $this->assertDatabaseHas('users', [
            'email' => 'ana.user@example.com',
            'rol_id' => Rol::where('name', Rol::USER)->value('id'),
            'state_id' => State::where('name', State::ENABLED)->value('id'),
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/user-level-init')
                && isset($request['user_id']);
        });
    }

    public function test_register_user_rejects_duplicate_email(): void
    {
        $payload = [
            'name' => 'Ana User',
            'phone' => '3001234567',
            'identification' => '111222333',
            'type_identification' => TypeIdentification::query()->value('id'),
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->postJson(self::REGISTER_USER_ENDPOINT, $payload)->assertOk();

        $response = $this->postJson(self::REGISTER_USER_ENDPOINT, $payload);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Validation errors');
        $response->assertJsonPath('data.email.0', 'El correo electrónico ya está registrado.');
    }
}
