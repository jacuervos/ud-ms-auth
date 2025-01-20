<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Response;
use App\Models\State;
use Illuminate\Support\Facades\Hash;

class RegisterController extends BaseController
{
    public function register(RegisterRequest $request)
    {
        // Crear al usuario con los datos validados
        $user = new User;

        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->phone = $request->phone;
        $user->identification = $request->identification;
        $user->type_identification_id = $request->type_identification_id;
        $user->rol_id = $request->rol_id;
        $user->state_id = State::where('name', State::ENABLED)->value('id');
        $user->image = $request->image;
        $user->save();

        return $this->sendResponse($user, 'User register successfully.');
    }
}
