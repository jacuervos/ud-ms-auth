<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\State;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LoginController extends BaseController
{
    public function login(LoginRequest $loginRequest)
    {
        $userLogin = User::where('email', $loginRequest->email)->first();
        if (Auth::attempt(['email' => $loginRequest->email, 'password' => $loginRequest->password]))
        {
                $userLogin->tokens()->delete();
                if(State::find($userLogin->state_id)->name === State::ENABLED){
                    $user = Auth::user();
                    $rol=Rol::find($user->rol_id);
                    $token = $user->createToken('token', [$rol->name])->plainTextToken;
                    return $this->sendResponse(["access_token" => $token], 'Login successfully');
                } else {
                    return response(["message"=> "Usuario no habilitado", "code" => 400],Response::HTTP_UNAUTHORIZED);
                }
        }else {
            return $this->sendError('Unauthorised', ['error'=>'Credenciales inválidas'],400);
        }
    }

}