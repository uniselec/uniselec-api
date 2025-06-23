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

        return response()->json($summary, 200);
    }
}
