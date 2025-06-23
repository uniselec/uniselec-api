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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
