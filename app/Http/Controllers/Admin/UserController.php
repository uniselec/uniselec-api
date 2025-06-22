<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use App\Notifications\UserResetPasswordNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;
use EloquentFilter\Filterable;
use Illuminate\Support\Facades\Storage;

class UserController extends BasicCrudController
{

    private $rules = [
        'name' => 'required|max:255',
        'nickname' => '',
        'email' => 'required|max:255',
        'mobile' => '',
        'phone' => '',
        'institutional_phone' => '',
        'joined_at' => '',
        'birthdate' => '',
        'gender' => '',
        'enrollment' => '',
        'cpf' => '',
        'rg' => '',
        'rg_issued_at' => '',
        'rg_issuer' => '',
        'rg_state' => '',
        'address' => '',
        'address_number' => '',
        'address_complement' => '',
        'neighborhood' => '',
        'city' => '',
        'nucleus_code' => '',
        'state' => '',
        'zip_code' => '',
        'degree' => '',
        'position' => '',
        'functional_status' => '',
        'institutional_email' => '',
        'email_verified_at' => '',
        'remember_token' => '',
        'password' => '',
        'status' => '',
        'documents' => '',
    ];
    public function resendPasswordResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'E-mail inválido ou não encontrado.'], 422);
        }

        $email = $request->input('email');

        Password::broker('users')->sendResetLink(
            ['email' => $email],
            function ($user, $token) {
                $frontendUrl = config('app.frontend_url');
                $resetLink = "{$frontendUrl}/reset-password/{$token}/" . urlencode($user->email);

                $user->notify(new UserResetPasswordNotification($resetLink));
            }
        );

        return response()->json(['message' => 'Link de redefinição reenviado com sucesso.']);
    }
    protected function model()
    {
        return User::class;
    }

    protected function rulesStore()
    {
        return $this->rules;
    }

    protected function rulesUpdate()
    {
        return $this->rules;
    }

    protected function resourceCollection()
    {
        return $this->resource();
    }
    public function show($id)
    {
        $user = User::findOrFail($id);
        return new UserResource($user);
    }

    protected function resource()
    {
        return UserResource::class;
    }
}
