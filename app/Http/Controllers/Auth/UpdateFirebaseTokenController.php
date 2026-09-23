<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\UpdateFirebaseTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UpdateFirebaseTokenController extends BaseController
{
    public function __invoke(UpdateFirebaseTokenRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->sendError('Unauthorised', ['error' => 'No se encuentra logueado'], 401);
        }

        $user->firebase_token = $request->firebase_token;
        $user->save();

        return $this->sendMessageResponse('Firebase token actualizado correctamente');
    }
}
