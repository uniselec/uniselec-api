<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppealResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'justification' => $this->justification,
            'decision' => $this->decision,
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Nested resource for attached documents
            'documents' => AppealDocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}
