<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory, Filterable;

    protected $fillable = ['name', 'academic_unit', 'modality'];

    protected $casts = [
        'modality'      => 'string',
        'academic_unit' => 'array',
    ];
}
