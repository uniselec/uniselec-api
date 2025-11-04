<?php

namespace App\Services;

use App\Models\ApplicationOutcome;
use App\Models\ConvocationList;
use App\Models\ConvocationListApplication;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplicationGeneratorService
{
    /**
     * Gera registros em convocation_list_applications:
     * - agrupa por curso e categoria
     * - ordena por final_score e, em caso de empate, por:
     *     a) idade (mais velho primeiro)
     *     b) nota de Redação
     *     c) nota de Linguagens
     *     d) nota de Matemática
     *     e) nota de Ciências da Natureza
     *     f) nota de Ciências Humanas
     * - define general/category ranking
     * - inicializa convocation_status, result_status e response_status
     *
     * @return int Total de linhas inseridas
     */
    public function generate(ConvocationList $list): int
    {
        $ps          = $list->processSelection;
        $psId        = $ps->id;
        $courses     = collect($ps->courses)->keyBy('id');
        $prevListIds = $ps->convocationLists()
            ->where('id', '<', $list->id)
            ->pluck('id')
            ->all();

        // 1) outcomes aprovados e ainda não gerados nesta lista
        $outcomes = ApplicationOutcome::with('application')
            ->where('status', 'approved')
            ->whereHas('application', fn($q) => $q->where('process_selection_id', $psId))
            ->whereNotExists(function ($sub) use ($list) {
                $sub->selectRaw('1')
                    ->from('convocation_list_applications as cla')
                    ->whereColumn('cla.application_id', 'application_outcomes.application_id')
                    ->where('cla.convocation_list_id', $list->id);
            })
            ->get();

        // 2) agrupa em memória por curso e nome de categoria
        $grouped = [];
        foreach ($outcomes as $out) {
            $data     = $out->application->form_data;
            $courseId = $data['position']['id'] ?? null;
            if (!$courseId) {
                continue;
            }
            foreach ($data['admission_categories'] ?? [] as $cat) {
                $grouped[$courseId][$cat['name']][] = $out;
            }
        }

        $rows       = [];
        $globalRank = 0;

        // 3) percorre cada grupo, ordena e monta linhas
        foreach ($grouped as $courseId => $byCat) {
            foreach ($byCat as $catName => $chunk) {
                usort($chunk, function ($a, $b) {
                    // 1) final_score
                    if ($a->final_score !== $b->final_score) {
                        return $b->final_score <=> $a->final_score;
                    }

                    // 2) idade (mais velho primeiro)
                    // supondo form_data['birthdate'] no formato YYYY-MM-DD
                    $dobA = strtotime($a->application->form_data['birthdate']);
                    $dobB = strtotime($b->application->form_data['birthdate']);
                    if ($dobA !== $dobB) {
                        return $dobA <=> $dobB; // menor timestamp = mais velho
                    }

                    // 3) Redação
                    $scoresA = $a->scores; // ou original_scores, conforme seu model
                    $scoresB = $b->scores;
                    if ($scoresA['redacao'] !== $scoresB['redacao']) {
                        return $scoresB['redacao'] <=> $scoresA['redacao'];
                    }
                    // 4) Linguagens
                    if ($scoresA['linguagens'] !== $scoresB['linguagens']) {
                        return $scoresB['linguagens'] <=> $scoresA['linguagens'];
                    }
                    // 5) Matemática
                    if ($scoresA['matematica'] !== $scoresB['matematica']) {
                        return $scoresB['matematica'] <=> $scoresA['matematica'];
                    }
                    // 6) Ciências da Natureza
                    if ($scoresA['natureza'] !== $scoresB['natureza']) {
                        return $scoresB['natureza'] <=> $scoresA['natureza'];
                    }
                    // 7) Ciências Humanas
                    if ($scoresA['humanas'] !== $scoresB['humanas']) {
                        return $scoresB['humanas'] <=> $scoresA['humanas'];
                    }
                    return 0;
                });

                $quota = $courses[$courseId]['vacanciesByCategory'][$catName] ?? 0;

                foreach ($chunk as $idx => $out) {
                    $globalRank++;
                    $categoryRank = $idx + 1;
                    $appId        = $out->application_id;

                    // já foi called+accepted em lista anterior?
                    $acceptedPrev = ConvocationListApplication::whereIn('convocation_list_id', $prevListIds)
                        ->where('application_id', $appId)
                        ->where('convocation_status', 'called')
                        ->where('response_status', 'accepted')
                        ->exists();

                    $rows[] = [
                        'convocation_list_id'   => $list->id,
                        'application_id'        => $appId,
                        'course_id'             => $courseId,
                        'admission_category_id' => $out->application
                            ->form_data['admission_categories'][array_search(
                            $catName,
                            array_column($out->application->form_data['admission_categories'], 'name')
                        )]['id'],
                        'general_ranking'       => $globalRank,
                        'category_ranking'      => $categoryRank,
                        'convocation_status'    => $acceptedPrev ? 'skipped' : 'pending',
                        'result_status'         => ($categoryRank <= $quota) ? 'classified' : 'classifiable',
                        'response_status'       => 'pending',
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ];
                }
            }
        }

        // 4) insere tudo de uma vez
        DB::transaction(function () use ($rows) {
            if (!empty($rows)) {
                ConvocationListApplication::insert($rows);
            }
        });

        return count($rows);
    }
}
