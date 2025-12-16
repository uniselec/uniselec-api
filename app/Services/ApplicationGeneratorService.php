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
     * - ordena por final_score e, em caso de empate, por critérios
     * - define general/category ranking
     * - inicializa convocation_status, result_status e response_status
     *
     * Regra adicional (a partir da 2ª lista):
     * - Se a application já estiver em lista anterior do mesmo processo com
     *   convocation_status = called OU skipped => na nova lista entra como skipped
     *
     * @return int Total de linhas inseridas
     */
    public function generate(ConvocationList $list): int
    {
        $ps      = $list->processSelection;
        $psId    = $ps->id;
        $courses = collect($ps->courses)->keyBy('id');

        // todas as listas anteriores do mesmo processo
        $prevListIds = $ps->convocationLists()
            ->where('id', '<', $list->id)
            ->pluck('id')
            ->all();

        // 1) outcomes aprovados e ainda não gerados nesta lista
        $outcomes = ApplicationOutcome::with('application.enemScore')
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
            if (! $courseId) {
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
                    $birthA = $a->application->form_data['birthdate'] ?? null;
                    $birthB = $b->application->form_data['birthdate'] ?? null;
                    if ($birthA && $birthB && $birthA !== $birthB) {
                        return strtotime($birthA) <=> strtotime($birthB);
                    }

                    // 3–7) notas do ENEM
                    $scoresA = $a->application->enemScore->scores ?? [];
                    $scoresB = $b->application->enemScore->scores ?? [];

                    $metrics = [
                        'writing_score',
                        'language_score',
                        'math_score',
                        'science_score',
                        'humanities_score',
                    ];

                    foreach ($metrics as $key) {
                        $valA = (float) ($scoresA[$key] ?? 0);
                        $valB = (float) ($scoresB[$key] ?? 0);
                        if ($valA !== $valB) {
                            return $valB <=> $valA;
                        }
                    }

                    return 0;
                });

                $quota = $courses[$courseId]['vacanciesByCategory'][$catName] ?? 0;

                foreach ($chunk as $idx => $out) {
                    $globalRank++;
                    $categoryRank = $idx + 1;
                    $appId        = $out->application_id;

                    // ✅ NOVA REGRA: se já foi called OU skipped em lista anterior => skip agora
                    $skipPrev = !empty($prevListIds)
                        ? ConvocationListApplication::whereIn('convocation_list_id', $prevListIds)
                        ->where('application_id', $appId)
                        ->whereIn('convocation_status', ['called', 'skipped'])
                        ->exists()
                        : false;

                    $admissionCategories = $out->application->form_data['admission_categories'] ?? [];
                    $catIndex = array_search(
                        $catName,
                        array_column($admissionCategories, 'name'),
                        true
                    );

                    $catId = ($catIndex !== false)
                        ? ($admissionCategories[$catIndex]['id'] ?? null)
                        : null;

                    if (! $catId) {
                        // se não conseguir resolver a categoria, ignora por segurança
                        continue;
                    }

                    $rows[] = [
                        'convocation_list_id'   => $list->id,
                        'application_id'        => $appId,
                        'course_id'             => $courseId,
                        'admission_category_id' => $catId,
                        'general_ranking'       => $globalRank,
                        'category_ranking'      => $categoryRank,
                        'convocation_status' => $skipPrev ? 'skipped' : 'pending',
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
            $batchSize = 500;
            foreach (array_chunk($rows, $batchSize) as $batch) {
                ConvocationListApplication::insert($batch);
            }
        });

        return count($rows);
    }
}
