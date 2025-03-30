<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\JsonResponse;

class LogoutController extends BaseController
{
    public function logout(Request $request): JsonResponse
    {
        try {
            // Obtener el token
            $token = JWTAuth::getToken();

            if (!$token) {
                return $this->sendError('No se encontró un token válido', [], 400);
            }

            // Invalidar el token
            JWTAuth::invalidate($token);

            return $this->sendResponse([], 'Sesión cerrada exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al cerrar sesión', ['error' => $e->getMessage()], 500);
        }
    }
}
