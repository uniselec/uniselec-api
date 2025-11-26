<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessSelection;
use App\Services\ProcessSelectionApplicationCsvService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function exportApplications(
        ProcessSelection $selection,
        ProcessSelectionApplicationCsvService $csvService
    ): StreamedResponse|RedirectResponse {
        if ($selection->status !== 'active') {
            return redirect()->back()->withErrors('Seleção não está ativa.');
        }

        return $csvService->export($selection);
    }
}
