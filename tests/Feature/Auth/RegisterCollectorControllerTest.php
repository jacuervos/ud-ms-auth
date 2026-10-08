<?php

namespace Tests\Feature\Auth;

use App\Models\Collector;
use App\Models\Rol;
use App\Models\State;
use App\Models\TypeIdentification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegisterCollectorControllerTest extends TestCase
{
    use RefreshDatabase;

    private const COLLECTOR_EMAIL = 'collector@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        State::query()->firstOrCreate(
            ['name' => State::PENDING_USER],
            ['color' => 'yellow']
        );

        if (!Schema::hasTable('collectors')) {
            Schema::create('collectors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('identification_document')->nullable();
                $table->string('driving_license_document')->nullable();
                $table->unsignedBigInteger('state_id');
                $table->date('deleted_at')->nullable();
            });
        }

        config()->set('filesystems.disks.azure.url', 'https://example.test');
        config()->set('filesystems.disks.azure.container', 'container');
        Storage::fake('azure');
    }

    public function test_register_collector_creates_user_and_pending_collector(): void
    {
        $response = $this->postJson('/api/register_controller', [
            'name' => 'Carlos Collector',
            'phone' => '3009876543',
            'identification' => '999888777',
            'type_identification' => TypeIdentification::query()->value('id'),
            'email' => self::COLLECTOR_EMAIL,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'images' => UploadedFile::fake()->image('collector.png'),
            'identification_document' => UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'),
            'driving_license_document' => UploadedFile::fake()->create('license.pdf', 50, 'application/pdf'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Se ha creado el recolector correctamente');

        $this->assertDatabaseHas('users', [
            'email' => self::COLLECTOR_EMAIL,
            'rol_id' => Rol::where('name', Rol::RECYCLER)->value('id'),
            'state_id' => State::where('name', State::ENABLED)->value('id'),
        ]);

        $userId = (int) \DB::table('users')->where('email', self::COLLECTOR_EMAIL)->value('id');

        $this->assertDatabaseHas('collectors', [
            'user_id' => $userId,
            'state_id' => State::where('name', State::PENDING_USER)->value('id'),
        ]);
    }

    public function test_register_collector_requires_documents(): void
    {
        $response = $this->postJson('/api/register_controller', [
            'name' => 'Carlos Collector',
            'phone' => '3009876543',
            'identification' => '999888777',
            'type_identification' => TypeIdentification::query()->value('id'),
            'email' => 'collector2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'images' => UploadedFile::fake()->image('collector.png'),
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Validation errors');
    }
}
