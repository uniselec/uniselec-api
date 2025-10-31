<?php

namespace App\Services;

use App\Models\ConvocationListApplication;
use App\Models\ConvocationListSeat;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplicationResponseService
{
    /**
     * Marca a aceitação da vaga para a inscrição chamada.
     *
     * @param  int  $claId
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function accept(int $claId): void
    {
        DB::transaction(function () use ($claId) {
            $cla = ConvocationListApplication::lockForUpdate()->findOrFail($claId);

            if ($cla->convocation_status !== 'called' || $cla->response_status !== 'pending') {
                throw new InvalidArgumentException("Só é possível aceitar candidaturas em status called e response pending.");
            }

            // preenche a vaga
            if ($cla->seat_id) {
                $seat = ConvocationListSeat::lockForUpdate()->find($cla->seat_id);
                $seat->status = 'filled';
                $seat->save();
            }

            // atualiza o response_status
            $cla->response_status = 'accepted';
            $cla->save();

            // nas outras convocações desta mesma application, marca declined_other_list
            ConvocationListApplication::where('application_id', $cla->application_id)
                ->where('id', '<>', $cla->id)
                ->update(['response_status' => 'declined_other_list']);
        });
    }

    /**
     * Marca a recusa da vaga para a inscrição chamada.
     *
     * @param  int  $claId
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function decline(int $claId): void
    {
        DB::transaction(function () use ($claId) {
            $cla = ConvocationListApplication::lockForUpdate()->findOrFail($claId);

            if ($cla->convocation_status !== 'called' || $cla->response_status !== 'pending') {
                throw new InvalidArgumentException("Só é possível recusar candidaturas em status called e response pending.");
            }

            // libera a vaga novamente
            if ($cla->seat_id) {
                $seat = ConvocationListSeat::lockForUpdate()->find($cla->seat_id);
                $seat->status = 'open';
                $seat->application_id = null;
                $seat->save();
            }

            // atualiza o response_status
            $cla->response_status = 'declined';
            $cla->save();

            // nas outras convocações desta mesma application, marca declined_other_list
            ConvocationListApplication::where('application_id', $cla->application_id)
                ->where('id', '<>', $cla->id)
                ->update(['response_status' => 'declined_other_list']);
        });
    }

    /**
     * Indeferir (rejeitar) a convocação por irregularidade.
     *
     * @param  int  $claId
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function reject(int $claId): void
    {
        DB::transaction(function () use ($claId) {
            $cla = ConvocationListApplication::lockForUpdate()->findOrFail($claId);

            if ($cla->convocation_status !== 'called') {
                throw new InvalidArgumentException("Só é possível indeferir candidaturas em status called.");
            }

            // libera a vaga novamente
            if ($cla->seat_id) {
                $seat = ConvocationListSeat::lockForUpdate()->find($cla->seat_id);
                $seat->status = 'open';
                $seat->application_id = null;
                $seat->save();
            }

            // atualiza o response_status
            $cla->response_status = 'rejected';
            $cla->save();
        });
    }
}
