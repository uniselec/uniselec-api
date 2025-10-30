<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateSeatsRequest;
use App\Models\ConvocationList;
use App\Services\SeatGeneratorService;
use Illuminate\Http\JsonResponse;

class ConvocationListSeatGenerationController extends Controller
{
    public function store(
        GenerateSeatsRequest $request,
        SeatGeneratorService $service,
        int $list
    ): JsonResponse {
        $convList = ConvocationList::findOrFail($list);

        // (opcional) impedir duplicação total:
        if ($convList->seats()->exists()) {
            return response()->json([
                'message' => 'Esta lista já possui vagas geradas.'
            ], 422);
        }

        // $total = $service->generate($convList, $request->input('seats'));

        return response()->json([
            'message' => "Gerado com sucesso",
            // 'created' => $total
        ], 201);
    }
}
