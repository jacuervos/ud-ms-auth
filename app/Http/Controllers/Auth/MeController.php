<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class MeController extends BaseController
{
    public function authMe(): JsonResponse
    {
        // Intentar obtener el usuario autenticado con JWT
        $user = JWTAuth::parseToken()->authenticate();

        if ($user) {
            return $this->sendResponse(UserResource::make($user), 'User info');
        } else {
            return $this->sendError('Unauthorised', ['error' => 'No se encuentra logueado'], 401);
        }
    }
}
