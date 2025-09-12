<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Rol;
use App\Models\State;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends BaseController
{
    public function login(LoginRequest $loginRequest)
    {
        $credentials = $loginRequest->only('email', 'password');

        // Intentar autenticar al usuario
        if (!$token = JWTAuth::attempt($credentials)) {
            return $this->sendError('Unauthorized', ['error' => 'Credenciales inválidas'], 401);
        }

        // Obtener el usuario autenticado
        $user = Auth::user();

        // Verificar si el usuario está habilitado
        if (State::find($user->state_id)->name !== State::ENABLED) {
            return response(["message" => "Usuario no habilitado", "code" => 400], Response::HTTP_UNAUTHORIZED);
        }

        // Obtener el rol
        $rolUser = Rol::find($user->rol_id)->name;

        // Generar el token con el rol incluido como claim
        $customClaims = ['rol' => $rolUser];
        $token = JWTAuth::claims($customClaims)->attempt($credentials);

        // Devolver el token y el rol (opcional, para que el frontend lo tenga también)
        return $this->sendResponse(["access_token" => $token, "rol" => $rolUser], 'Login successfully');
    }
}
