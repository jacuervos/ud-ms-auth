<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth
Route::prefix('auth')->group(function () {
    Route::post('login', '\App\Http\Controllers\Auth\LoginController@login')->name('auth.login');
    Route::post('register', '\App\Http\Controllers\Auth\RegisterController@register')->name('auth.register'); // Registrar un nuevo usuario

    // Rutas protegidas con JWT
    Route::middleware(['jwt.auth'])->group(function () {
        Route::get('auth_me', '\App\Http\Controllers\Auth\MeController@authMe')->name('auth.me');
        Route::post('logout', '\App\Http\Controllers\Auth\LogoutController@logout')->name('auth.logout');
    });
});
