<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentVerificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'name' => $this->form_data['name'],
            'social_name' => $this->form_data['social_name'] ?? null,
            'edital' => $this->form_data['edital'],
            'course' => $this->form_data['position']['name'],
            'academic_unit' => $this->form_data['position']['academic_unit']['description'],
            'admission_categories' => array_map(function ($admissionCategory) {
                return $admissionCategory['name'];
            }, $this->form_data['admission_categories']),
            'bonus' => $this->form_data['bonus']['name'] ?? null,
            'registration_date' => $this->updated_at,
            'verification_code' => $this->verification_code,
        ];

        return $data;
    }
}
