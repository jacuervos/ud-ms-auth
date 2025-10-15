<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Collector extends Model
{
    public $timestamps = false;

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
