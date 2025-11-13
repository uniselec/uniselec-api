<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\VerificationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Password;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Rules\ValidCpf;
use Illuminate\Support\Str;


// Authenticate
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'authAdmin'])->name('admin.login');
});

Route::post('/register', [RegisterController::class, 'register'])->name('client.register');

//Essas vão ficar indisponíveis até o lançamento da versão do cliente.
Route::prefix('client')->group(function () {
    Route::post('/login', [AuthController::class, 'auth'])->name('client.login');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');




// Client verify email
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['signed'])
    ->name('verification.verify');

// Client Resent email verification link
Route::post('/email/resend', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return response()->json(['message' => 'E-mail de verificação reenviado.']);
})->middleware('auth:sanctum');


//Client password forgot
// ESQUECI MINHA SENHA (cpf ou e-mail)
Route::post('/password/forgot', function (Request $request) {
    $messages = [
        'identifier.required' => 'O campo CPF ou e-mail é obrigatório.',
        'identifier.string'   => 'O identificador deve ser uma cadeia de caracteres.',
    ];
    $request->validate([
        'identifier' => ['required', 'string'],
    ], $messages);

    $id = $request->input('identifier');

    // 2️⃣ DETECTA SE É E-MAIL OU CPF (somente dígitos)

    // Remove tudo que não for dígito e valida CPF com regra customizada
    $cpf = preg_replace('/\D/', '', $id);

    $cpfValidator = Validator::make(
        ['cpf' => $cpf],
        ['cpf' => ['required', 'size:11', new ValidCpf]],
        [
            'cpf.required' => 'O CPF é obrigatório.',
            'cpf.size'     => 'O CPF deve conter exatamente 11 dígitos.',
        ]
    );
    if ($cpfValidator->fails()) {
        throw ValidationException::withMessages($cpfValidator->errors()->toArray());
    }

    $user = User::where('cpf', $cpf)->first();

    // 3️⃣ SE USUÁRIO NÃO EXISTE, RETORNA ERRO EM PORTUGUÊS
    if (! $user) {
        throw ValidationException::withMessages([
            'identifier' => ['Usuário não encontrado com esse CPF ou e-mail.'],
        ]);
    }

    // 4️⃣ DISPARA LINK DE RECUPERAÇÃO PARA O E-MAIL DO USUÁRIO
    $status = Password::broker('users')->sendResetLink(
        ['email' => $user->email]
    );

    // 5️⃣ TRATA TODOS OS RETORNOS POSSÍVEIS DO BROKER, EM PORTUGUÊS
    switch ($status) {
        case Password::RESET_LINK_SENT:
            return response()->json([
                'message' => 'Link de recuperação enviado para o e-mail: ' . $user->email,
            ], 200);

        case Password::INVALID_USER:
            return response()->json([
                'message' => 'Não foi possível encontrar usuário com esse CPF ou e-mail.',
            ], 422);

        default:
            // Qualquer outro erro genérico de envio
            return response()->json([
                'message' => 'Falha ao enviar o link de recuperação. Por favor, tente novamente mais tarde.',
            ], 500);
    }
})->name('password.forgot');


//Client password reset
Route::post('/password/reset', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'token' => 'required',
        'password' => 'required|min:8|confirmed',
    ]);

    $status = Password::broker('users')->reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => bcrypt($password),
            ])->save();
        }
    );

    return $status === Password::PASSWORD_RESET
        ? response()->json(['message' => __($status)])
        : response()->json(['message' => __($status)], 400);
})->name('password.reset');


//Client password forgot
Route::post('/admin/password/forgot', function (Request $request) {
    $request->validate(['email' => 'required|email']);

    $status = Password::broker('admins')->sendResetLink(
        $request->only('email')
    );

    return $status === Password::RESET_LINK_SENT
        ? response()->json(['message' => __($status)])
        : response()->json(['message' => __($status)], 400);
})->name('admin.password.forgot');


// Admin password reset
Route::post('/admin/password/reset', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'token' => 'required',
        'password' => 'required|min:8|confirmed',
    ]);

    $status = Password::broker('admins')->reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => bcrypt($password),
            ])->save();
        }
    );
    return $status === Password::PASSWORD_RESET
        ? response()->json(['message' => 'Senha redefinida com sucesso.'])
        : response()->json(['message' => 'Erro ao redefinir senha.'], 400);
})->name('admin.password.reset');

