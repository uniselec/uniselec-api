<?php
// app/Services/RankingService.php

namespace App\Services;

use App\Models\{
    ConvocationList,
    ConvocationListApplication
};
use Illuminate\Support\Facades\DB;

class RankingService
{
    /**
     * Gera/atualiza ranking_in_category para cada lista de convocação.
     *
     * @return int Total de registros ranqueados
     */
    public function rankConvocationList(ConvocationList $convocationList): int
    {
        $totalUpdated = 0;

        DB::transaction(function () use ($convocationList, &$totalUpdated) {
            // agrupa por curso + categoria
            $groups = ConvocationListApplication::query()
                ->select('course_id', 'admission_category_id')
                ->where('convocation_list_id', $convocationList->id)
                ->distinct()
                ->get();

            foreach ($groups as $group) {
                $applications = ConvocationListApplication::query()
                    ->join('application_outcomes as ao', 'ao.application_id', '=', 'convocation_list_applications.application_id')
                    ->where('convocation_list_applications.convocation_list_id', $convocationList->id)
                    ->where('convocation_list_applications.course_id', $group->course_id)
                    ->where('convocation_list_applications.admission_category_id', $group->admission_category_id)
                    ->where('convocation_list_applications.status', 'eligible')
                    ->orderByDesc('ao.final_score')
                    ->orderByDesc('ao.average_score')
                    ->orderBy('convocation_list_applications.application_id')
                    ->select('convocation_list_applications.id')
                    ->lockForUpdate()
                    ->get();

                $rank = 1;
                foreach ($applications as $app) {
                    ConvocationListApplication::where('id', $app->id)
                        ->update(['ranking_in_category' => $rank]);
                    $rank++;
                    $totalUpdated++;
                }
            }
        });

        return $totalUpdated;
    }
}
