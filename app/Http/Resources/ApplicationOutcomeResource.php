<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationOutcomeResource extends JsonResource
{

    public function toArray($request)
    {
        $data = parent::toArray($request);
        $data['application']['resolved_inconsistencies'] = [
            'selected_name' => $this->name_source
                ? ($this->name_source === 'application'
                ? ($this->form_data['name'] ?? null)
                    : ($this->enemScore?->scores['name'] ?? null)
                )
                : null,

            'selected_birthdate' => $this->birthdate_source
                ? ($this->birthdate_source === 'application'
                ? ($this->form_data['birthdate'] ?? null)
                    : ($this->enemScore?->scores['birthdate'] ?? null)
                )
                : null,

            'selected_cpf' => $this->cpf_source
                ? ($this->cpf_source === 'application'
                ? ($this->form_data['cpf'] ?? null)
                    : ($this->enemScore?->scores['cpf'] ?? null)
                )
                : null,
        ];

        return parent::toArray($request);
    }
}
