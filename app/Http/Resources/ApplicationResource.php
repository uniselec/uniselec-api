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
            'data' => $this->data,
            'verification_expected' => md5(json_encode($this->data)),
            'verification_code' => $this->verification_code,
            'valid_verification_code' =>  md5(json_encode($this->data)) === $this->verification_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'process_selection'       => new ProcessSelectionResource($this->whenLoaded('processSelection')),
        ];
    }
}
