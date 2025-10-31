<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConvocationListSeatResource extends JsonResource
{
    public function toArray($request)
    {
        // pega tudo que já existia
        $data = parent::toArray($request);

        // tenta carregar a lista (belongsto) — pode ser null se não veio eager-loaded
        $list = $this->list ?? $this->list()->first();

        $hasPendingOrCalledOut = false;
        if ($list) {
            $hasPendingOrCalledOut = $list->applications()
                ->where('course_id', $this->course_id)
                ->where('admission_category_id', $this->current_admission_category_id)
                ->whereIn('convocation_status', ['pending','called_out_of_quota'])
                ->exists();
        }

        $data['can_redistribute'] = ($this->status === 'open') && ! $hasPendingOrCalledOut;

        return $data;
    }
}
