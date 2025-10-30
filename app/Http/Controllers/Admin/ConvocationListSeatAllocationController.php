<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConvocationList;
use App\Services\SeatAllocatorService;
use Illuminate\Http\JsonResponse;

class ConvocationListSeatAllocationController extends Controller
{
    public function store(
        SeatAllocatorService $seatAllocatorService,
        int $convocationList
    ): JsonResponse {
        // $convocationListModel = ConvocationList::with(['seats', 'applications'])
        //     ->findOrFail($convocationList);

        // if (!$convocationListModel->seats()->exists()) {
        //     return response()->json(
        //         ['message' => 'Gere as vagas antes de alocar.'],
        //         422
        //     );
        // }
        // if (!$convocationListModel->applications()->exists()) {
        //     return response()->json(
        //         ['message' => 'Gere as aplicações antes de alocar.'],
        //         422
        //     );
        // }

        // $counters = $seatAllocatorService->allocate($convocationListModel);

        return response()->json([
            'message' => 'Distribuição concluída.',
            'result'  => 0,
        ]);
    }
}
