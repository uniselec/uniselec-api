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

        /* -----------------------------------------------------------------
         | 1. Recupera outcomes aprovados + ordenação global desejada
         * -----------------------------------------------------------------*/
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

        /* -----------------------------------------------------------------
         | 2. Contadores
         * -----------------------------------------------------------------*/
        $globalRank       = 0;              // ranking_at_generation
        $categoryCounters = [];             // catId => current rank
        $totalInserted    = 0;

        /* -----------------------------------------------------------------
         | 3. Processa em chunks para não estourar memória
         * -----------------------------------------------------------------*/
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
                    $app       = $outcome->application;
                    $formData  = $app->form_data;

                    $courseId  = $formData['position']['id'];
                    $cats      = $formData['admission_categories'] ?? [];

                    foreach ($cats as $cat) {
                        $catId = $cat['id'];

                        // ranking na categoria
                        $categoryCounters[$catId] = ($categoryCounters[$catId] ?? 0) + 1;

                        $rows[] = [
                            'convocation_list_id'   => $convocationList->id,
                            'application_id'        => $app->id,
                            'course_id'             => $courseId,
                            'admission_category_id' => $catId,
                            'ranking_at_generation' => ++$globalRank,
                            'ranking_in_category'   => $categoryCounters[$catId],
                            'status'                => 'eligible',
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ];
                    }
                }

                ConvocationListApplication::insert($rows);
                $totalInserted += count($rows);
            });
        });

        return $totalInserted;
    }
}
