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
     * Tenta convocar a inscrição $claId na lista $listId.
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

            // 1) Conta só as vagas “open” — não inclui as já reserved
            $availableSeats = ConvocationListSeat::where([
                ['convocation_list_id',         $listId],
                ['course_id',                   $target->course_id],
                ['origin_admission_category_id', $target->admission_category_id],
                ['status',                      'open'],
            ])->lockForUpdate()->count();

            // 2) Busca todos os pending até o target
            $candidates = ConvocationListApplication::where([
                ['convocation_list_id',    $listId],
                ['course_id',              $target->course_id],
                ['admission_category_id',  $target->admission_category_id],
                ['convocation_status',     'pending'],
            ])
                ->orderBy('general_ranking')
                ->get();

            foreach ($candidates as $cla) {
                // pára quando passa o target
                if ($cla->general_ranking > $target->general_ranking) {
                    break;
                }

                if ($availableSeats > 0) {
                    // ainda há vaga: pega o próximo seat “open”
                    $seat = ConvocationListSeat::where([
                        ['convocation_list_id',         $listId],
                        ['course_id',                   $cla->course_id],
                        ['origin_admission_category_id', $cla->admission_category_id],
                        ['status',                      'open'],
                    ])
                        ->lockForUpdate()
                        ->first();

                    // reserva
                    $seat->status         = 'reserved';
                    $seat->application_id = $cla->application_id;
                    $seat->save();

                    $cla->seat_id            = $seat->id;
                    $cla->convocation_status = 'called';
                    $availableSeats--;      // decrementa a cota dinâmica
                } else {
                    // sem vaga restante, mas ainda dentro do target
                    $cla->convocation_status = 'called_out_of_quota';
                }

                $cla->save();

                if ($cla->id === $target->id) {
                    break;
                }
            }

            // 3) Se o target foi realmente called, pula as outras inscrições do mesmo usuário
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
