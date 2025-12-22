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
     * Regra:
     * - Ao convocar o alvo, convoca também todos os candidatos acima (até o alvo).
     * - Somente quem ficar "called" (com vaga/seat reservado) deve virar "skipped" nas outras modalidades
     *   dentro da MESMA convocation_list.
     * - Quem ficar "called_out_of_quota" NÃO elimina outras modalidades.
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

            // 1) Conta vagas abertas (open) na categoria atual
            $availableSeats = ConvocationListSeat::where([
                ['convocation_list_id',           $listId],
                ['course_id',                     $target->course_id],
                ['current_admission_category_id', $target->admission_category_id],
                ['status',                        'open'],
            ])->lockForUpdate()->count();

            // 2) Candidatos até o target (por general_ranking)
            $candidates = ConvocationListApplication::where('convocation_list_id', $listId)
                ->where('course_id', $target->course_id)
                ->where('admission_category_id', $target->admission_category_id)
                ->whereIn('convocation_status', ['pending', 'called_out_of_quota'])
                ->orderBy('general_ranking')
                ->lockForUpdate()
                ->get();

            // SOMENTE applications que realmente ficaram "called" (com seat)
            $calledWithSeatApplicationIds = [];

            foreach ($candidates as $cla) {
                if ($cla->general_ranking > $target->general_ranking) {
                    break;
                }

                if ($availableSeats > 0) {
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

                        // ✅ só entra aqui se virou called
                        $calledWithSeatApplicationIds[] = (int) $cla->application_id;
                    } else {
                        // sem seat por algum motivo: fora de vaga
                        $cla->convocation_status = 'called_out_of_quota';
                    }
                } else {
                    $cla->convocation_status = 'called_out_of_quota';
                }

                $cla->save();

                if ($cla->id === $target->id) {
                    break;
                }
            }

            $calledWithSeatApplicationIds = array_values(array_unique($calledWithSeatApplicationIds));

            // 3) Só elimina outras modalidades quando a application ficou "called"
            if (!empty($calledWithSeatApplicationIds)) {
                ConvocationListApplication::where('convocation_list_id', $listId)
                    ->whereIn('application_id', $calledWithSeatApplicationIds)
                    // não mexe no registro que já é called (o que “ganhou a vaga”)
                    // e nem no que já está skipped
                    ->whereNotIn('convocation_status', ['called', 'skipped'])
                    ->update(['convocation_status' => 'skipped']);
            }
        });
    }
}
