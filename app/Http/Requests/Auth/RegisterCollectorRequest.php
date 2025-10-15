<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class RegisterCollectorRequest extends FormRequest
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
             'password' => 'required|min:8|confirmed', // Contraseña obligatorio, mínimo 8 caracteres, debe coincidir con password_confirmation
             'phone' => 'required|string|max:15|regex:/^\+?[0-9]*$/', // Teléfono obligatorio, formato válido
             'identification' => 'required|string|max:50', // Identificación obligatorio, máx. 50 caracteres
             'type_identification' => 'required|integer|exists:type_identifications,id', // ID de tipo de identificación obligatorio, debe existir en otra tabla
             'images' => 'required|file|max:2048', // URL de imagen obligatorio, máx. 200 caracteres
             'identification_document' => 'required|file|mimes:pdf|max:2048', // URL de documento opcional, máx. 200 caracteres
             'driving_license_document' => 'required|file|mimes:pdf|max:2048',// URL de documento opcional, máx. 200 caracteres
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
             'phone.required' => 'El teléfono es obligatoria.',
             'identification.required' => 'El número de identificación es obligatoria.',
             'type_identification.required' => 'El tipo de identificación es obligatoria.',
             'image.required' => 'La imagen es obligatoria.',
             'identification_document.required' => 'El documento de identidad es obligatoria.',
             'driving_license_document.required' => 'El documento de licencia es obligatoria.',
         ];
     }
}
