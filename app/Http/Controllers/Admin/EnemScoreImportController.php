<?php

namespace App\Http\Controllers\Admin;

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EnemScoreImportService;
use Illuminate\Http\Request;

class EnemScoreImportController extends Controller
{
    public function __invoke(Request $request, EnemScoreImportService $service)
    {
        $data = $request->validate([
            'file'                 => 'required|file|mimes:csv,txt',
            'process_selection_id' => 'required|integer|exists:process_selections,id',
            'async'                => 'sometimes|boolean',   // opcional: fila
        ]);


        // Processa síncrono
        $summary = $service->import($data['file'], $data['process_selection_id']);
        if (
            ($summary['processed'] ?? 0) > 0 &&
            ($summary['created'] ?? 0) === 0 &&
            ($summary['updated'] ?? 0) === 0
        ) {
            return response()->json([
                'message' => 'Nenhuma nota do ENEM foi importada. Verifique se o arquivo corresponde ao processo de seleção e se há candidatos com notas.',
                'summary' => $summary,
            ], 422);
        }
        return response()->json($summary, 200);
    }
}
