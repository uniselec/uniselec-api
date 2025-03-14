<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory, Filterable;

    protected $fillable = ['name', 'state', 'modality', 'campus'];

    protected $casts = [
        'modality' => 'string',
    ];

    public function processSelections()
    {
        return $this->belongsToMany(ProcessSelection::class, 'process_selection_courses')
            ->withPivot('vacancies');
    }
}
