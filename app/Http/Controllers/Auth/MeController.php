<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MeController extends BaseController
{
    public function authMe(): JsonResponse
    {
        if (auth('sanctum')->check()){
            $user = auth('sanctum')->user();
            $user = UserResource::make(User::find($user->id));
            return $this->sendResponse($user, 'User info');
        } else {
            return $this->sendError('Unauthorised', ['error'=>'No se encuentra logueado'],400);
        }
    }
}