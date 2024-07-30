<?php

namespace App\Http\Controllers\Api\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
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
}
