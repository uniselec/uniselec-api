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
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'form_data' => $this->form_data,
            'verification_expected' => md5(json_encode($this->form_data)),
            'verification_code' => $this->verification_code,
            'valid_verification_code' =>  md5(json_encode($this->form_data)) === $this->verification_code,
            'name_source' => $this->name_source,
            'birthdate_source' => $this->birthdate_source,
            'cpf_source' => $this->cpf_source,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'process_selection_id' => $this->process_selection_id,
            'process_selection' => new ProcessSelectionResource($this->whenLoaded('processSelection')),
            'resolved_inconsistencies' => [
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
            ]
        ];
    }
}
