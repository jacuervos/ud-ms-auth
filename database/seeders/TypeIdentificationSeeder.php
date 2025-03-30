<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TypeIdentificationSeeder extends Seeder
{
    public function run()
    {
        DB::table('type_identifications')->insert([
            ['name' => 'Cédula de Ciudadanía'],
            ['name' => 'Tarjeta de Identidad'],
            ['name' => 'Cédula de Extranjería'],
            ['name' => 'Pasaporte'],
        ]);
    }
}
