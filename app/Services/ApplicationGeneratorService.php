<?php
// app/Services/ApplicationGeneratorService.php

namespace App\Services;

use App\Models\ApplicationOutcome;
use App\Models\ConvocationList;
use App\Models\ConvocationListApplication;
use Illuminate\Support\Facades\DB;

class ApplicationGeneratorService
{
    /**
     * Cria registros em convocation_list_applications para a lista informada.
     *
     * @return int  Total de linhas inseridas
     */
    public function generate(ConvocationList $convocationList): int
    {
        $processSelectionId = $convocationList->process_selection_id;

        // Outcomes aprovados cujo application pertence a este processo
        // e ainda não foi convocado nesta lista.
        $outcomeQuery = ApplicationOutcome::query()
            ->where('status', 'approved')
            ->whereHas('application', function ($query) use ($processSelectionId) {
                $query->where('process_selection_id', $processSelectionId);
            })
            ->whereNotExists(function ($subQuery) use ($convocationList) {
                $subQuery->selectRaw(1)
                    ->from('convocation_list_applications as cla')
                    ->whereColumn('cla.application_id', 'application_outcomes.application_id')
                    ->where('cla.convocation_list_id', $convocationList->id);
            });

        $totalCreated = 0;

        DB::transaction(function () use ($outcomeQuery, $convocationList, &$totalCreated) {
            $outcomeQuery
                ->orderByDesc('final_score')
                ->orderByDesc('average_score')
                ->orderBy('application_id')
                ->chunk(200, function ($chunk) use ($convocationList, &$totalCreated) {

                    $rows = $chunk->map(function ($outcome, $index) use ($convocationList) {
                        $formData   = $outcome->application->form_data;
                        $courseId   = $formData['position']['id'];
                        $categoryId = $formData['admission_categories'][0]['id'];

                        return [
                            'convocation_list_id'   => $convocationList->id,
                            'application_id'        => $outcome->application_id,
                            'course_id'             => $courseId,
                            'admission_category_id' => $categoryId,
                            'ranking_at_generation' => $index + 1,
                            'status'                => 'eligible',
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ];
                    })->all();

                    ConvocationListApplication::insert($rows);
                    $totalCreated += count($rows);
                });
        });

        return $totalCreated;
    }
}