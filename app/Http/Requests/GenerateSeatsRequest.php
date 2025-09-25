<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('super_user');
    }

    public function rules(): array
    {
        return [
            'seats'                       => 'required|array|min:1',
            'seats.*.course_id'           => 'required|exists:courses,id',
            'seats.*.vacanciesByCategory' => 'required|array|min:1',
            'seats.*.vacanciesByCategory.*' => 'required|integer|min:0',
        ];
    }
}
