<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use EloquentFilter\Filterable;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Document",
 *     required={"title", "description", "path", "filename"},
 *     @OA\Property(property="id", type="integer", readOnly=true, description="Document ID"),
 *     @OA\Property(property="title", type="string", description="Title of the document"),
 *     @OA\Property(property="description", type="string", description="Description of the document"),
 *     @OA\Property(property="path", type="string", description="Path where the document is stored"),
 *     @OA\Property(property="filename", type="string", description="Original name of the document file"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 */
class Document extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'title',
        'description',
        'path',
        'filename',
    ];
}
