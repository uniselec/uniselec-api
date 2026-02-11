<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessSelection;
use App\Services\EnemOutcomeExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnemOutcomeExportController extends Controller
{
    /**
     * GET /admin/super_user/process-selections/{selection}/export-enem-outcomes
     */
    public function export(
        Request $request,
        ProcessSelection $selection,
        EnemOutcomeExportService $exportService
    ): StreamedResponse {
        if ($selection->status !== 'active') {
            abort(422, 'Seleção não está ativa.');
        }

        // filtro opcional por ano do ENEM
        $enemYear = $request->query('enem_year');
        $enemYear = ($enemYear !== null && $enemYear !== '') ? (int) $enemYear : null;

        return $exportService->export($selection, $enemYear);
    }
}
