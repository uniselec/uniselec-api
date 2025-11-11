<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check: retorna status da API e do banco de dados.
     */
    public function ready(): JsonResponse
    {
        $deps = [
            'db' => 'UP',
        ];

        try {
            // força uma operação simples para testar a conexão
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $deps['db'] = 'DOWN';
        }

        $overall = collect($deps)->contains('DOWN') ? 'DOWN' : 'UP';

        return response()->json([
            'status' => $overall,
            'deps'   => $deps,
        ], $overall === 'UP' ? 200 : 503);
    }
}
