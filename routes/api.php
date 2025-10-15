<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth
    Route::post('login', '\App\Http\Controllers\Auth\LoginController@login')->name('auth.login');
    Route::post('register_controller', '\App\Http\Controllers\Auth\RegisterCollectorController@registerApp')->name('auth.registerCollector'); // Registrar collector desde la app
    Route::post('register', '\App\Http\Controllers\Auth\RegisterController@register')->name('auth.register'); // Registrar un nuevo usuario
    Route::get('type_identifications', '\App\Http\Controllers\TypeIdentification\TypeIdentificationController@index')->name('typeIdentification.index'); // Ver tipo de documentos

    // Rutas protegidas con JWT
    Route::middleware(['auth:api'])->group(function () {
        Route::get('auth_me', '\App\Http\Controllers\Auth\MeController@authMe')->name('auth.me');
        Route::post('logout', '\App\Http\Controllers\Auth\LogoutController@logout')->name('auth.logout');
    });
