<?php

namespace App\Services;

use App\Models\{
    ConvocationList,
    ConvocationListApplication,
    ConvocationListSeat
};
use Illuminate\Support\Facades\DB;

class ApplicationCallerService
{
    public function callByApplication(int $claId): void
    {
        $cla = ConvocationListApplication::findOrFail($claId);
        $this->call($cla->convocation_list_id, $claId);
    }

    /**
     * Tenta convocar a inscrição $claId na lista $listId,
     * alocando vagas de acordo com current_admission_category_id.
     *
     * @return void
     */
    public function call(int $listId, int $claId): void
    {
        DB::transaction(function () use ($listId, $claId) {
            $target = ConvocationListApplication::where('id', $claId)
                ->where('convocation_list_id', $listId)
                ->firstOrFail();

            if ($target->convocation_status === 'skipped') {
                return;
            }

            // 1) Conta vagas “open” usando current_admission_category_id
            $availableSeats = ConvocationListSeat::where([
                ['convocation_list_id',             $listId],
                ['course_id',                       $target->course_id],
                ['current_admission_category_id',   $target->admission_category_id],
                ['status',                          'open'],
            ])->lockForUpdate()->count();

            // 2) Busca candidatos pending OU already out_of_quota até o target
            $candidates = ConvocationListApplication::where('convocation_list_id', $listId)
                ->where('course_id', $target->course_id)
                ->where('admission_category_id', $target->admission_category_id)
                ->whereIn('convocation_status', ['pending', 'called_out_of_quota'])
                ->orderBy('general_ranking')
                ->get();

            foreach ($candidates as $cla) {
                if ($cla->general_ranking > $target->general_ranking) {
                    break;
                }

                if ($availableSeats > 0) {
                    // 3) Reserva um seat “open” baseado em current_admission_category_id
                    $seat = ConvocationListSeat::where([
                        ['convocation_list_id',           $listId],
                        ['course_id',                     $cla->course_id],
                        ['current_admission_category_id', $cla->admission_category_id],
                        ['status',                        'open'],
                    ])->lockForUpdate()->first();

                    $seat->status         = 'reserved';
                    $seat->application_id = $cla->application_id;
                    $seat->save();

                    $cla->seat_id            = $seat->id;
                    $cla->convocation_status = 'called';
                    $availableSeats--;
                } else {
                    $cla->convocation_status = 'called_out_of_quota';
                }

                $cla->save();

                if ($cla->id === $target->id) {
                    break;
                }
            }

            // 4) Se o target foi chamado com vaga, pula outras inscrições do mesmo usuário
            $target->refresh();
            if ($target->convocation_status === 'called') {
                ConvocationListApplication::where('convocation_list_id', $listId)
                    ->where('application_id', $target->application_id)
                    ->where('id', '<>', $target->id)
                    ->update(['convocation_status' => 'skipped']);
            }
        });
    }
}
