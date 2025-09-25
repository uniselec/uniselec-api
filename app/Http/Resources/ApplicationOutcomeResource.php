<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationOutcomeResource extends JsonResource
{
    public function toArray($request)
    {
        $data = parent::toArray($request);

        if(isset($data['application'])) {
            $data['application']['resolved_inconsistencies'] = [
                'selected_name'      => $this->getResolvedValue($data, 'name'),
                'selected_birthdate' => $this->getResolvedValue($data, 'birthdate'),
                'selected_cpf'       => $this->getResolvedValue($data, 'cpf'),
            ];
        }

        return $data;
    }

    private function getResolvedValue(array $data, string $field): ?string
    {
        $source = $data['application']["{$field}_source"] ?? null;

        if (!$source) {
            return null;
        }

        return $source === 'application'
            ? ($data['application']['form_data'][$field] ?? null)
            : ($data['application']['enem_score']['scores'][$field] ?? null);
    }
}
