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
     * - agrupa por curso e categoria
     * - ordena por final_score/average_score
     * - define general/category ranking
     * - inicializa convocation_status, result_status e response_status
     *   * se já “called+accepted” em lista anterior → convocation_status = skipped
     *
     * @return int Total de linhas inseridas
     */
    public function generate(ConvocationList $list): int
    {
        $ps        = $list->processSelection;
        $psId      = $ps->id;
        $courses   = collect($ps->courses)->keyBy('id');
        // listas anteriores
        $prevListIds = $ps->convocationLists()
                          ->where('id','<',$list->id)
                          ->pluck('id')
                          ->all();

        // 1) outcomes aprovados e ainda não gerados nesta lista
        $outcomes = ApplicationOutcome::with('application')
            ->where('status','approved')
            ->whereHas('application', fn($q) => $q->where('process_selection_id',$psId))
            ->whereNotExists(function($sub) use($list) {
                $sub->selectRaw('1')
                    ->from('convocation_list_applications as cla')
                    ->whereColumn('cla.application_id','application_outcomes.application_id')
                    ->where('cla.convocation_list_id',$list->id);
            })
            ->get();

        // 2) agrupa em memória por curso e nome de categoria
        $grouped = [];
        foreach ($outcomes as $out) {
            $data     = $out->application->form_data;
            $courseId = $data['position']['id'] ?? null;
            if (!$courseId) continue;

            foreach ($data['admission_categories'] ?? [] as $cat) {
                $grouped[$courseId][$cat['name']][] = $out;
            }
        }

        $rows       = [];
        $globalRank = 0;

        // 3) para cada grupo, ordena e monta a linha
        foreach ($grouped as $courseId => $byCat) {
            foreach ($byCat as $catName => $chunk) {
                usort($chunk, function($a,$b){
                    return [$b->final_score,$b->average_score] <=> [$a->final_score,$a->average_score];
                });

                $quota = $courses[$courseId]['vacanciesByCategory'][$catName] ?? 0;

                foreach ($chunk as $idx => $out) {
                    $globalRank++;
                    $categoryRank = $idx + 1;

                    $appId = $out->application_id;

                    // já foi called+accepted em lista anterior?
                    $acceptedPrev = ConvocationListApplication::whereIn('convocation_list_id',$prevListIds)
                        ->where('application_id',$appId)
                        ->where('convocation_status','called')
                        ->where('response_status','accepted')
                        ->exists();

                    $rows[] = [
                        'convocation_list_id'   => $list->id,
                        'application_id'        => $appId,
                        'course_id'             => $courseId,
                        'admission_category_id' => $out->application
                            ->form_data['admission_categories']
                            [array_search($catName,
                                array_column(
                                    $out->application->form_data['admission_categories'],
                                    'name'
                                )
                            )]['id'],
                        'general_ranking'       => $globalRank,
                        'category_ranking'      => $categoryRank,
                        // já aceitou antes? pula direto
                        'convocation_status'    => $acceptedPrev ? 'skipped' : 'pending',
                        'result_status'         => ($categoryRank <= $quota) ? 'classified' : 'classifiable',
                        // se pulado, não há resposta; senão, começa pending
                        'response_status'       => $acceptedPrev
                                                   ?  'pending'
                                                   : 'pending',
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ];
                }
            }
        }

        // 4) insere tudo
        DB::transaction(fn() => ConvocationListApplication::insert($rows));

        return count($rows);
    }
}
