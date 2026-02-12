<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppealDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appeal_id' => $this->appeal_id,
            'path' => $this->path,
            'original_name' => $this->original_name,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
