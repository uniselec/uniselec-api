<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConvocationList;
use App\Services\ConvocationListCsvExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\RedirectResponse;

class ConvocationListCsvController extends Controller
{
    public function exportCsv(
        ConvocationList $list,
        ConvocationListCsvExportService $csvService
    ): StreamedResponse|RedirectResponse {
        return $csvService->exportCsv($list);
    }
}
