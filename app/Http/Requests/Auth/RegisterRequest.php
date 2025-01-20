<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */

     public function failedValidation(Validator $validator)
     {
         throw new HttpResponseException(response()->json([
             'success'   => false,
             'message'   => 'Validation errors',
             'data'      => $validator->errors()
         ],400));
     }

     public function rules()
     {
         return [
             'name' => 'required|string|max:100', // Nombre es obligatorio, texto y máx. 100 caracteres
             'email' => 'required|email|unique:users,email', // Email obligatorio, válido y único en la tabla users
             'password' => 'required|min:8|confirmed', // Contraseña obligatoria, mínimo 8 caracteres, debe coincidir con password_confirmation
             'phone' => 'nullable|string|max:15|regex:/^\+?[0-9]*$/', // Teléfono opcional, formato válido
             'identification' => 'nullable|string|max:50', // Identificación opcional, máx. 50 caracteres
             'type_identification_id' => 'nullable|integer|exists:identification_types,id', // ID de tipo de identificación opcional, debe existir en otra tabla
             'rol_id' => 'required|integer|exists:rols,id', // ID del rol obligatorio, debe existir en la tabla roles
             'state_id' => 'nullable|integer|exists:states,id', // ID del estado opcional, debe existir en la tabla states
             'image' => 'nullable|string|max:200', // URL de imagen opcional, máx. 200 caracteres
         ];
     }
 
     /**
      * Get custom messages for validation errors.
      *
      * @return array<string, string>
      */
     public function messages()
     {
         return [
             'name.required' => 'El nombre es obligatorio.',
             'email.required' => 'El correo electrónico es obligatorio.',
             'email.unique' => 'El correo electrónico ya está registrado.',
             'password.required' => 'La contraseña es obligatoria.',
             'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
             'password.confirmed' => 'La confirmación de la contraseña no coincide.',
             'phone.regex' => 'El número de teléfono no es válido.',
             'rol_id.required' => 'El rol es obligatorio.',
             'rol_id.exists' => 'El rol seleccionado no es válido.',
         ];
     }
}
