<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConvocationList;
use App\Services\ApplicationGeneratorService;
use Illuminate\Http\JsonResponse;

class ConvocationListApplicationGenerationController extends Controller
{
    public function store(
        ApplicationGeneratorService $applicationGeneratorService,
        int $convocationList
    ): JsonResponse {
        $convocationListModel = ConvocationList::findOrFail($convocationList);

        if ($convocationListModel->applications()->exists()) {
            return response()->json(
                ['message' => 'Aplicações já geradas para esta lista.'],
                422
            );
        }

        $created = $applicationGeneratorService->generate($convocationListModel);

        return response()->json([
            'message' => 'Aplicações geradas com sucesso.',
            'created' => $created,
        ], 201);
    }
}
