<?php
// app/Services/ApplicationGeneratorService.php

namespace App\Services;

use App\Models\{
    ApplicationOutcome,
    ConvocationList,
    ConvocationListApplication
};
use Illuminate\Support\Facades\DB;

class ApplicationGeneratorService
{
    /**
     * Gera registros em convocation_list_applications.
     * Cria uma linha por (application x categoria selecionada).
     *
     * @return int  Total de linhas inseridas
     */
    public function generate(ConvocationList $convocationList): int
    {
        $psId = $convocationList->process_selection_id;

        $outcomes = ApplicationOutcome::query()
            ->where('status', 'approved')
            ->whereHas('application', fn ($q) => $q->where('process_selection_id', $psId))
            ->whereNotExists(function ($sub) use ($convocationList) {
                $sub->selectRaw(1)
                    ->from('convocation_list_applications as cla')
                    ->whereColumn('cla.application_id', 'application_outcomes.application_id')
                    ->where('cla.convocation_list_id', $convocationList->id);
            })
            ->orderByDesc('final_score')
            ->orderByDesc('average_score')
            ->orderBy('application_id');

        $globalRank       = 0;
        $categoryCounters = [];  // catId => current rank
        $totalInserted    = 0;

        DB::transaction(function () use (
            $outcomes,
            $convocationList,
            &$globalRank,
            &$categoryCounters,
            &$totalInserted
        ) {
            $outcomes->chunk(500, function ($chunk) use (
                $convocationList,
                &$globalRank,
                &$categoryCounters,
                &$totalInserted
            ) {
                $rows = [];

                foreach ($chunk as $outcome) {
                    $app = $outcome->application;

                    // garante array independente do tipo vindo do banco
                    $formData = is_array($app->form_data)
                        ? $app->form_data
                        : (json_decode($app->form_data ?? '[]', true) ?? []);

                    $courseId  = $formData['position']['id'] ?? null;
                    $cats      = $formData['admission_categories'] ?? [];

                    if (!$courseId || empty($cats)) {
                        continue;
                    }

                    // ranking global só 1x por application
                    $globalRank++;

                    foreach ($cats as $cat) {
                        $catId = $cat['id'];

                        // ranking por categoria
                        $categoryCounters[$catId] = ($categoryCounters[$catId] ?? 0) + 1;

                        $rows[] = [
                            'convocation_list_id'   => $convocationList->id,
                            'application_id'        => $app->id,
                            'course_id'             => $courseId,
                            'admission_category_id' => $catId,
                            'general_ranking' => $globalRank,
                            'category_ranking'   => $categoryCounters[$catId],
                            // 'status'                => 'eligible',
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ];
                    }
                }

                if ($rows) {
                    ConvocationListApplication::insert($rows);
                    $totalInserted += count($rows);
                }
            });
        });

        return $totalInserted;
    }
}
