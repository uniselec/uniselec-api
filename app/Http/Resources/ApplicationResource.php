<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $formHash = md5(json_encode($this->form_data));

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'form_data' => $this->form_data,
            'verification_expected' => $formHash,
            'verification_code' => $this->verification_code,
            'valid_verification_code' => $formHash === $this->verification_code,
            'name_source' => $this->name_source,
            'birthdate_source' => $this->birthdate_source,
            'cpf_source' => $this->cpf_source,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'process_selection_id' => $this->process_selection_id,
            'process_selection' => new ProcessSelectionResource($this->whenLoaded('processSelection')),
            'resolved_inconsistencies' => [
                'selected_name'      => $this->getResolvedValue($this->name_source, 'name'),
                'selected_birthdate' => $this->getResolvedValue($this->birthdate_source, 'birthdate'),
                'selected_cpf'       => $this->getResolvedValue($this->cpf_source, 'cpf'),
            ],
        ];
    }

    private function getResolvedValue(?string $source, string $formKey): ?string
    {
        if (!$source) {
            return null;
        }

        return $source === 'application'
        ? ($this->form_data[$formKey] ?? null)
            : ($this->enemScore?->scores[$formKey] ?? null);
    }
}
