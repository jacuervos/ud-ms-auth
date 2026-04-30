<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\Rol;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class RegisterUserController extends BaseController
{
    public function saveStorage($image, $route): string
    {
        $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $image->getClientOriginalExtension();
        $safeName = Str::slug($originalName, '_');
        $imageName = $safeName . '.' . $extension;
        $nameRoute = 'images/' . $route . '/';
        Storage::disk('azure')->putFileAs($nameRoute, $image, $imageName);
        $url = rtrim(config('filesystems.disks.azure.url'), '/') . '/' .
            config('filesystems.disks.azure.container') . '/' .
            $nameRoute . $imageName;
        return $url;
    }

    public function registerUserApp(RegisterUserRequest $request)
    {
        $user = New User();
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->identification = $request->identification;
        $user->type_identification_id = $request->type_identification;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->state_id = State::where('name', State::ENABLED)->value('id');
        $user->rol_id = Rol::where('name', Rol::USER)->value('id');
        if($request->images){
            $image = $this->saveStorage($request->images, 'users');
            $user->image = $image;
        }
        $user->save();
        $data = [
            'message' => 'Se ha creado el usuario correctamente',
            'code' => 200,
        ];
        Http::baseUrl(config('services.level_service.url'))
            ->post('/user-level-init', [
                'user_id' => $user->id,
            ]);
        return response()->json($data);
    }
}
