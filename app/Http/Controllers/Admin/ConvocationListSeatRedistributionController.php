<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConvocationList;
use App\Services\SeatRedistributionService;
use Illuminate\Http\JsonResponse;

class ConvocationListSeatRedistributionController extends Controller
{
    /**
     * Executa o serviço de redistribuição até que:
     *   – não haja mais inscrições elegíveis, OU
     *   – todas as vagas estejam preenchidas.
     */
    public function store(
        SeatRedistributionService $service,
        int $convocationList
    ): JsonResponse {
        $list = ConvocationList::with(['seats', 'applications'])->findOrFail($convocationList);

        [$filled, $leftOpen] = $service->redistribute($list);

        return response()->json([
            'message'       => 'Redistribuição concluída.',
            'seats_filled'  => $filled,
            'seats_open'    => $leftOpen,
        ]);
    }
}
