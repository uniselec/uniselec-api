<?php
namespace App\Services;

use App\Models\ConvocationListSeat;
use Illuminate\Support\Facades\DB;

class SeatRedistributionService
{
    public function redistributeSeat(ConvocationListSeat $seat): bool
    {
        // só vagas abertas e sem aplicação
        if ($seat->status !== 'open' || $seat->application_id) {
            return false;
        }

        $ps    = $seat->list->processSelection;
        $rules = $ps->remap_rules;

        // relation deve ser currentCategory
        $currentCat = $seat->currentCategory;
        if (! $currentCat) {
            return false;
        }

        $currentName = $currentCat->name;
        $fallback    = $rules[$currentName] ?? [];
        if (! is_array($fallback) || empty($fallback)) {
            return false;
        }

        $admissionCats = collect($ps->admission_categories);

        foreach ($fallback as $nextName) {
            $cat = $admissionCats->first(fn($c) => ($c['name'] ?? null) === $nextName);
            if (! $cat || empty($cat['id'])) {
                continue;
            }

            DB::transaction(function() use ($seat, $cat) {
                $seat->current_admission_category_id = $cat['id'];
                $seat->save();
            });

            return true;
        }

        return false;
    }
}
