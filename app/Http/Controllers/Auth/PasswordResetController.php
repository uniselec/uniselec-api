<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function forgot(Request $request)
    {
        $request->validate(['cpf' => 'required|string']);
        $user = User::where("cpf", $request->cpf)->first();
        if(!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $status = Password::sendResetLink(
            ['email' => $user->email]
        );
        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status), 'email' => $user->email])
            : response()->json(['email' => __($status)], 422);
    }
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['email' => __($status)], 422);
    }
}
