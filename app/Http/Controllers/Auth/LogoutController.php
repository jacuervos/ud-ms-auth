<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseController;
use App\Models\User;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutController extends BaseController
{
    public function logout(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'email' => ['required', 'email'],
        ]);
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());       
        }
        $userLogin = User::where('email', $request->email)->first();
        $userLogin->tokens()->delete();
        return $this->sendResponse($userLogin, 'Sesión cerrada');
    }
}