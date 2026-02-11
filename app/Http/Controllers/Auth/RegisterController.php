<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;

class RegisterController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register a new user",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="root4@dsgoextractor.com"),
     *             @OA\Property(property="cpf", type="string", format="cpf", example="25787968409"),
     *             @OA\Property(property="password", type="string", format="password", example="root")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error on create a new user"
     *     )
     * )
     */
    public function register(Request $request, User $user)
    {
        $input = $request->all();
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'cpf' => 'required|string|unique:users,cpf',
            'password' => 'required|string|min:6',
        ]);

        if ($validation->fails()) {
            return response()->json(['error' => $validation->errors()], 422);
        }


        $userData = $request->only('name', 'cpf', 'email', 'password');
        $userData['password'] = bcrypt($userData['password']);
        if (!$user = $user->create($userData)) {
            abort(500, 'Error on create a new user');
        } else {
            $token = $user->createToken('api-web')->plainTextToken;
            return response()->json([
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 201);
        }
    }
    public function registerAdmin(Request $request, Admin $user)
    {

        $userData = $request->only('name', 'email', 'password');
        $userData['password'] = bcrypt($userData['password']);

        if (!$user = $user->create($userData)) {
            abort(500, 'Error on create a new user');
        } else {
            $token = $user->createToken('api-web')->plainTextToken;
            $user['token'] = $token;
            return response()->json([
                'data' => [
                    'user' => $user
                ]
            ]);
        }
    }



    public function updateProfileClient(Request $request)
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['message' => 'User is not a client.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
                // se quiser reforçar a regra do front:
                // 'regex:/^(?=.*[0-9])(?=.*[^A-Za-z0-9]).+$/',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Só campos válidos entram aqui (name, email, password, password_confirmation)
        $data = $validator->validated();

        // Trata senha: se veio preenchida, faz hash; se não, remove
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Não queremos tentar dar update com password_confirmation
        unset($data['password_confirmation']);

        // Atualiza apenas os campos presentes em $data
        $user->update($data);

        // Gera novo token (mantendo o padrão que você já usa)
        $token = $user->createToken('api-web')->plainTextToken;
        $user['token'] = $token;
        $user->refresh();

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ], 200);
    }

    public function updateProfileAdmin(Request $request)
    {
        $user = $request->user();
        if (!$user instanceof Admin) {
            return response()->json(['message' => 'User is not an admin.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:admins,email,' . $user->id,
            'password' => 'nullable|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        $token = $user->createToken('api-web', [$user->role])->plainTextToken;

        return response()->json(
            ['token' => $token, 'user' => $user],
            200
        );
    }
}
