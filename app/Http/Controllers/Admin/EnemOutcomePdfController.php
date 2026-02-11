<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessSelection;
use App\Services\EnemOutcomePdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnemOutcomePdfController extends Controller
{
    public function __invoke(
        ProcessSelection $selection,
        EnemOutcomePdfService $pdfService
    ): Response {
        try {
            return $pdfService->export($selection);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
