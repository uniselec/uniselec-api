<?php

namespace App\Services;

use App\Models\ConvocationListSeat;
use Illuminate\Support\Facades\DB;

class SeatRedistributionService
{
    /**
     * Redistribui UM seat, mudando apenas sua current_admission_category_id,
     * conforme remap_rules do processo seletivo.
     *
     * @param  ConvocationListSeat  $seat
     * @return bool  true se encontrou e atualizou; false caso contrário
     */
    public function redistributeSeat(ConvocationListSeat $seat): bool
    {
        // 1) Só redistribui vagas abertas, sem aplicação alocada
        if ($seat->status !== 'open' || $seat->application_id) {
            return false;
        }

        $ps       = $seat->list->processSelection;
        $rules    = $ps->remap_rules;               // ex: ['LB - PPI' => [...], ...]
        $origin   = $seat->originCategory->name;    // nome da categoria original
        $fallback = $rules[$origin] ?? [];          // array de nomes de categoria

        if (! is_array($fallback) || empty($fallback)) {
            return false;
        }

        // transforma admission_categories em Collection para buscar por name
        $admissionCats = collect($ps->admission_categories);

        // 2) percorre cada categoria fallback na ordem
        foreach ($fallback as $nextName) {
            $cat = $admissionCats->first(fn($c) => ($c['name'] ?? null) === $nextName);
            if (! $cat || empty($cat['id'])) {
                continue;
            }

            // 3) atualiza o seat para essa nova categoria
            DB::transaction(function() use ($seat, $cat) {
                $seat->current_admission_category_id = $cat['id'];
                $seat->save();
            });

            return true;
        }

        return false;
    }
}
