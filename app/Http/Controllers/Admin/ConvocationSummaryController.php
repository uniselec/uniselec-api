<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessSelection;
use Illuminate\Http\JsonResponse;

class ConvocationSummaryController extends Controller
{
    /**
     * Retorna, para cada lista de convocação de um processo,
     * o total de convocados ("called") por categoria de ingresso.
     */
    public function index(ProcessSelection $selection): JsonResponse
    {
        // carrega todas as listas do processo, com suas aplicações já convocadas
        $lists = $selection
            ->convocationLists()
            ->with(['applications' => function($q) {
                $q->where('convocation_status', 'called')
                  ->with('category'); // vamos precisar do nome da categoria
            }])
            ->get();

        // monta o payload
        $payload = $lists->map(function($list) {
            // agrupa por nome de categoria e conta
            $counts = $list->applications
                ->groupBy(fn($app) => $app->category->name)
                ->map->count()
                ->toArray();

            return [
                'listId'   => (string)$list->id,
                'listName' => $list->name,
                'counts'   => $counts,
            ];
        });

        return response()->json($payload);
    }
}
