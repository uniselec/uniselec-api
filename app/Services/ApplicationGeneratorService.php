<?php

namespace App\Services;

use App\Models\ApplicationOutcome;
use App\Models\ConvocationList;
use App\Models\ConvocationListApplication;
use Illuminate\Support\Facades\DB;

class ApplicationGeneratorService
{
    /**
     * Gera registros em convocation_list_applications:
     * - agrupa por curso e categoria (usando o nome)
     * - ordena pelo final_score e average_score
     * - define general e category ranking
     * - seta convocation_status, result_status e response_status
     *
     * @return int  Total de linhas inseridas
     */
    public function generate(ConvocationList $list): int
    {
        $ps        = $list->processSelection;
        $psId      = $ps->id;
        $courses   = collect($ps->courses)->keyBy('id'); // bloqueios por curso
        // quais listas anteriores já existem
        $prevListIds = $ps->convocationLists()
                          ->where('id', '<', $list->id)
                          ->pluck('id')
                          ->all();

        // 1) busca todos outcomes aprovados e ainda não inseridos
        $outcomes = ApplicationOutcome::with('application')
            ->where('status', 'approved')
            ->whereHas('application', fn($q) => $q->where('process_selection_id', $psId))
            ->whereNotExists(function($sub) use($list) {
                $sub->selectRaw('1')
                    ->from('convocation_list_applications as cla')
                    ->whereColumn('cla.application_id','application_outcomes.application_id')
                    ->where('cla.convocation_list_id',$list->id);
            })
            ->get();

        // 2) agrupa em memória: [curso][categoriaNome] => lista de outcomes
        $grouped = [];
        foreach ($outcomes as $outcome) {
            $appData   = $outcome->application->form_data;
            $courseId  = $appData['position']['id'] ?? null;
            $categories = $appData['admission_categories'] ?? [];

            if (!$courseId) {
                continue;
            }

            foreach ($categories as $cat) {
                $catId   = $cat['id'];
                $catName = $cat['name'];
                $grouped[$courseId][$catName][] = $outcome;
            }
        }

        $rows          = [];
        $globalRank    = 0;

        // 3) percorre cada grupo, ordena e monta linhas
        foreach ($grouped as $courseId => $byCatName) {
            foreach ($byCatName as $catName => $chunk) {
                // ordenação
                usort($chunk, function($a, $b) {
                    if ($a->final_score !== $b->final_score) {
                        return $b->final_score <=> $a->final_score;
                    }
                    return $b->average_score <=> $a->average_score;
                });

                // cota do edital
                $quota = $courses[$courseId]['vacanciesByCategory'][$catName] ?? 0;

                foreach ($chunk as $index => $outcome) {
                    $globalRank++;
                    $categoryRank = $index + 1;

                    // checa se recusou antes
                    $declinedPrev = ConvocationListApplication::whereIn('convocation_list_id', $prevListIds)
                        ->where('application_id', $outcome->application_id)
                        ->where('response_status', 'declined')
                        ->exists();

                    $rows[] = [
                        'convocation_list_id'   => $list->id,
                        'application_id'        => $outcome->application_id,
                        'course_id'             => $courseId,
                        'admission_category_id' => $outcome->application
                                                       ->form_data['admission_categories']
                                                       [array_search($catName,
                                                           array_column(
                                                               $outcome->application
                                                                   ->form_data['admission_categories'], 'name')
                                                           )
                                                       ]['id'],
                        'general_ranking'       => $globalRank,
                        'category_ranking'      => $categoryRank,
                        'convocation_status'    => 'pending',
                        'result_status'         => ($categoryRank <= $quota)
                                                   ? 'classified'
                                                   : 'classifiable',
                        'response_status'       => $declinedPrev
                                                   ? 'declined_other_list'
                                                   : 'pending',
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ];
                }
            }
        }

        // 4) insere tudo de uma vez
        DB::transaction(function() use($rows) {
            if (!empty($rows)) {
                ConvocationListApplication::insert($rows);
            }
        });

        return count($rows);
    }
}
