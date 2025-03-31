<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        DB::table('rols')->insert([
            ['name' => 'Administrador'],
            ['name' => 'Usuario'],
            ['name' => 'Recolector'],
        ]);
    }
}
