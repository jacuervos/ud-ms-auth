<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    public $timestamps = true;

    protected $hidden = [
        'password'
    ];

    public function rol()
    {
        return $this->belongsTo(Rol::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function typeIdentification()
    {
        return $this->belongsTo(TypeIdentification::class);
    }

    public function collector()
    {
        return $this->hasOne(Collector::class);
    }

    public function getJWTIdentifier()
    {
           return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
           return [];
    }
}
