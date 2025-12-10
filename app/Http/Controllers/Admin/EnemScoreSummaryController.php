<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationOutcome;
use App\Models\EnemScore;
use App\Models\ProcessSelection;

class EnemScoreSummaryController extends Controller
{
    public function __invoke(ProcessSelection $processSelection)
    {
        $processId = $processSelection->id;

        // 1) Total de inscrições do processo
        $totalApplications = Application::where('process_selection_id', $processId)
            ->count();

        // 2) Total de inscrições que têm ENEM importado (enem_scores)
        $totalWithScore = EnemScore::whereHas('application', function ($q) use ($processId) {
                $q->where('process_selection_id', $processId);
            })
            ->count();

        // 3) Total sem ENEM (inscrição existe mas não tem registro em enem_scores)
        $totalWithoutScore = $totalApplications - $totalWithScore;

        // 4) Total com ENEM, mas "Candidato não encontrado" no arquivo do INEP
        //    No import você salvou como N/A nos campos de scores
        $totalNotFoundInInepFile = EnemScore::whereHas('application', function ($q) use ($processId) {
                    $q->where('process_selection_id', $processId);
                })
                ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(scores, "$.name")) = ?', ['N/A'])
                ->count();

        // 5) Total de application_outcomes com status 'pending'
        $totalPendingOutcomes = ApplicationOutcome::where('status', 'pending')
            ->whereHas('application', function ($q) use ($processId) {
                $q->where('process_selection_id', $processId);
            })
            ->count();

        return response()->json([
            'process_selection_id'           => $processSelection->id,
            'process_selection_name'         => $processSelection->name,
            'total_applications'             => $totalApplications,
            'total_with_score'               => $totalWithScore,
            'total_without_score'            => $totalWithoutScore,
            'total_not_found_in_inep_file'   => $totalNotFoundInInepFile,
            'total_pending_outcomes'         => $totalPendingOutcomes,
        ]);
    }
}
