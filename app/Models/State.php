<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{

    protected $fillable = [
        'name',
        'color',
    ];

    //User
    const ENABLED = 'Habilitado';
    const DISABLED = 'Inhabilitado';
    const PENDING_USER = 'Pendiente de Validar';

    public function users(){
        return $this->hasMany(User::class);
    }
}
