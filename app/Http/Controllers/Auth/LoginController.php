<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ForgotPassword;
use App\Models\Rol;
use App\Models\State;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends BaseController
{
    public function login(LoginRequest $loginRequest)
    {
        try {
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

            // Un dispositivo por cuenta: el último login reemplaza el token Firebase.
            if ($loginRequest->filled('firebase_token')) {
                $user->firebase_token = $loginRequest->firebase_token;
                $user->save();
            }

            // Generar el token con el rol incluido como claim
            $customClaims = ['rol' => $rolUser];
            $token = JWTAuth::claims($customClaims)->attempt($credentials);

            // Devolver el token y el rol (opcional, para que el frontend lo tenga también)
            return $this->sendResponse(["access_token" => $token, "rol" => $rolUser], 'Login successfully');
        } catch (\Exception $e) {
            //Si la contraseña no esta encriptada y fue creada por backoffice
            if ($e->getMessage() == "This password does not use the Bcrypt algorithm."){
               //Se enviara un correo con el código para actualizar contraseña
               if($loginRequest->password === "RECYCLEUD2026"){
                   $exist = ForgotPassword::where('email', $loginRequest->email)->first();
                   if($exist){
                       $exist->delete();
                   }
                   $this->createCodeForgotPassword($loginRequest->email);
                   return response(["message" => "Actualizar contraseña", "code" => 400], Response::HTTP_UNAUTHORIZED);
               }
                return response(["message" => "No pudimos validar el correo, contacte a soporte", "code" => 400], Response::HTTP_UNAUTHORIZED);
            }
        }
    }

    public function createCodeForgotPassword($email)
    {
        $forgot = new ForgotPassword();
        $forgot->email = $email;
        $forgot->code = random_int(100000, 999999);
        $forgot->save();
    }
}
