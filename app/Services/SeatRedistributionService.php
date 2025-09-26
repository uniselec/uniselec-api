<?php
namespace App\Services;

use App\Models\ConvocationList;
use App\Models\ConvocationListSeat;
use App\Models\ConvocationListApplication;
use Illuminate\Support\Facades\DB;

class SeatRedistributionService
{
    /**
     * Preenche vagas até acabar inscrição elegível
     * ou não restarem mais assentos livres/remanejáveis.
     *
     * @return int  quantidade de assentos efetivamente preenchidos
     */
    public function redistribute(ConvocationList $list): int
    {
        /** ------------------------------------------------------------------
         * →  Carrega regras na ordem definida pelo usuário
         * -----------------------------------------------------------------*/
        $rules = $list->remap_rules['order'] ?? [];              // ← nomes
        $chains = $list->remap_rules['chains'] ?? [];

        // Índice rápido: nomeCategoria → ID
        $nameToId = collect($list->processSelection->admission_categories)
            ->pluck('id', 'name');

        /** ------------------------------------------------------------------
         * →  Pré-carrega todas as applications elegíveis
         *      (já ordenadas pelo ranking geral que você salva)
         * -----------------------------------------------------------------*/
        $eligible = ConvocationListApplication::query()
            ->where('convocation_list_id', $list->id)
            ->where('status', 'eligible')
            ->orderBy('ranking_at_generation')
            ->get()
            ->groupBy(fn ($cla) =>           // agrupa por categoria ID
                $cla->admission_category_id
            );

        /** ------------------------------------------------------------------
         * →  Loop até não existir vaga “open” ou acabar inscrição
         * -----------------------------------------------------------------*/
        $filledCounter = 0;

        DB::transaction(function () use (
            $list,
            $rules,
            $chains,
            $nameToId,
            $eligible,
            &$filledCounter
        ) {
            /**
             * Procura o 1.º assento OPEN (ou RESERVED) para cada rodada
             *    - status open   → nunca teve dono
             *    - status reserved→ ficou sem candidato, está livre p/ remap
             */
            do {
                $seat = ConvocationListSeat::query()
                    ->where('convocation_list_id', $list->id)
                    ->whereIn('status', ['open', 'reserved'])
                    ->orderBy('seat_code')          // qualquer ordem determinística
                    ->first();

                if (!$seat) {
                    break;                          // nada mais a fazer
                }

                /* -------- tenta preencher seguindo cadeia de remanejamento ---*/
                $currentCatName = $seat->currentCategory->name;

                // monta cadeia:   própria ← + ordem global
                $searchChain = array_merge(
                    [$currentCatName],
                    $chains[$currentCatName] ?? []
                );

                $applicationChosen = null;

                foreach ($searchChain as $catName) {
                    $catId = $nameToId[$catName] ?? null;
                    if (!$catId) continue;

                    // pega a 1ª inscrição dessa categoria ainda elegível
                    $app = optional($eligible->get($catId))->shift();
                    if ($app) {
                        // remove do bucket (para não usar de novo)
                        $eligible[$catId] = $eligible[$catId]->slice(1);
                        $applicationChosen = $app;
                        break;
                    }
                }

                /* -------- encontrou alguém? Preenche. Caso contrário,
                 *         remapeia categoria seguindo cadeia global -------- */
                if ($applicationChosen) {
                    $seat->update([
                        'application_id'            => $applicationChosen->application_id,
                        'current_admission_category_id' => $applicationChosen->admission_category_id,
                        'status'                    => 'filled',
                    ]);

                    $applicationChosen->update([
                        'seat_id' => $seat->id,
                        'status'  => 'convoked',
                    ]);

                    $filledCounter++;
                } else {
                    // remapeia assento para próxima categoria da cadeia global
                    $nextName = $chains[$currentCatName][0] ?? null;
                    if (!$nextName) {                             // sem mais cadeia
                        $seat->update(['status' => 'filled']);    // não será mais usado
                    } else {
                        $seat->update([
                            'current_admission_category_id' => $nameToId[$nextName],
                            'status'                        => 'reserved',
                        ]);

                        // reorganiza cadeia para próxima tentativa
                        $chains[$currentCatName] = array_slice(
                            $chains[$currentCatName], 1
                        );
                    }
                }
            } while (true);
        });

        return $filledCounter;
    }
}
