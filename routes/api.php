<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Auth
Route::prefix('auth')->group(function () {
    Route::post('login', '\App\Http\Controllers\Auth\LoginController@login')->name('auth.login');
    Route::post('register', '\App\Http\Controllers\Auth\RegisterController@register')->name('auth.register'); // Registrar un nuevo usuario
    Route::get('auth_me', '\App\Http\Controllers\Auth\MeController@authMe')->name('auth.me'); //ver usuario autenticado
    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::post('logout', '\App\Http\Controllers\Auth\LogoutController@logout')->name('auth.logout'); //cerrar cesión
    }); 
});