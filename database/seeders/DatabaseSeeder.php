<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear múltiples usuarios de prueba
        User::factory(10)->create();

        // Crear un usuario específico
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'identification' => '987654321',
            'type_identification_id' => 1,
            'rol_id' => 2,
            'state_id' => 1,
            'image' => null,
            'password' => bcrypt('password123'),
            'points' => 50,
        ]);
    }
}
