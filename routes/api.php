<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth
    Route::post('login', '\App\Http\Controllers\Auth\LoginController@login')->name('auth.login');
    Route::post('register_controller', '\App\Http\Controllers\Auth\RegisterCollectorController@registerApp')->name('auth.registerCollector'); // Registrar collector desde la app
    Route::post('register_user', '\App\Http\Controllers\Auth\RegisterUserController@registerUserApp')->name('auth.registerUser'); // Registrar usuario desde la app
    Route::post('register', '\App\Http\Controllers\Auth\RegisterController@register')->name('auth.register'); // Registrar un nuevo usuario
    Route::get('type_identifications', '\App\Http\Controllers\TypeIdentification\TypeIdentificationController@index')->name('typeIdentification.index'); // Ver tipo de documentos

    Route::post('validate_code', '\App\Http\Controllers\ForgotPassword\ChangePasswordController@validateCode')->name('forgotPassword.validate'); // Validar código
    Route::post('change_password', '\App\Http\Controllers\ForgotPassword\ChangePasswordController@changePassword')->name('forgotPassword.changePassword'); // Cambiar contraseña

    // Rutas protegidas con JWT
    Route::middleware(['auth:api'])->group(function () {
        Route::get('auth_me', '\App\Http\Controllers\Auth\MeController@authMe')->name('auth.me');
        Route::post('logout', '\App\Http\Controllers\Auth\LogoutController@logout')->name('auth.logout');
    });
