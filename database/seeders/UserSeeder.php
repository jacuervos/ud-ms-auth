<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
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
            'type_identification_id' => 1, // Asegúrate de que este ID exista
            'rol_id' => 1,  // Asegúrate de que este ID exista en la tabla rols
            'state_id' => 1, // Asegúrate de que este ID exista en la tabla states
            'image' => null,
            'password' => Hash::make('password123'),
            'points' => 50,
        ]);
    }
}
