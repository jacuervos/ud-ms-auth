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

        $user = User::where('email', $createCodeRequest->email)->whereNull('deleted_at') ->first();

        if (!$user) { return response()->json([ 'message' => 'No existe un usuario asociado a este correo.' ], 404); }

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
<!DOCTYPE html>
<html lang=\"es\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Recuperación de contraseña</title>
</head>

<body style=\"
    margin: 0;
    padding: 0;
    background-color: #f5f7fb;
    font-family: Arial, Helvetica, sans-serif;
    color: #333333;
\">

<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"
       style=\"background-color: #f5f7fb; padding: 40px 20px;\">
    <tr>
        <td align=\"center\">

            <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"
                   style=\"
                       max-width: 560px;
                       background-color: #ffffff;
                       border-radius: 16px;
                       overflow: hidden;
                       box-shadow: 0 4px 20px rgba(0,0,0,0.06);
                   \">

                <!-- Encabezado -->
                <tr>
                    <td align=\"center\" style=\"padding: 35px 30px 25px;\">

                        <div style=\"
                            width: 56px;
                            height: 56px;
                            line-height: 56px;
                            background-color: #fce8f0;
                            border-radius: 50%;
                            font-size: 28px;
                        \">
                            🔐
                        </div>

                        <h1 style=\"
                            margin: 20px 0 8px;
                            font-size: 24px;
                            color: #222222;
                            font-weight: 700;
                        \">
                            Recuperación de contraseña
                        </h1>

                        <p style=\"
                            margin: 0;
                            font-size: 15px;
                            color: #777777;
                            line-height: 1.6;
                        \">
                            Estamos aquí para ayudarte a recuperar el acceso a tu cuenta.
                        </p>

                    </td>
                </tr>

                <!-- Contenido -->
                <tr>
                    <td style=\"padding: 10px 40px 35px;\">

                        <p style=\"
                            margin: 0 0 20px;
                            font-size: 16px;
                            line-height: 1.6;
                            color: #444444;
                        \">
                            Has solicitado restablecer tu contraseña.
                            Utiliza el siguiente código de verificación para continuar:
                        </p>

                        <!-- Código -->
                        <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
                            <tr>
                                <td align=\"center\"
                                    style=\"
                                        padding: 25px;
                                        background-color: #fff4f8;
                                        border: 1px solid #ffd6e5;
                                        border-radius: 12px;
                                    \">

                                    <span style=\"
                                        font-size: 36px;
                                        font-weight: 700;
                                        letter-spacing: 8px;
                                        color: #e85d91;
                                    \">
                                        {$code}
                                    </span>

                                </td>
                            </tr>
                        </table>

                        <p style=\"
                            margin: 25px 0 0;
                            font-size: 14px;
                            line-height: 1.6;
                            color: #777777;
                            text-align: center;
                        \">
                            Ingresa este código en la aplicación para continuar
                            con el proceso de recuperación.
                        </p>

                        <!-- Separador -->
                        <div style=\"
                            height: 1px;
                            background-color: #eeeeee;
                            margin: 30px 0;
                        \"></div>

                        <!-- Seguridad -->
                        <p style=\"
                            margin: 0;
                            font-size: 13px;
                            line-height: 1.6;
                            color: #888888;
                            text-align: center;
                        \">
                            🔒 Si no solicitaste recuperar tu contraseña,
                            puedes ignorar este correo. Tu cuenta seguirá segura.
                        </p>

                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td align=\"center\"
                        style=\"
                            padding: 20px 30px;
                            background-color: #fafafa;
                            border-top: 1px solid #eeeeee;
                        \">

                        <p style=\"
                            margin: 0;
                            font-size: 12px;
                            color: #999999;
                        \">
                            Este es un correo automático. Por favor, no respondas a este mensaje.
                        </p>

                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

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
