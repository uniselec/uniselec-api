<?php
// app/Services/ApplicationGeneratorService.php

namespace App\Services;

use App\Models\{ApplicationOutcome, ConvocationList, ConvocationListApplication};
use Illuminate\Support\Facades\DB;

class ApplicationGeneratorService
{
    /**
     * Para cada application aprovado, cria **uma linha por categoria**
     * escolhida na inscrição (form_data.admission_categories).
     *
     * @return int Quantidade total de linhas inseridas
     */
    public function generate(ConvocationList $convocationList): int
    {
        $processSelectionId = $convocationList->process_selection_id;

        /* ----------------------------------------------------------------
         * Outcomes aprovados do mesmo processo, ainda NÃO convocados
         * nesta lista (qualquer categoria).
         * -------------------------------------------------------------- */
        $outcomeQuery = ApplicationOutcome::query()
            ->where('status', 'approved')
            ->whereHas('application', function ($q) use ($processSelectionId) {
                $q->where('process_selection_id', $processSelectionId);
            })
            ->whereNotExists(function ($sub) use ($convocationList) {
                $sub->selectRaw(1)
                    ->from('convocation_list_applications as cla')
                    ->whereColumn('cla.application_id', 'application_outcomes.application_id')
                    ->where('cla.convocation_list_id', $convocationList->id);
            });

        $totalCreated = 0;
        $globalRank   = 0;                   // ranking_at_generation sequencial

        /* ----------------------------------------------------------------
         * Chunk para evitar estouro de memória
         * -------------------------------------------------------------- */
        DB::transaction(function () use (
            $outcomeQuery,
            $convocationList,
            &$totalCreated,
            &$globalRank
        ) {
            $outcomeQuery
                ->orderByDesc('final_score')
                ->orderByDesc('average_score')
                ->orderBy('application_id')
                ->chunk(200, function ($outcomes) use (
                    $convocationList,
                    &$totalCreated,
                    &$globalRank
                ) {
                    $rows = [];

                    foreach ($outcomes as $outcome) {
                        $form = $outcome->application->form_data;

                        // Curso escolhido na inscrição
                        $courseId = $form['position']['id'];

                        // Pode haver 1‒N categorias selecionadas
                        foreach ($form['admission_categories'] as $cat) {
                            $globalRank++;

                            $rows[] = [
                                'convocation_list_id'   => $convocationList->id,
                                'application_id'        => $outcome->application_id,
                                'course_id'             => $courseId,
                                'admission_category_id' => $cat['id'],
                                'ranking_at_generation' => $globalRank,
                                'status'                => 'eligible',
                                'created_at'            => now(),
                                'updated_at'            => now(),
                            ];
                        }
                    }

                    ConvocationListApplication::insert($rows);
                    $totalCreated += count($rows);
                });
        });

        return $totalCreated;
    }
}
