<?php

namespace App\Http\Controllers\TypeIdentification;

use App\Http\Controllers\Controller;
use App\Models\TypeIdentification;

class TypeIdentificationController extends Controller
{
    public function index()
    {
        return response()->json(TypeIdentification::all());
    }
}
