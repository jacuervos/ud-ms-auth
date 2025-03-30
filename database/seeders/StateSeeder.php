<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateSeeder extends Seeder
{
    public function run()
    {
        DB::table('states')->insert([
            ['name' => 'Habilitado', 'color' => 'green'],
            ['name' => 'Inhabilitado', 'color' => 'red'],
        ]);
    }
}
