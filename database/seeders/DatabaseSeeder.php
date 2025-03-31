<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Ejecutar los seeders en el orden correcto
        $this->call([
            RoleSeeder::class, 
            StateSeeder::class, 
            TypeIdentificationSeeder::class, 
            UserSeeder::class,  // Este se ejecuta al final para asegurar que los datos previos existen
        ]);
    }
}
