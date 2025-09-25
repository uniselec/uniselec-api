<?php


namespace App\Services;

use App\Models\AdmissionCategory;
use App\Models\ConvocationList;
use App\Models\ConvocationListApplication;
use App\Models\ConvocationListSeat;
use Illuminate\Support\Facades\DB;

class SeatAllocatorService
{
    /**
     * Distribui as vagas da lista conforme as regras de remanejamento.
     *
     * @return array  ['taken' => n, 'unfilled' => m]
     */
    public function allocate(ConvocationList $convocationList): array
    {
        $remapChains   = $convocationList->remap_rules['chains'] ?? [];
        $categoryIdMap = AdmissionCategory::pluck('id', 'name');

        $counters = ['taken' => 0, 'unfilled' => 0];

        DB::transaction(function () use (
            $convocationList,
            $remapChains,
            $categoryIdMap,
            &$counters
        ) {
            /** @var ConvocationListSeat $seat */
            foreach (
                $convocationList->seats()
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->get() as $seat
            ) {
                // 1 · tenta na própria categoria
                $application = $this->nextEligibleApplication(
                    $convocationList,
                    $seat->course_id,
                    $seat->origin_admission_category_id
                );

                // 2 · percorre cadeia de remanejamento, se necessário
                if (!$application) {
                    $originName = $seat->originCategory->name;
                    $chain      = $remapChains[$originName] ?? [];

                    foreach ($chain as $destinationCategoryName) {
                        $destinationCategoryId = $categoryIdMap[$destinationCategoryName] ?? null;
                        if (!$destinationCategoryId) {
                            continue;
                        }

                        $application = $this->nextEligibleApplication(
                            $convocationList,
                            $seat->course_id,
                            $destinationCategoryId
                        );

                        if ($application) {
                            $seat->current_admission_category_id = $destinationCategoryId;
                            break;
                        }
                    }
                }

                // 3 · gravações finais
                if ($application) {
                    $seat->application_id = $application->application_id;
                    $seat->status         = 'filled';   // ENUM de convocation_list_seats
                    $seat->save();

                    $application->status  = 'convoked'; // ENUM de convocation_list_applications
                    $application->seat_id = $seat->id;
                    $application->save();

                    $counters['taken']++;
                } else {
                    // vaga segue aberta
                    $counters['unfilled']++;
                }
            }
        });

        return $counters;
    }

    /**
     * Retorna o próximo candidato elegível para curso + categoria.
     */
    private function nextEligibleApplication(
        ConvocationList $convocationList,
        int $courseId,
        int $categoryId
    ): ?ConvocationListApplication {
        return ConvocationListApplication::where([
                'convocation_list_id'   => $convocationList->id,
                'course_id'             => $courseId,
                'admission_category_id' => $categoryId,
                'status'                => 'eligible',
            ])
            ->orderBy('ranking_at_generation')
            ->lockForUpdate()
            ->first();
    }
}
