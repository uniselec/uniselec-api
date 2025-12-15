<?php

namespace App\Services;

use App\Models\ConvocationListApplication;
use App\Models\ConvocationListSeat;
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
     * Regra: ao convocar o alvo, convoca também todos os candidatos acima (até o alvo),
     * e TODOS os convocados (called e called_out_of_quota) devem ser "skipped" nas outras
     * modalidades dentro da mesma convocation_list.
     */
    public function call(int $listId, int $claId): void
    {
        DB::transaction(function () use ($listId, $claId) {
            $target = ConvocationListApplication::where('id', $claId)
                ->where('convocation_list_id', $listId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($target->convocation_status === 'skipped') {
                return;
            }

            // 1) Conta vagas “open” usando current_admission_category_id
            $availableSeats = ConvocationListSeat::where([
                ['convocation_list_id',           $listId],
                ['course_id',                     $target->course_id],
                ['current_admission_category_id', $target->admission_category_id],
                ['status',                        'open'],
            ])->lockForUpdate()->count();

            // 2) Busca candidatos pending OU already out_of_quota até o target
            // (mantendo a lógica original: ordenação por general_ranking)
            $candidates = ConvocationListApplication::where('convocation_list_id', $listId)
                ->where('course_id', $target->course_id)
                ->where('admission_category_id', $target->admission_category_id)
                ->whereIn('convocation_status', ['pending', 'called_out_of_quota'])
                ->orderBy('general_ranking')
                ->lockForUpdate()
                ->get();

            // Guarda TODAS as applications que forem convocadas nesse “call”
            $calledApplicationIds = [];

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

                    if ($seat) {
                        $seat->status         = 'reserved';
                        $seat->application_id = $cla->application_id;
                        $seat->save();

                        $cla->seat_id            = $seat->id;
                        $cla->convocation_status = 'called';
                        $availableSeats--;
                    } else {
                        // segurança: se por qualquer motivo não achar seat, cai para fora de vaga
                        $cla->convocation_status = 'called_out_of_quota';
                    }
                } else {
                    $cla->convocation_status = 'called_out_of_quota';
                }

                $cla->save();

                $calledApplicationIds[] = (int) $cla->application_id;

                if ($cla->id === $target->id) {
                    break;
                }
            }

            $calledApplicationIds = array_values(array_unique($calledApplicationIds));

            if (!empty($calledApplicationIds)) {
                ConvocationListApplication::where('convocation_list_id', $listId)
                    ->whereIn('application_id', $calledApplicationIds)
                    ->whereNotIn('convocation_status', ['called', 'called_out_of_quota'])
                    ->update(['convocation_status' => 'skipped']);
            }
        });
    }
}
