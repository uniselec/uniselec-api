<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check: retorna status da API e do banco de dados.
     */
    public function __invoke(): JsonResponse
    {
        $deps = [
            'db' => 'connected',
        ];

        // força uma operação simples para testar a conexão
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
        } catch (\Exception $e) {
            $deps['db'] = 'down';
        }

        $overall = collect($deps)->contains('down') ? 'down' : 'ok';

        return response()->json([
            'status' => $overall,
            'timestamp'   => now()->toIso8601String(),
            'database'   => $deps['db'],
        ], $overall === 'ok' ? 200 : 503);
    }
}
