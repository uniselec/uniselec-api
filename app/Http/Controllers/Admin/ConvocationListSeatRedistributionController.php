<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConvocationListSeat;
use App\Services\SeatRedistributionService;
use Illuminate\Http\JsonResponse;

class ConvocationListSeatRedistributionController extends Controller
{
    public function redistribute(int $seat, SeatRedistributionService $service): JsonResponse
    {
        $seat = ConvocationListSeat::findOrFail($seat);

        $updated = $service->redistributeSeat($seat);

        if (! $updated) {
            return response()->json([
                'message' => 'Vaga não pôde ser redistribuída (status ou sem regras aplicáveis).'
            ], 422);
        }

        return response()->json([
            'message'    => 'Vaga redistribuída com sucesso.',
            'seat_id'    => $seat->id,
            'new_category_id' => $seat->current_admission_category_id,
        ], 200);
    }
}
