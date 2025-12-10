<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessSelection;
use App\Services\EnemScoreExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnemScoreExportController extends Controller
{
    public function export(
        Request $request,
        ProcessSelection $selection,
        EnemScoreExportService $exportService
    ): StreamedResponse|RedirectResponse {
        // Garante que a seleção está ativa
        if ($selection->status !== 'active') {
            return redirect()->back()->withErrors('Seleção não está ativa.');
        }

        // Filtro opcional por ano do ENEM
        $enemYear = $request->query('enem_year');
        $enemYear = $enemYear !== null && $enemYear !== '' ? (int) $enemYear : null;

        // Dispara o export
        return $exportService->export($selection, $enemYear);
    }
}
