<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConvocationList;
use App\Services\ConvocationPdfExportService;
use Illuminate\Http\Response;

class ConvocationListPdfController extends Controller
{

    public function export(
        ConvocationList $list,
        ConvocationPdfExportService $csvService
    ) {
        return $csvService->export($list);
    }
}
