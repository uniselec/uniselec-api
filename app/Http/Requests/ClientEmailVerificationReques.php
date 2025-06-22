<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Auth\EmailVerificationRequest;

class ClientEmailVerificationRequest extends EmailVerificationRequest
{
    public function authorize()
    {
        $client = \App\Models\User::find($this->route('id'));

        if (! $client) {
            return false;
        }

        return hash_equals((string) $this->route('hash'), sha1($client->getEmailForVerification()));
    }
}
