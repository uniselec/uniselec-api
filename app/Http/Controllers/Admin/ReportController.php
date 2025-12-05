<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessSelection;
use App\Services\ProcessSelectionApplicationCsvService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function exportApplications(
        Request $request,
        ProcessSelection $selection,
        ProcessSelectionApplicationCsvService $csvService
    ): StreamedResponse|RedirectResponse {
        if ($selection->status !== 'active') {
            return redirect()->back()->withErrors('Seleção não está ativa.');
        }

        // ?enem_year=2024 (opcional)
        $enemYear = $request->query('enem_year');
        $enemYear = $enemYear !== null && $enemYear !== '' ? (int) $enemYear : null;

        // ?only_enem=1 (opcional)
        // exemplo: ?only_enem=true ou ?only_enem=1
        $onlyEnemNumbers = $request->boolean('only_enem', false);

        return $csvService->export($selection, $enemYear, $onlyEnemNumbers);
    }
}
