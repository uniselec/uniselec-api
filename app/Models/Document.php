<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use EloquentFilter\Filterable;
use OpenApi\Annotations as OA;

class Document extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'title',
        'process_selection_id',
        'description',
        'path',
        'filename',
    ];
    public function processSelection()
    {
        return $this->belongsTo(ProcessSelection::class);
    }
}
