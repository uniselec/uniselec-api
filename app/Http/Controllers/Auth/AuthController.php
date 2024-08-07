<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{



    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Authenticate user and return token",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User authenticated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function auth(Request $request)
    {
        $input = $request->all();

        $validation = Validator::make($input, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(['error' => $validation->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::guard('web')->attempt($credentials)) {
            abort(401, 'Invalid Credentials');
        }

        $user = $request->user();
        $token = $user->createToken('api-web', ['web'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }
    public function authAdmin(Request $request)
    {
        $input = $request->all();
        $validation = Validator::make($input, [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if ($validation->fails()) {
            return response()->json(['error' => $validation->errors()], 422);
        }
        $credentials = $request->only('email', 'password');
        if (!Auth::guard('admin')->attempt($credentials)) {
            abort(401, 'Invalid Credentials');
        }
        $user = $request->user('admin');
        $token = $user->createToken('api-web', ['admin'])->plainTextToken;
        return response()->json(
            ['token' => $token, 'user' => $user]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Logout user",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=204,
     *         description="Logged out successfully"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();

        return response()->json(['message' => 'Logged out from all sessions successfully'], 204);
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     summary="Get authenticated user",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Authenticated user data",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json(
            ['me' => $user]
        );
    }
}
