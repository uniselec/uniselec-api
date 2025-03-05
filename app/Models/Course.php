<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'modality', 'campus', 'uf'];

    protected $casts = [
        'modality' => 'string',
    ];

    public function processSelections()
    {
        return $this->belongsToMany(ProcessSelection::class, 'process_selection_courses')
            ->withPivot('vacancies');
    }
}
