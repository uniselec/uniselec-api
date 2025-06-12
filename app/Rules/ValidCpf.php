<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidCpf implements Rule
{
    public function passes($attribute, $value): bool
    {
        $cpf = preg_replace('/\D/', '', $value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;

        $calc = function ($len) use ($cpf) {
            $sum = 0;
            for ($i = 0; $i < $len; $i++) $sum += $cpf[$i] * (($len + 1) - $i);
            $d = (10 * $sum) % 11;
            return $d == 10 ? 0 : $d;
        };

        return $calc(9) == $cpf[9] && $calc(10) == $cpf[10];
    }

    public function message(): string
    {
        return 'CPF inválido.';
    }
}
