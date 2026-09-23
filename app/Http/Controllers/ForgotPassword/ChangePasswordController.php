<?php

namespace App\Http\Controllers\ForgotPassword;

use App\Http\Controllers\BaseController;
use App\Http\Requests\ForgotPassword\ChangePasswordRequest;
use App\Http\Requests\ForgotPassword\CreateCodeRequest;
use App\Http\Requests\ForgotPassword\ValidateCodeRequest;
use App\Models\ForgotPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\AzureEmailService;

class ChangePasswordController extends BaseController
{

    public function createCodeForgotPassword(
        CreateCodeRequest $createCodeRequest,
        AzureEmailService $azureEmailService
    ) {
        $exist = ForgotPassword::where(
            'email',
            $createCodeRequest->email
        )->first();

        if ($exist) {
            $exist->delete();
        }

        $code = random_int(100000, 999999);

        $forgot = new ForgotPassword();
        $forgot->email = $createCodeRequest->email;
        $forgot->code = $code;
        $forgot->save();

        try {
            $azureEmailService->send(
                $createCodeRequest->email,
                'Código para recuperar tu contraseña',
                "
            <html>
                <body>
                    <h2>Recuperación de contraseña</h2>

                    <p>
                        Has solicitado recuperar tu contraseña.
                    </p>

                    <p>
                        Tu código de verificación es:
                    </p>

                    <h1>{$code}</h1>

                    <p>
                        Si no solicitaste este código, puedes ignorar este correo.
                    </p>
                </body>
            </html>
            "
            );
        } catch (\Throwable $e) {
            dd($e->getMessage());
            $forgot->delete();
            return response()->json([
                'message' => 'No fue posible enviar el código al correo.'
            ], 500);
        }

        return $this->sendMessageResponse(
            'Código enviado, revisar correo.'
        );
    }

    public function validateCode(ValidateCodeRequest $validateCodeRequest)
    {
        $exist = ForgotPassword::where('code', $validateCodeRequest->code)->first();
        if($exist){
            return $this->sendMessageResponse('Código válido, ahora cambia la contraseña');
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
