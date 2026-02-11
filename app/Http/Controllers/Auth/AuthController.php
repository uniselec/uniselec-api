<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Rules\ValidCpf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function auth(Request $request)
    {
        // 1️⃣ Valida só o identificador e a senha
        $data = $request->validate([
            'identifier' => ['required', 'string'],           // e-mail ou CPF
            'password'   => ['required', 'string'],
        ]);

        $login = $data['identifier'];

        // 2️⃣ Detecta e busca o usuário
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $login)->first();
        } else {
            // limpa tudo que não for dígito
            $cpf = preg_replace('/\D/', '', $login);
            // opcional: valida o formato
            // Validator::make(['cpf' => $cpf], ['cpf' => ['required', 'size:11', new ValidCpf]])->validate();
            $user = User::where('cpf', $cpf)->first();
        }

        if (! $user) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        // 3️⃣ Checa a senha
        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }
        // if($user->status !== 'active') {
        //     return response()->json(['message' => 'User is not active.'], 403);
        // }
        // 4️⃣ Emite o token Sanctum
        $token = $user->createToken('api-web', ['web'])->plainTextToken;
        $user = new UserResource($user);
        return response()->json([
            'token' => $token,
            'user'  => $user,
        ], 200);
    }
    public function authAdmin(Request $request)
    {
        $input = $request->all();
        $validation = Validator::make($input, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        if (!Auth::guard('admin')->attempt($credentials)) {
            abort(401, 'Invalid Credentials');
        }
        $user = $request->user('admin');

        $token = $user->createToken('api-web', [$user->role])->plainTextToken;
        return response()->json(
            ['token' => $token, 'user' => $user]
        );
    }

    public function logout(Request $request)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.'], 200);
    }


    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json(
            ['me' => $user]
        );
    }
    public function meClient(Request $request)
    {
        $user = $request->user();
        $user = User::with('statusLogs')->findOrFail($user->id);
        return new UserResource($user);
    }
    public function invalidateAllTokens(Request $request)
    {
        PersonalAccessToken::query()->delete();

        return response()->json(['message' => 'Todos os tokens foram invalidados com sucesso.'], 200);
    }
}
