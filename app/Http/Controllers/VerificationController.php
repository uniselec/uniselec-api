<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function verify(Request $request)
    {
        $client = User::findOrFail($request->route('id'));

        if (! hash_equals((string) $request->route('hash'), sha1($client->getEmailForVerification()))) {
            return response()->json(['message' => 'Assinatura inválida.'], 403);
        }

        if (! $client->hasVerifiedEmail()) {
            $client->markEmailAsVerified();
        }

        return response()->json(['message' => 'E-mail verificado com sucesso!']);
    }
}
