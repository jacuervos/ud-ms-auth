<?php

namespace App\Http\Controllers\ForgotPassword;

use App\Http\Controllers\BaseController;
use App\Http\Requests\ForgotPassword\ChangePasswordRequest;
use App\Http\Requests\ForgotPassword\CreateCodeRequest;
use App\Http\Requests\ForgotPassword\ValidateCodeRequest;
use App\Models\ForgotPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends BaseController
{

    public function createCodeForgotPassword(CreateCodeRequest $createCodeRequest)
    {
        $forgot = New ForgotPassword();
        $forgot->email = $createCodeRequest->email;
        $forgot->code = random_int(100000, 999999);
        $forgot->save();
    }

    public function validateCode(ValidateCodeRequest $validateCodeRequest)
    {
        $exist = ForgotPassword::where('code', $validateCodeRequest->code)->first();
        if($exist){
            return $this->sendMessageResponse('Código valido, ahora cambia la contraseña');
        }else{
            return $this->sendError('Código no encontrado.');
        }
    }

    public function changePassword(ChangePasswordRequest $changePasswordRequest)
    {
        $exist = ForgotPassword::where('code', $changePasswordRequest->code)->first();
        $user = User::where('email', $changePasswordRequest->email)->first();
        $user->password = Hash::make($changePasswordRequest->password);
        $user->save();
        $exist->delete();
        return $this->sendMessageResponse('Contraseña cambiada ya puede iniciar sesión.');
    }
}
