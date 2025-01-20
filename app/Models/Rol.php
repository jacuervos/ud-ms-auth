<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    const ADMIN = 'Administrador';
    const USER = 'Usuario';
    const RECYCLER = 'Recolector';

    public function users(){
        return $this->hasMany(User::class);
    }
}
